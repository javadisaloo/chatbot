<?php
/**
 * Parts catalog repository.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Part CRUD + fuzzy matching.
 */
final class Part {

	/**
	 * List parts.
	 *
	 * @param bool $active_only Only active parts.
	 *
	 * @return array[]
	 */
	public static function get_list( bool $active_only = true ): array {
		global $wpdb;
		$table = Database::table( 'parts' );
		$sql   = "SELECT * FROM {$table}" . ( $active_only ? ' WHERE is_active = 1' : '' ) . ' ORDER BY sort_order ASC, name ASC';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table name.
		return (array) $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get one part.
	 *
	 * @param int $id Part id.
	 *
	 * @return array|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = Database::table( 'parts' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Insert or update a part.
	 *
	 * @param array $data Fields.
	 * @param int   $id   Existing id (0 = insert).
	 *
	 * @return int
	 */
	public static function save( array $data, int $id = 0 ): int {
		global $wpdb;
		$table = Database::table( 'parts' );

		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === $name ) {
			return 0;
		}

		$slug = sanitize_title( $data['slug'] ?? $name );

		$fields  = array(
			'name'       => $name,
			'slug'       => $slug,
			'category'   => sanitize_text_field( $data['category'] ?? '' ),
			'icon'       => sanitize_text_field( $data['icon'] ?? '' ),
			'aliases'    => sanitize_textarea_field( $data['aliases'] ?? '' ),
			'is_active'  => ! empty( $data['is_active'] ) ? 1 : 0,
			'sort_order' => intval( $data['sort_order'] ?? 0 ),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' );

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
	 * Delete a part.
	 *
	 * @param int $id Part id.
	 */
	public static function delete( int $id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Database::table( 'parts' ), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Fuzzy-match a part from user text.
	 *
	 * @param string $input Raw user text.
	 *
	 * @return array|null
	 */
	public static function match( string $input ): ?array {
		$needle = Normalizer::text( $input );
		if ( '' === $needle ) {
			return null;
		}

		$best       = null;
		$best_score = 0;

		foreach ( self::get_list( true ) as $part ) {
			$candidates = array( Normalizer::text( $part['name'] ), Normalizer::text( $part['slug'] ) );
			foreach ( explode( ',', (string) $part['aliases'] ) as $alias ) {
				$alias = Normalizer::text( $alias );
				if ( '' !== $alias ) {
					$candidates[] = $alias;
				}
			}
			foreach ( $candidates as $candidate ) {
				if ( '' === $candidate ) {
					continue;
				}
				$score = Normalizer::score( $needle, $candidate );
				if ( $score > $best_score ) {
					$best_score = $score;
					$best       = $part;
				}
			}
		}

		return ( $best_score >= 65 ) ? $best : null;
	}
}
