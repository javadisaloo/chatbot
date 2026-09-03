<?php
/**
 * Main plugin orchestrator (singleton).
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Boots all modules.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Booted flag.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Get instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Boot the plugin (called on plugins_loaded).
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'partino-smart-chatbot', false, dirname( PARTINO_CHATBOT_BASENAME ) . '/languages' );

		// DB migration on upgrade.
		Database::maybe_upgrade();

		// REST API.
		( new Rest_Api() )->register();

		// Frontend.
		( new Frontend() )->register();

		// Admin.
		if ( is_admin() ) {
			( new Admin() )->register();
		}

		// Cron cleanup.
		add_action( 'partino_chatbot_daily_cleanup', array( Conversation::class, 'cleanup' ) );
	}
}
