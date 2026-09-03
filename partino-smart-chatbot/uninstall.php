<?php
/**
 * Uninstall handler.
 *
 * Data is ONLY removed when the admin explicitly enabled
 * "Delete data on uninstall" in the plugin settings (default: OFF).
 *
 * @package Partino\Chatbot
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$partino_settings = get_option( 'partino_chatbot_settings', array() );

$partino_delete = ! empty( $partino_settings['advanced']['delete_data_on_uninstall'] );

if ( ! $partino_delete ) {
	return;
}

global $wpdb;

$partino_tables = array(
	'partino_inquiries',
	'partino_conversations',
	'partino_messages',
	'partino_vehicles',
	'partino_parts',
	'partino_quick_actions',
	'partino_logs',
);

foreach ( $partino_tables as $partino_table ) {
	$partino_full = $wpdb->prefix . $partino_table;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table list.
	$wpdb->query( "DROP TABLE IF EXISTS {$partino_full}" );
}

delete_option( 'partino_chatbot_settings' );
delete_option( 'partino_chatbot_db_version' );
delete_option( 'partino_chatbot_ai_requests' );
delete_option( 'partino_chatbot_ai_errors' );

// Remove capability from roles.
$partino_role = get_role( 'administrator' );
if ( $partino_role ) {
	$partino_role->remove_cap( 'manage_partino_chatbot' );
}

// Clear scheduled cron.
wp_clear_scheduled_hook( 'partino_chatbot_daily_cleanup' );

// Rate-limit transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_partino_rl_%' OR option_name LIKE '_transient_timeout_partino_rl_%'" );
