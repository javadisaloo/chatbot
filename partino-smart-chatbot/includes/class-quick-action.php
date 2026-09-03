<?php
/**
 * Quick actions repository.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Quick action CRUD.
 */
final class Quick_Action {

	/**
	 * List quick actions.
	 *
	 * @param bool $active_only Only active.
	 *
	 * @return array[]
	 */
	public static function get_list( bool $active_only = true ): array {
		global $wpdb;
		$table = Database::table( 'quick_actions' );
		$sql   = "SELECT * FROM {$table}" . ( $active_only ? ' WHERE is_active = 1' : '' ) . ' ORDER BY sort_order ASC, id ASC';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get one action.
	 *
	 * @param int $id Row id.
	 *
	 * @return array|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = Database::table( 'quick_actions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Insert or update.
	 *
	 * @param array $data Fields.
	 * @param int   $id   Existing id.
	 *
	 * @return int
	 */
	public static function save( array $data, int $id = 0 ): int {
		global $wpdb;
		$table = Database::table( 'quick_actions' );

		$title = sanitize_text_field( $data['title'] ?? '' );
		if ( '' === $title ) {
			return 0;
		}

		$fields  = array(
			'title'      => $title,
			'icon'       => sanitize_text_field( $data['icon'] ?? '' ),
			'part_id'    => absint( $data['part_id'] ?? 0 ) ?: null,
			'is_active'  => ! empty( $data['is_active'] ) ? 1 : 0,
			'sort_order' => intval( $data['sort_order'] ?? 0 ),
		);
		$formats = array( '%s', '%s', '%d', '%d', '%d' );

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $fields, array( 'id' => $id ), $formats, array( '%d' ) );
			return $id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $table, $fields, $formats );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete.
	 *
	 * @param int $id Row id.
	 */
	public static function delete( int $id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Database::table( 'quick_actions' ), array( 'id' => $id ), array( '%d' ) );
	}
}
