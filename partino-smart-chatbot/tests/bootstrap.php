<?php
/**
 * PHPUnit bootstrap: lightweight WordPress shims for unit tests.
 *
 * These tests cover pure logic (normalization, AI output validation,
 * settings sanitization) and do NOT require a WordPress install.
 * Integration tests should run against wp-env / wp-playground.
 *
 * @package Partino\Chatbot\Tests
 */

define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'PARTINO_CHATBOT_PATH', dirname( __DIR__ ) . '/' );
define( 'PARTINO_CHATBOT_VERSION', 'test' );

// ---------------------------------------------------------------------------
// Minimal WordPress function shims.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $str ) ) );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( (string) $email, FILTER_SANITIZE_EMAIL );
	}
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : null;
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( trim( (string) $title ) );
		return preg_replace( '/[^a-z0-9\-]+/', '-', $title );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $n ) {
		return abs( (int) $n );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $flags = 0 ) {
		return json_encode( $data, $flags );
	}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'phpunit-salt-' . $scheme;
	}
}

// Simple in-memory options store used by Settings in unit tests.
$GLOBALS['__partino_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $GLOBALS['__partino_test_options'][ $name ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['__partino_test_options'][ $name ] = $value;
		return true;
	}
}

require_once PARTINO_CHATBOT_PATH . 'includes/class-normalizer.php';
require_once PARTINO_CHATBOT_PATH . 'includes/class-ai.php';
require_once PARTINO_CHATBOT_PATH . 'includes/class-settings.php';
