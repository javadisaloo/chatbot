<?php
/**
 * Plugin Name:       Partino Smart Parts Chatbot
 * Plugin URI:        https://github.com/javadisaloo/chatbot
 * Description:       چت‌بات هوشمند استعلام قطعات خودرو با پشتیبانی از هوش مصنوعی (OpenRouter)، جریان مکالمه State-Based، پنل مدیریت کامل و امنیت استاندارد وردپرس.
 * Version:           1.0.0
 * Author:            Partino
 * Author URI:        https://partino.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       partino-smart-chatbot
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.1
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Constants.
// ---------------------------------------------------------------------------
define( 'PARTINO_CHATBOT_VERSION', '1.0.0' );
define( 'PARTINO_CHATBOT_DB_VERSION', '1.0.0' );
define( 'PARTINO_CHATBOT_FILE', __FILE__ );
define( 'PARTINO_CHATBOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'PARTINO_CHATBOT_URL', plugin_dir_url( __FILE__ ) );
define( 'PARTINO_CHATBOT_BASENAME', plugin_basename( __FILE__ ) );
define( 'PARTINO_CHATBOT_REST_NS', 'partino-chatbot/v1' );
define( 'PARTINO_CHATBOT_CAP', 'manage_partino_chatbot' );
define( 'PARTINO_CHATBOT_MIN_PHP', '8.1' );

// ---------------------------------------------------------------------------
// PHP version guard (never fatal on unsupported PHP).
// ---------------------------------------------------------------------------
if ( version_compare( PHP_VERSION, PARTINO_CHATBOT_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					__( 'افزونه Partino Smart Parts Chatbot به PHP نسخه %1$s یا بالاتر نیاز دارد. نسخه فعلی: %2$s', 'partino-smart-chatbot' ),
					PARTINO_CHATBOT_MIN_PHP,
					PHP_VERSION
				)
			);
			echo '</p></div>';
		}
	);
	return;
}

// ---------------------------------------------------------------------------
// Autoloader.
// ---------------------------------------------------------------------------
require_once PARTINO_CHATBOT_PATH . 'includes/class-autoloader.php';
Partino\Chatbot\Autoloader::register();

require_once PARTINO_CHATBOT_PATH . 'includes/helpers.php';

// ---------------------------------------------------------------------------
// Activation / Deactivation.
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, array( 'Partino\\Chatbot\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Partino\\Chatbot\\Deactivator', 'deactivate' ) );

// ---------------------------------------------------------------------------
// Boot.
// ---------------------------------------------------------------------------
add_action(
	'plugins_loaded',
	static function () {
		Partino\Chatbot\Plugin::instance()->boot();
	},
	10
);
