<?php
/**
 * Inquiry (lead) repository.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Inquiry CRUD, search, export and notifications.
 */
final class Inquiry {

	/**
	 * Default status keys => Persian labels.
	 *
	 * @return array
	 */
	public static function statuses(): array {
		$statuses = array(
			'new'         => __( 'جدید', 'partino-smart-chatbot' ),
			'contacted'   => __( 'تماس گرفته شد', 'partino-smart-chatbot' ),
			'in_progress' => __( 'در حال پیگیری', 'partino-smart-chatbot' ),
			'quoted'      => __( 'قیمت ارسال شد', 'partino-smart-chatbot' ),
			'completed'   => __( 'تکمیل شد', 'partino-smart-chatbot' ),
			'cancelled'   => __( 'لغو شد', 'partino-smart-chatbot' ),
		);

		/**
		 * Filter inquiry statuses.
		 *
		 * @param array $statuses key => label.
		 */
		return (array) apply_filters( 'partino_inquiry_statuses', $statuses );
	}

	/**
	 * Create an inquiry from a completed conversation context.
	 *
	 * @param array $context Conversation context (part/vehicle/phone).
	 * @param int   $conversation_id Conversation id.
	 *
	 * @return int Inquiry id (0 on failure).
	 */
	public static function create( array $context, int $conversation_id ): int {
		global $wpdb;

		$phone = Security::normalize_phone( (string) ( $context['phone'] ?? '' ) );
		if ( '' === $phone && Settings::get( 'lead.phone_required', true ) ) {
			return 0;
		}

		// Duplicate handling.
		$dup_behavior = (string) Settings::get( 'lead.duplicate_behavior', 'allow' );
		if ( 'reject' === $dup_behavior && '' !== $phone ) {
			$table = Database::table( 'inquiries' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE customer_phone = %s AND part_name = %s AND vehicle_model = %s AND created_at > %s",
					$phone,
					(string) ( $context['part_name'] ?? '' ),
					(string) ( $context['vehicle_model'] ?? '' ),
					gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
				)
			);
			if ( $exists > 0 ) {
				return -1; // Signal duplicate.
			}
		}

		$data = array(
			'uuid'            => wp_generate_uuid4(),
			'conversation_id' => $conversation_id,
			'customer_phone'  => $phone,
			'customer_name'   => sanitize_text_field( (string) ( $context['name'] ?? '' ) ),
			'part_id'         => absint( $context['part_id'] ?? 0 ) ?: null,
			'part_name'       => sanitize_text_field( (string) ( $context['part_name'] ?? '' ) ),
			'part_category'   => sanitize_text_field( (string) ( $context['part_category'] ?? '' ) ),
			'vehicle_brand'   => sanitize_text_field( (string) ( $context['vehicle_brand'] ?? '' ) ),
			'vehicle_model'   => sanitize_text_field( (string) ( $context['vehicle_model'] ?? '' ) ),
			'vehicle_trim'    => sanitize_text_field( (string) ( $context['vehicle_trim'] ?? '' ) ),
			'vehicle_year'    => sanitize_text_field( (string) ( $context['vehicle_year'] ?? '' ) ),
			'status'          => 'new',
			'source'          => 'website',
			'page_url'        => esc_url_raw( (string) ( $context['page_url'] ?? '' ) ),
			'referrer'        => Security::referrer(),
			'device_type'     => Security::device_type(),
			'user_agent'      => Security::user_agent(),
			'ip_hash'         => Security::ip_hash(),
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);

		/**
		 * Fires before an inquiry is created.
		 *
		 * @param array $data Inquiry data.
		 */
		do_action( 'partino_before_inquiry_created', $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( Database::table( 'inquiries' ), $data );
		if ( ! $ok ) {
			Logger::error( 'db', 'Failed to insert inquiry.', array( 'error' => $wpdb->last_error ) );
			return 0;
		}

		$id = (int) $wpdb->insert_id;

		/**
		 * Fires after an inquiry is created.
		 *
		 * @param int   $id   Inquiry id.
		 * @param array $data Inquiry data.
		 */
		do_action( 'partino_after_inquiry_created', $id, $data );

		self::notify( $id, $data );

		return $id;
	}

