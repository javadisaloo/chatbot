<?php
/**
 * Conversation persistence (DB rows + messages).
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Conversation repository.
 */
final class Conversation {

	/**
	 * Create a new conversation.
	 *
	 * @param string $page_url Current page.
	 *
	 * @return array|null Row.
	 */
	public static function create( string $page_url = '' ): ?array {
		global $wpdb;

		$uuid = wp_generate_uuid4();
		$now  = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert(
			Database::table( 'conversations' ),
			array(
				'uuid'       => $uuid,
				'state'      => States::INITIAL,
				'context'    => wp_json_encode( array(), JSON_UNESCAPED_UNICODE ),
				'status'     => 'active',
				'ip_hash'    => Security::ip_hash(),
				'user_agent' => Security::user_agent(),
				'page_url'   => esc_url_raw( $page_url ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			Logger::error( 'db', 'Failed to create conversation.', array( 'error' => $wpdb->last_error ) );
			return null;
		}

		return self::get_by_uuid( $uuid );
	}

	/**
	 * Fetch by UUID (active sessions only unless $any).
	 *
	 * @param string $uuid UUID.
	 * @param bool   $any  Include completed conversations.
	 *
	 * @return array|null
	 */
	public static function get_by_uuid( string $uuid, bool $any = false ): ?array {
		if ( ! wp_is_uuid( $uuid ) ) {
			return null;
		}
		global $wpdb;
		$table = Database::table( 'conversations' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE uuid = %s", $uuid ), ARRAY_A );

		if ( ! $row ) {
			return null;
		}
		if ( ! $any && 'active' !== $row['status'] ) {
			return null;
		}

		// Session timeout check.
		$timeout_min = (int) Settings::get( 'conversation.session_timeout', 1440 );
		$age         = current_time( 'timestamp' ) - strtotime( $row['updated_at'] );
		if ( ! $any && $age > $timeout_min * MINUTE_IN_SECONDS ) {
			return null;
		}

		return $row;
	}

	/**
	 * Get by numeric id (admin usage).
	 *
	 * @param int $id Row id.
	 *
	 * @return array|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = Database::table( 'conversations' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Persist state + context.
	 *
	 * @param int    $id      Conversation id.
	 * @param string $state   New state.
	 * @param array  $context Context.
	 * @param string $status  active|completed.
	 */
	public static function update_state( int $id, string $state, array $context, string $status = 'active' ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			Database::table( 'conversations' ),
			array(
				'state'      => $state,
				'context'    => wp_json_encode( $context, JSON_UNESCAPED_UNICODE ),
				'status'     => in_array( $status, array( 'active', 'completed' ), true ) ? $status : 'active',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Decode context from a row.
	 *
	 * @param array $row Conversation row.
	 *
	 * @return array
	 */
	public static function context( array $row ): array {
		$ctx = json_decode( (string) ( $row['context'] ?? '' ), true );
		return is_array( $ctx ) ? $ctx : array();
	}

	/**
	 * Add a message.
	 *
	 * @param int    $conversation_id Conversation id.
	 * @param string $role            user|assistant|system.
	 * @param string $content         Message text.
	 * @param array  $meta            Optional meta (options shown, etc.).
	 */
	public static function add_message( int $conversation_id, string $role, string $content, array $meta = array() ): void {
		global $wpdb;

		$max = (int) Settings::get( 'conversation.max_length', 120 );
		if ( self::count_messages( $conversation_id ) >= $max ) {
			return;
		}

		/**
		 * Fires when a conversation message is stored.
		 *
		 * @param int    $conversation_id Conversation id.
		 * @param string $role            Role.
		 * @param string $content         Content.
		 */
		do_action( 'partino_conversation_message', $conversation_id, $role, $content );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			Database::table( 'messages' ),
			array(
				'conversation_id' => $conversation_id,
				'role'            => in_array( $role, array( 'user', 'assistant', 'system' ), true ) ? $role : 'user',
				'content'         => sanitize_textarea_field( $content ),
				'meta'            => ! empty( $meta ) ? wp_json_encode( $meta, JSON_UNESCAPED_UNICODE ) : null,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Message count for a conversation.
	 *
	 * @param int $conversation_id Conversation id.
	 *
	 * @return int
	 */
	public static function count_messages( int $conversation_id ): int {
		global $wpdb;
		$table = Database::table( 'messages' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE conversation_id = %d", $conversation_id ) );
	}

	/**
	 * Fetch messages (user/assistant only by default — system stays internal).
	 *
	 * @param int  $conversation_id Conversation id.
	 * @param bool $include_system  Include system messages (admin only).
	 *
	 * @return array[]
	 */
	public static function get_messages( int $conversation_id, bool $include_system = false ): array {
		global $wpdb;
		$table = Database::table( 'messages' );

		if ( $include_system ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY id ASC", $conversation_id ), ARRAY_A );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE conversation_id = %d AND role IN ('user','assistant') ORDER BY id ASC", $conversation_id ), ARRAY_A );
	}

	/**
	 * List conversations for admin.
	 *
	 * @param int $per_page Page size.
	 * @param int $page     Page number.
	 *
	 * @return array{items: array[], total: int}
	 */
	public static function query( int $per_page = 20, int $page = 1 ): array {
		global $wpdb;
		$table = Database::table( 'conversations' );
		$msgs  = Database::table( 'messages' );

		$per_page = max( 1, min( 200, $per_page ) );
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$items = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, (SELECT COUNT(*) FROM {$msgs} m WHERE m.conversation_id = c.id) AS message_count
				 FROM {$table} c ORDER BY c.updated_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Retention cleanup: delete stale conversations without inquiries.
	 */
	public static function cleanup(): void {
		global $wpdb;

		$days = (int) Settings::get( 'conversation.retention_days', 90 );
		if ( $days <= 0 ) {
			return;
		}

		$cutoff    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days", current_time( 'timestamp' ) ) );
		$conv      = Database::table( 'conversations' );
		$msgs      = Database::table( 'messages' );
		$inquiries = Database::table( 'inquiries' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT c.id FROM {$conv} c
				 LEFT JOIN {$inquiries} i ON i.conversation_id = c.id
				 WHERE i.id IS NULL AND c.updated_at < %s LIMIT 500",
				$cutoff
			)
		);
		// phpcs:enable

		foreach ( $ids as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $msgs, array( 'conversation_id' => (int) $id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $conv, array( 'id' => (int) $id ), array( '%d' ) );
		}
	}
}
