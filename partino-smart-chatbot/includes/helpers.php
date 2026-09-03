<?php
/**
 * Global helper functions (intentionally few, all prefixed).
 *
 * @package Partino\Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'partino_chatbot' ) ) {
	/**
	 * Get the plugin main instance.
	 *
	 * @return \Partino\Chatbot\Plugin
	 */
	function partino_chatbot(): \Partino\Chatbot\Plugin {
		return \Partino\Chatbot\Plugin::instance();
	}
}

if ( ! function_exists( 'partino_chatbot_get_setting' ) ) {
	/**
	 * Get a single plugin setting.
	 *
	 * @param string $key     Setting key (dot notation: "general.enabled").
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	function partino_chatbot_get_setting( string $key, $default = null ) {
		return \Partino\Chatbot\Settings::get( $key, $default );
	}
}

if ( ! function_exists( 'partino_chatbot_mask_phone' ) ) {
	/**
	 * Mask a phone number for lists / logs (0912***4567).
	 *
	 * @param string $phone Phone number.
	 *
	 * @return string
	 */
	function partino_chatbot_mask_phone( string $phone ): string {
		$phone = preg_replace( '/[^0-9+]/', '', $phone );
		if ( strlen( $phone ) < 8 ) {
			return str_repeat( '*', max( strlen( $phone ), 4 ) );
		}
		return substr( $phone, 0, 4 ) . str_repeat( '*', max( strlen( $phone ) - 8, 3 ) ) . substr( $phone, -4 );
	}
}
