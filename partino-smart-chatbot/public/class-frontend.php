<?php
/**
 * Frontend integration: assets + widget mount point.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues frontend assets and renders the widget root.
 */
final class Frontend {

	/**
	 * Hook registration.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_footer', array( $this, 'render_root' ), 99 );
	}

	/**
	 * Should the widget load on this request?
	 *
	 * @return bool
	 */
	private function should_load(): bool {
		if ( ! Settings::get( 'general.enabled', true ) ) {
			return false;
		}
		if ( is_admin() || is_feed() || is_embed() ) {
			return false;
		}
		if ( function_exists( 'is_login' ) && is_login() ) {
			return false;
		}
		$is_mobile = wp_is_mobile();
		if ( $is_mobile && ! Settings::get( 'general.show_mobile', true ) ) {
			return false;
		}
		if ( ! $is_mobile && ! Settings::get( 'general.show_desktop', true ) ) {
			return false;
		}

		/**
		 * Filter whether the chatbot loads on the current request.
		 *
		 * @param bool $load Load flag.
		 */
		return (bool) apply_filters( 'partino_chatbot_should_load', true );
	}

	/**
	 * Enqueue CSS/JS only when the widget will render.
	 */
	public function assets(): void {
		if ( ! $this->should_load() ) {
			return;
		}

		$css = PARTINO_CHATBOT_PATH . 'public/assets/css/chatbot.css';
		$js  = PARTINO_CHATBOT_PATH . 'public/assets/js/chatbot.js';

		wp_enqueue_style(
			'partino-chatbot',
			PARTINO_CHATBOT_URL . 'public/assets/css/chatbot.css',
			array(),
			file_exists( $css ) ? (string) filemtime( $css ) : PARTINO_CHATBOT_VERSION
		);

		wp_enqueue_script(
			'partino-chatbot',
			PARTINO_CHATBOT_URL . 'public/assets/js/chatbot.js',
			array(),
			file_exists( $js ) ? (string) filemtime( $js ) : PARTINO_CHATBOT_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'partino-chatbot',
			'PartinoChatbotData',
			array(
				'restUrl' => esc_url_raw( rest_url( PARTINO_CHATBOT_REST_NS ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'config'  => Front_Config::build(),
			)
		);
	}

	/**
	 * Render the widget mount node in the footer.
	 */
	public function render_root(): void {
		if ( ! $this->should_load() ) {
			return;
		}

		$template = PARTINO_CHATBOT_PATH . 'templates/chatbot.php';

		/**
		 * Filter the chatbot mount template path (theme overrides).
		 *
		 * @param string $template Absolute template path.
		 */
		$template = (string) apply_filters( 'partino_chatbot_template', $template );

		if ( is_readable( $template ) ) {
			require $template;
		}
	}
}
