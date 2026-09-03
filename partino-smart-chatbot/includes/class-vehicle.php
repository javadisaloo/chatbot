<?php
/**
 * Vehicle catalog repository (brands → models → trims).
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Vehicle CRUD + fuzzy matching.
 */
final class Vehicle {

	/**
	 * Fetch rows by type/parent.
	 *
	 * @param string   $type        brand|model|trim.
	 * @param int|null $parent_id   Parent id filter.
	 * @param bool     $active_only Only active rows.
	 *
	 * @return array[]
	 */
	public static function get_list( string $type = 'brand', ?int $parent_id = null, bool $active_only = true ): array {
		global $wpdb;
		$table = Database::table( 'vehicles' );

		$where  = array( 'type = %s' );
		$params = array( $type );

		if ( null !== $parent_id ) {
			$where[]  = 'parent_id = %d';
			$params[] = $parent_id;
		}
		if ( $active_only ) {
			$where[] = 'is_active = 1';
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY sort_order ASC, name ASC';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- built with placeholders above.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Get one row.
	 *
	 * @param int $id Row id.
	 *
	 * @return array|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Insert or update a vehicle row.
	 *
	 * @param array $data Sanitized fields.
	 * @param int   $id   Existing id (0 = insert).
	 *
	 * @return int Row id or 0 on failure.
	 */
	public static function save( array $data, int $id = 0 ): int {
		global $wpdb;
		$table = Database::table( 'vehicles' );

		$fields = array(
			'parent_id'  => absint( $data['parent_id'] ?? 0 ),
			'type'       => in_array( $data['type'] ?? '', array( 'brand', 'model', 'trim' ), true ) ? $data['type'] : 'brand',
			'name'       => sanitize_text_field( $data['name'] ?? '' ),
			'name_en'    => sanitize_text_field( $data['name_en'] ?? '' ),
			'aliases'    => sanitize_textarea_field( $data['aliases'] ?? '' ),
			'year_start' => absint( $data['year_start'] ?? 0 ),
			'year_end'   => absint( $data['year_end'] ?? 0 ),
			'is_active'  => ! empty( $data['is_active'] ) ? 1 : 0,
			'sort_order' => intval( $data['sort_order'] ?? 0 ),
		);

		if ( '' === $fields['name'] ) {
			return 0;
		}

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' );

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
	 * Delete a row and its descendants.
	 *
	 * @param int $id Row id.
	 */
	public static function delete( int $id ): void {
		global $wpdb;
		$table = Database::table( 'vehicles' );

		// Collect descendant ids (2 levels max: brand→model→trim).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$children = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE parent_id = %d", $id ) );
		foreach ( $children as $child_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $table, array( 'parent_id' => (int) $child_id ), array( '%d' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'parent_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Fuzzy-match user text to a model (searching brand+model names & aliases).
	 *
	 * @param string $input Raw user text.
	 *
	 * @return array|null Matched model row (with brand info) or null.
	 */
	public static function match_model( string $input ): ?array {
		$needle = Normalizer::number_words( Normalizer::text( $input ) );
		if ( '' === $needle ) {
			return null;
		}

		$models = self::get_list( 'model', null, true );
		$brands = array();
		foreach ( self::get_list( 'brand', null, true ) as $b ) {
			$brands[ (int) $b['id'] ] = $b;
		}

		$best       = null;
		$best_score = 0;

		foreach ( $models as $model ) {
			$brand = $brands[ (int) $model['parent_id'] ] ?? null;
			if ( ! $brand ) {
				continue;
			}

			$candidates = array(
				Normalizer::text( $model['name'] ),
				Normalizer::text( $model['name_en'] ),
				Normalizer::text( $brand['name'] . ' ' . $model['name'] ),
				Normalizer::text( $brand['name_en'] . ' ' . $model['name_en'] ),
			);
			foreach ( explode( ',', (string) $model['aliases'] ) as $alias ) {
				$alias = Normalizer::text( $alias );
				if ( '' !== $alias ) {
					$candidates[] = $alias;
					$candidates[] = Normalizer::text( $brand['name'] ) . ' ' . $alias;
				}
			}

			foreach ( $candidates as $candidate ) {
				if ( '' === $candidate ) {
					continue;
				}
				$score = Normalizer::score( $needle, $candidate );
				if ( $score > $best_score ) {
					$best_score = $score;
					$best       = array_merge(
						$model,
						array(
							'brand_id'      => (int) $brand['id'],
							'brand_name'    => $brand['name'],
							'brand_name_en' => $brand['name_en'],
						)
					);
				}
			}
		}

		return ( $best_score >= 70 ) ? $best : null;
	}

	/**
	 * Fuzzy-match a trim within a model.
	 *
	 * @param string $input    Raw user text.
	 * @param int    $model_id Model id.
	 *
	 * @return array|null
	 */
	public static function match_trim( string $input, int $model_id ): ?array {
		$needle = Normalizer::text( $input );
		if ( '' === $needle ) {
			return null;
		}

		$best       = null;
		$best_score = 0;

		foreach ( self::get_list( 'trim', $model_id, true ) as $trim ) {
			$candidates = array( Normalizer::text( $trim['name'] ) );
			foreach ( explode( ',', (string) $trim['aliases'] ) as $alias ) {
				$alias = Normalizer::text( $alias );
				if ( '' !== $alias ) {
					$candidates[] = $alias;
				}
			}
			foreach ( $candidates as $candidate ) {
				$score = Normalizer::score( $needle, $candidate );
				if ( $score > $best_score ) {
					$best_score = $score;
					$best       = $trim;
				}
			}
		}

		return ( $best_score >= 70 ) ? $best : null;
	}

	/**
	 * Display label "Peugeot 206" / "پژو 206".
	 *
	 * @param array $model Matched model row (with brand info).
	 *
	 * @return string
	 */
	public static function label( array $model ): string {
		$brand = $model['brand_name_en'] ?: $model['brand_name'];
		$name  = $model['name_en'] ?: $model['name'];
		return trim( $brand . ' ' . $name );
	}
}
