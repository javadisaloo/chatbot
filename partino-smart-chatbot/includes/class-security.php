<?php
/**
 * Security: rate limiting, anti-spam blocking, IP hashing, phone validation.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Security utilities.
 */
final class Security {

	/**
	 * Get the visitor IP (best effort, no trust of spoofable headers beyond proxy basics).
	 *
	 * @return string
	 */
	public static function get_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Salted hash of the visitor IP (privacy-preserving identifier).
	 *
	 * @return string
	 */
	public static function ip_hash(): string {
		$mode = (string) Settings::get( 'privacy.mode', 'standard' );
		if ( 'minimal' === $mode ) {
			return '';
		}
		$ip = self::get_ip();
		if ( '' === $ip ) {
			return '';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	/**
	 * Rate limit key for the current client.
	 *
	 * @return string
	 */
	private static function client_key(): string {
		$ip = self::get_ip();
		return 'partino_rl_' . md5( $ip . '|' . wp_salt( 'nonce' ) );
	}

	/**
	 * Check and consume one rate-limit slot. Returns true when allowed.
	 *
	 * @return bool
	 */
	public static function check_rate_limit(): bool {
		$limit = (int) Settings::get( 'advanced.rate_limit_per_minute', 20 );
		$key   = self::client_key();

		// Blocked?
		if ( get_transient( $key . '_blk' ) ) {
			return false;
		}

		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			// Temporary block for repeat abusers.
			$block_minutes = (int) Settings::get( 'advanced.block_minutes', 10 );
			set_transient( $key . '_blk', 1, $block_minutes * MINUTE_IN_SECONDS );
			Logger::warning( 'security', 'Rate limit exceeded; client temporarily blocked.' );
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Validate + normalize an Iranian mobile number.
	 *
	 * Accepts: 09121234567, +989121234567, 989121234567, 9121234567,
	 * Persian/Arabic digits, spaces and dashes.
	 *
	 * @param string $raw Raw input.
	 *
	 * @return string Normalized 09xxxxxxxxx or empty string when invalid.
	 */
	public static function normalize_phone( string $raw ): string {
		$digits = Normalizer::digits( $raw );
		$digits = preg_replace( '/[\s\-\(\)\.]+/', '', $digits );

		if ( str_starts_with( $digits, '+98' ) ) {
			$digits = '0' . substr( $digits, 3 );
		} elseif ( str_starts_with( $digits, '0098' ) ) {
			$digits = '0' . substr( $digits, 4 );
		} elseif ( str_starts_with( $digits, '98' ) && 12 === strlen( $digits ) ) {
			$digits = '0' . substr( $digits, 2 );
		} elseif ( str_starts_with( $digits, '9' ) && 10 === strlen( $digits ) ) {
			$digits = '0' . $digits;
		}

		if ( preg_match( '/^09\d{9}$/', $digits ) ) {
			return $digits;
		}

		return '';
	}

	/**
	 * Detect device type from the user agent.
	 *
	 * @return string mobile|tablet|desktop
	 */
	public static function device_type(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		if ( '' === $ua ) {
			return '';
		}
		if ( str_contains( $ua, 'ipad' ) || str_contains( $ua, 'tablet' ) ) {
			return 'tablet';
		}
		if ( str_contains( $ua, 'mobi' ) || str_contains( $ua, 'android' ) || str_contains( $ua, 'iphone' ) ) {
			return 'mobile';
		}
		return 'desktop';
	}

	/**
	 * Truncated, sanitized user agent respecting privacy mode.
	 *
	 * @return string
	 */
	public static function user_agent(): string {
		$mode = (string) Settings::get( 'privacy.mode', 'standard' );
		if ( 'minimal' === $mode ) {
			return '';
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return mb_substr( $ua, 0, 255 );
	}

	/**
	 * Referrer respecting privacy mode ("extended" only).
	 *
	 * @return string
	 */
	public static function referrer(): string {
		$mode = (string) Settings::get( 'privacy.mode', 'standard' );
		if ( 'extended' !== $mode ) {
			return '';
		}
		return esc_url_raw( wp_get_referer() ?: '' );
	}
}