	/**
	 * Email notification (and SMS hook for future integrations).
	 *
	 * @param int   $id   Inquiry id.
	 * @param array $data Inquiry data.
	 */
	private static function notify( int $id, array $data ): void {
		/**
		 * Hook for SMS / external providers.
		 *
		 * @param int   $id   Inquiry id.
		 * @param array $data Inquiry data.
		 */
		do_action( 'partino_inquiry_notification', $id, $data );

		if ( ! Settings::get( 'notifications.email_enabled', false ) ) {
			return;
		}

		$to = (string) Settings::get( 'notifications.admin_email', '' );
		if ( '' === $to || ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$vehicle = trim( $data['vehicle_brand'] . ' ' . $data['vehicle_model'] . ' ' . $data['vehicle_trim'] );

		$replacements = array(
			'{phone}'     => $data['customer_phone'],
			'{part}'      => $data['part_name'],
			'{vehicle}'   => $vehicle,
			'{year}'      => $data['vehicle_year'],
			'{id}'        => (string) $id,
			'{admin_url}' => admin_url( 'admin.php?page=partino-chatbot-inquiries&action=view&inquiry=' . $id ),
		);

		$subject = strtr( (string) Settings::get( 'notifications.subject', '' ), $replacements );
		$body    = strtr( (string) Settings::get( 'notifications.template', '' ), $replacements );

		if ( '' !== trim( $subject ) && '' !== trim( $body ) ) {
			wp_mail( $to, wp_strip_all_tags( $subject ), wp_strip_all_tags( $body ) );
		}
	}

	/**
	 * Query inquiries with search/filter/pagination.
	 *
	 * @param array $args Query args.
	 *
	 * @return array{items: array[], total: int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$table = Database::table( 'inquiries' );

		$defaults = array(
			'search'    => '',
			'status'    => '',
			'part'      => '',
			'vehicle'   => '',
			'date_from' => '',
			'date_to'   => '',
			'per_page'  => 20,
			'page'      => 1,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
		);
		$args     = array_merge( $defaults, $args );

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(customer_phone LIKE %s OR part_name LIKE %s OR vehicle_brand LIKE %s OR vehicle_model LIKE %s OR customer_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( '' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}
		if ( '' !== $args['part'] ) {
			$where[]  = 'part_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['part'] ) . '%';
		}
		if ( '' !== $args['vehicle'] ) {
			$like     = '%' . $wpdb->esc_like( $args['vehicle'] ) . '%';
			$where[]  = '(vehicle_brand LIKE %s OR vehicle_model LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( '' !== $args['date_from'] ) {
			$where[]  = 'created_at >= %s';
			$params[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}
		if ( '' !== $args['date_to'] ) {
			$where[]  = 'created_at <= %s';
			$params[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		$orderby = in_array( $args['orderby'], array( 'id', 'created_at', 'status', 'customer_phone' ), true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$items = (array) $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Get one inquiry.
	 *
	 * @param int $id Inquiry id.
	 *
	 * @return array|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = Database::table( 'inquiries' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Update status.
	 *
	 * @param int    $id     Inquiry id.
	 * @param string $status Status key.
	 *
	 * @return bool
	 */
	public static function set_status( int $id, string $status ): bool {
		if ( ! array_key_exists( $status, self::statuses() ) ) {
			return false;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->update(
			Database::table( 'inquiries' ),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		return false !== $updated;
	}

	/**
	 * Delete an inquiry (+conversation/messages per privacy policy).
	 *
	 * @param int $id Inquiry id.
	 */
	public static function delete( int $id ): void {
		global $wpdb;

		$inquiry = self::get( $id );
		if ( ! $inquiry ) {
			return;
		}

		$conversation_id = (int) ( $inquiry['conversation_id'] ?? 0 );
		if ( $conversation_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( Database::table( 'messages' ), array( 'conversation_id' => $conversation_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( Database::table( 'conversations' ), array( 'id' => $conversation_id ), array( '%d' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Database::table( 'inquiries' ), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Dashboard stats.
	 *
	 * @return array
	 */
	public static function stats(): array {
		global $wpdb;
		$table = Database::table( 'inquiries' );
		$conv  = Database::table( 'conversations' );

		$today      = current_time( 'Y-m-d' ) . ' 00:00:00';
		$week_start = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days', current_time( 'timestamp' ) ) );
		$month      = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days', current_time( 'timestamp' ) ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total          = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$new_count      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'new'" );
		$today_count    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $today ) );
		$week_count     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $week_start ) );
		$month_count    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $month ) );
		$total_convs    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$conv}" );
		// phpcs:enable

		$conversion = $total_convs > 0 ? round( ( $total / $total_convs ) * 100, 1 ) : 0;

		return array(
			'total'         => $total,
			'new'           => $new_count,
			'today'         => $today_count,
			'week'          => $week_count,
			'month'         => $month_count,
			'conversations' => $total_convs,
			'conversion'    => $conversion,
			'ai_requests'   => (int) get_option( 'partino_chatbot_ai_requests', 0 ),
			'ai_errors'     => (int) get_option( 'partino_chatbot_ai_errors', 0 ),
		);
	}
}
