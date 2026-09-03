<?php
/**
 * Plugin activation logic.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation.
 */
final class Activator {

	/**
	 * Activate: create tables, capability, default option, cron.
	 */
	public static function activate(): void {
		Database::install();

		// Grant the custom capability to administrators.
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( PARTINO_CHATBOT_CAP ) ) {
			$role->add_cap( PARTINO_CHATBOT_CAP );
		}

		// Ensure the settings option exists.
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', false );
		}

		// Daily cleanup cron (retention).
		if ( ! wp_next_scheduled( 'partino_chatbot_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'partino_chatbot_daily_cleanup' );
		}

		flush_rewrite_rules();
	}
}
