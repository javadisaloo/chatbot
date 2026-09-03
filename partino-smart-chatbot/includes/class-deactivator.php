<?php
/**
 * Plugin deactivation logic.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin deactivation. Data is preserved.
 */
final class Deactivator {

	/**
	 * Deactivate: unschedule cron, flush rewrites. No data loss.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'partino_chatbot_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'partino_chatbot_daily_cleanup' );
		}
		flush_rewrite_rules();
	}
}
