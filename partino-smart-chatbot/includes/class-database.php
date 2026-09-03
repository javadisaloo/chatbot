<?php
/**
 * Database schema, versioning and migrations.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades custom tables via dbDelta().
 */
final class Database {

	public const DB_VERSION_OPTION = 'partino_chatbot_db_version';

	/**
	 * Table basenames (without prefix).
	 *
	 * @var string[]
	 */
	public const TABLES = array(
		'inquiries'     => 'partino_inquiries',
		'conversations' => 'partino_conversations',
		'messages'      => 'partino_messages',
		'vehicles'      => 'partino_vehicles',
		'parts'         => 'partino_parts',
		'quick_actions' => 'partino_quick_actions',
		'logs'          => 'partino_logs',
	);

	/**
	 * Fully-prefixed table name.
	 *
	 * @param string $key Table key.
	 *
	 * @return string
	 */
	public static function table( string $key ): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLES[ $key ];
	}

	/**
	 * Install / upgrade schema when the stored DB version is stale.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_VERSION_OPTION ) !== PARTINO_CHATBOT_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create or update all tables.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$schema = array();

		$schema[] = "CREATE TABLE {$p}partino_inquiries (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			conversation_id BIGINT(20) UNSIGNED NULL,
			customer_phone VARCHAR(20) NOT NULL DEFAULT '',
			customer_name VARCHAR(190) NOT NULL DEFAULT '',
			part_id BIGINT(20) UNSIGNED NULL,
			part_name VARCHAR(190) NOT NULL DEFAULT '',
			part_category VARCHAR(190) NOT NULL DEFAULT '',
			vehicle_brand VARCHAR(190) NOT NULL DEFAULT '',
			vehicle_model VARCHAR(190) NOT NULL DEFAULT '',
			vehicle_trim VARCHAR(190) NOT NULL DEFAULT '',
			vehicle_year VARCHAR(10) NOT NULL DEFAULT '',
			status VARCHAR(30) NOT NULL DEFAULT 'new',
			source VARCHAR(30) NOT NULL DEFAULT 'website',
			page_url TEXT NULL,
			referrer TEXT NULL,
			device_type VARCHAR(20) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY status (status),
			KEY customer_phone (customer_phone),
			KEY conversation_id (conversation_id),
			KEY part_id (part_id),
			KEY vehicle_model (vehicle_model),
			KEY created_at (created_at)
		) $charset;";

		$schema[] = "CREATE TABLE {$p}partino_conversations (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			state VARCHAR(40) NOT NULL DEFAULT 'INITIAL',
			context LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			page_url TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY status (status),
			KEY updated_at (updated_at)
		) $charset;";

		$schema[] = "CREATE TABLE {$p}partino_messages (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id BIGINT(20) UNSIGNED NOT NULL,
			role VARCHAR(12) NOT NULL DEFAULT 'user',
			content TEXT NOT NULL,
			meta TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) $charset;";

		$schema[] = "CREATE TABLE {$p}partino_vehicles (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			parent_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			type VARCHAR(10) NOT NULL DEFAULT 'brand',
			name VARCHAR(190) NOT NULL,
			name_en VARCHAR(190) NOT NULL DEFAULT '',
			aliases TEXT NULL,
			year_start SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			year_end SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY parent_id (parent_id),
			KEY type (type),
			KEY is_active (is_active)
		) $charset;";

		$schema[] = "CREATE TABLE {$p}partino_parts (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			slug VARCHAR(190) NOT NULL,
			category VARCHAR(190) NOT NULL DEFAULT '',
			icon VARCHAR(60) NOT NULL DEFAULT '',
			aliases TEXT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY is_active (is_active)
		) $charset;";

		$schema[] = "CREATE TABLE {$p}partino_quick_actions (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(190) NOT NULL,
			icon VARCHAR(60) NOT NULL DEFAULT '',
			part_id BIGINT(20) UNSIGNED NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY is_active (is_active)
		) $charset;";

		$schema[] = "CREATE TABLE {$p}partino_logs (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			level VARCHAR(10) NOT NULL DEFAULT 'info',
			channel VARCHAR(40) NOT NULL DEFAULT 'general',
			message TEXT NOT NULL,
			context TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY level (level),
			KEY channel (channel),
			KEY created_at (created_at)
		) $charset;";

		foreach ( $schema as $sql ) {
			dbDelta( $sql );
		}

		self::seed();

		update_option( self::DB_VERSION_OPTION, PARTINO_CHATBOT_DB_VERSION, false );
	}

	/**
	 * Seed default vehicles / parts / quick actions on first install only.
	 */
	private static function seed(): void {
		global $wpdb;

		$vehicles_table = self::table( 'vehicles' );
		$parts_table    = self::table( 'parts' );
		$qa_table       = self::table( 'quick_actions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema install.
		$has_vehicles = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$vehicles_table}" );

		if ( 0 === $has_vehicles ) {
			$now = current_time( 'mysql' );
			unset( $now ); // Not needed for catalog rows.

			$brands = array(
				array( 'پژو', 'Peugeot', 'پژو,پيژو,peugeot,pegout,پژوو' ),
				array( 'سمند', 'Samand', 'سمند,samand' ),
				array( 'پراید', 'Pride', 'پراید,پرايد,pride,saipa 111,saipa 131' ),
				array( 'تیبا', 'Tiba', 'تیبا,تيبا,tiba' ),
				array( 'کوییک', 'Quick', 'کوییک,کوئیک,quick,quik' ),
				array( 'دنا', 'Dena', 'دنا,dena' ),
				array( 'رانا', 'Runna', 'رانا,runna,rana' ),
				array( 'تویوتا', 'Toyota', 'تویوتا,تويوتا,toyota' ),
				array( 'هیوندای', 'Hyundai', 'هیوندای,هیوندا,هیوندایی,hyundai,hundai' ),
				array( 'کیا', 'Kia', 'کیا,kia' ),
				array( 'رنو', 'Renault', 'رنو,renault' ),
				array( 'نیسان', 'Nissan', 'نیسان,nissan' ),
			);

			$brand_ids = array();
			foreach ( $brands as $i => $b ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$vehicles_table,
					array(
						'parent_id'  => 0,
						'type'       => 'brand',
						'name'       => $b[0],
						'name_en'    => $b[1],
						'aliases'    => $b[2],
						'is_active'  => 1,
						'sort_order' => $i,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
				);
				$brand_ids[ $b[1] ] = (int) $wpdb->insert_id;
			}

			$models = array(
				// brand_en, name, name_en, aliases, year_start, year_end.
				array( 'Peugeot', '206', '206', '206,دویست و شش,دويست و شش,۲۰۶,pegout 206', 1380, 1402 ),
				array( 'Peugeot', '207', '207', '207,دویست و هفت,۲۰۷', 1388, 1404 ),
				array( 'Peugeot', '405', '405', '405,چهارصد و پنج,۴۰۵', 1372, 1399 ),
				array( 'Peugeot', 'پارس', 'Pars', 'پارس,pars,پرشیا,پرشيا,persia', 1379, 1403 ),
				array( 'Samand', 'LX', 'LX', 'ال ایکس,ال‌ایکس,lx', 1381, 1402 ),
				array( 'Samand', 'سورن', 'Soren', 'سورن,soren', 1386, 1404 ),
				array( 'Pride', '131', '131', '131,صد و سی و یک', 1370, 1399 ),
				array( 'Pride', '111', '111', '111,صد و یازده', 1389, 1399 ),
				array( 'Tiba', 'تیبا 2', 'Tiba 2', 'تیبا 2,تیبا۲,tiba 2', 1393, 1401 ),
				array( 'Quick', 'کوییک R', 'Quick R', 'کوییک آر,quick r', 1397, 1404 ),
				array( 'Dena', 'دنا پلاس', 'Dena Plus', 'دنا پلاس,dena plus,دنا+', 1394, 1404 ),
				array( 'Runna', 'رانا پلاس', 'Runna Plus', 'رانا پلاس,runna plus', 1392, 1404 ),
				array( 'Renault', 'ال 90', 'L90', 'ال نود,l90,tondar,تندر,تندر 90', 1386, 1397 ),
			);

			$model_ids = array();
			foreach ( $models as $i => $m ) {
				$parent = $brand_ids[ $m[0] ] ?? 0;
				if ( ! $parent ) {
					continue;
				}
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$vehicles_table,
					array(
						'parent_id'  => $parent,
						'type'       => 'model',
						'name'       => $m[1],
						'name_en'    => $m[2],
						'aliases'    => $m[3],
						'year_start' => $m[4],
						'year_end'   => $m[5],
						'is_active'  => 1,
						'sort_order' => $i,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
				);
				$model_ids[ $m[0] . '/' . $m[2] ] = (int) $wpdb->insert_id;
			}

			$trims = array(
				// model key, trim name, aliases.
				array( 'Peugeot/206', 'تیپ 1', 'تیپ 1,تیپ۱,type 1,تيپ 1,tip 1' ),
				array( 'Peugeot/206', 'تیپ 2', 'تیپ 2,تیپ۲,type 2,تيپ 2,tip 2' ),
				array( 'Peugeot/206', 'تیپ 3', 'تیپ 3,تیپ۳,type 3,tip 3' ),
				array( 'Peugeot/206', 'تیپ 3 پانوراما', 'تیپ 3 پانوراما,پانوراما,panorama' ),
				array( 'Peugeot/206', 'تیپ 4', 'تیپ 4,تیپ۴,type 4,tip 4' ),
				array( 'Peugeot/206', 'تیپ 5', 'تیپ 5,تیپ۵,type 5,tip 5' ),
				array( 'Peugeot/206', 'تیپ 6', 'تیپ 6,تیپ۶,type 6,tip 6' ),
				array( 'Peugeot/206', 'SD V8', 'اس دی,sd,v8,صندوقدار' ),
				array( 'Peugeot/Pars', 'ساده', 'ساده,معمولی' ),
				array( 'Peugeot/Pars', 'ELX', 'ای ال ایکس,elx' ),
				array( 'Peugeot/Pars', 'TU5', 'تی یو فایو,tu5' ),
				array( 'Peugeot/207', 'دنده‌ای', 'دنده ای,دنده‌ای,manual' ),
				array( 'Peugeot/207', 'اتوماتیک', 'اتومات,اتوماتیک,automatic' ),
				array( 'Peugeot/207', 'پانوراما', 'پانوراما,panorama' ),
				array( 'Dena/Dena Plus', 'توربو', 'توربو,turbo' ),
				array( 'Dena/Dena Plus', 'اتوماتیک توربو', 'اتومات توربو' ),
				array( 'Dena/Dena Plus', 'ساده', 'ساده' ),
			);

			foreach ( $trims as $i => $t ) {
				$parent = $model_ids[ $t[0] ] ?? 0;
				if ( ! $parent ) {
					continue;
				}
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$vehicles_table,
					array(
						'parent_id'  => $parent,
						'type'       => 'trim',
						'name'       => $t[1],
						'name_en'    => '',
						'aliases'    => $t[2],
						'is_active'  => 1,
						'sort_order' => $i,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
				);
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$has_parts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$parts_table}" );

		if ( 0 === $has_parts ) {
			$parts = array(
				array( 'لنت ترمز', 'brake-pad', 'ترمز', 'لنت,لنت ترمز,brake pad,brake pads,لنت جلو,لنت عقب' ),
				array( 'فیلتر روغن', 'oil-filter', 'موتور', 'فیلتر روغن,فيلتر روغن,oil filter' ),
				array( 'روغن موتور', 'engine-oil', 'موتور', 'روغن,روغن موتور,engine oil,oil' ),
				array( 'فیلتر هوا', 'air-filter', 'موتور', 'فیلتر هوا,air filter' ),
				array( 'فیلتر کابین', 'cabin-filter', 'تهویه', 'فیلتر کابین,فیلتر اتاق,cabin filter' ),
				array( 'شمع موتور', 'spark-plug', 'موتور', 'شمع,شمع موتور,spark plug' ),
				array( 'دیسک ترمز', 'brake-disc', 'ترمز', 'دیسک,دیسک ترمز,brake disc' ),
				array( 'تسمه تایم', 'timing-belt', 'موتور', 'تسمه تایم,تسمه تايم,timing belt' ),
				array( 'کمک فنر', 'shock-absorber', 'جلوبندی', 'کمک,کمک فنر,shock absorber' ),
				array( 'باتری', 'battery', 'برقی', 'باتری,باطری,battery' ),
			);
			foreach ( $parts as $i => $pt ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$parts_table,
					array(
						'name'       => $pt[0],
						'slug'       => $pt[1],
						'category'   => $pt[2],
						'icon'       => '',
						'aliases'    => $pt[3],
						'is_active'  => 1,
						'sort_order' => $i,
					),
					array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
				);
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$has_qa = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$qa_table}" );

		if ( 0 === $has_qa ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$brake  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$parts_table} WHERE slug = %s", 'brake-pad' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$oil    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$parts_table} WHERE slug = %s", 'oil-filter' ) );

			$actions = array(
				array( 'لنت ترمز خودروی من', $brake ?: null, 0 ),
				array( 'روغن و فیلتر روغن', $oil ?: null, 1 ),
			);
			foreach ( $actions as $a ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$qa_table,
					array(
						'title'      => $a[0],
						'icon'       => '',
						'part_id'    => $a[1],
						'is_active'  => 1,
						'sort_order' => $a[2],
					),
					array( '%s', '%s', '%d', '%d', '%d' )
				);
			}
		}
	}

	/**
	 * Drop all plugin tables (uninstall only).
	 */
	public static function drop_tables(): void {
		global $wpdb;
		foreach ( self::TABLES as $basename ) {
			$table = $wpdb->prefix . $basename;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from constant list.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
		delete_option( self::DB_VERSION_OPTION );
	}
}
