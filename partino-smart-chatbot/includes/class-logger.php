<?php
/**
 * Internal DB logger with sensitive-data redaction.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Logger writing to the partino_logs table.
 */
final class Logger {

	public const DEBUG   = 'debug';
	public const INFO    = 'info';
	public const WARNING = 'warning';
	public const ERROR   = 'error';

	/**
	 * Write a log entry.
	 *
	 * @param string $level   Level.
	 * @param string $channel Channel (ai, rest, db, security, general).
	 * @param string $message Message (no sensitive data!).
	 * @param array  $context Extra context (redacted before save).
	 */
	public static function log( string $level, string $channel, string $message, array $context = array() ): void {
		if ( self::DEBUG === $level && ! Settings::get( 'advanced.debug_log', false ) ) {
			return;
		}

		global $wpdb;

		$context = self::redact( $context );
		$message = self::redact_string( $message );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			Database::table( 'logs' ),
			array(
				'level'      => in_array( $level, array( self::DEBUG, self::INFO, self::WARNING, self::ERROR ), true ) ? $level : self::INFO,
				'channel'    => sanitize_key( $channel ),
				'message'    => mb_substr( $message, 0, 5000 ),
				'context'    => ! empty( $context ) ? wp_json_encode( $context, JSON_UNESCAPED_UNICODE ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Shorthand helpers.
	 *
	 * @param string $channel Channel.
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public static function debug( string $channel, string $message, array $context = array() ): void {
		self::log( self::DEBUG, $channel, $message, $context );
	}

	public static function info( string $channel, string $message, array $context = array() ): void {
		self::log( self::INFO, $channel, $message, $context );
	}

	public static function warning( string $channel, string $message, array $context = array() ): void {
		self::log( self::WARNING, $channel, $message, $context );
	}

	public static function error( string $channel, string $message, array $context = array() ): void {
		self::log( self::ERROR, $channel, $message, $context );
	}

	/**
	 * Redact sensitive keys from context arrays.
	 *
	 * @param array $context Context.
	 *
	 * @return array
	 */
	private static function redact( array $context ): array {
		$sensitive = array( 'api_key', 'apikey', 'authorization', 'phone', 'customer_phone', 'password', 'token', 'secret' );
		foreach ( $context as $key => $value ) {
			if ( is_array( $value ) ) {
				$context[ $key ] = self::redact( $value );
				continue;
			}
			if ( in_array( strtolower( (string) $key ), $sensitive, true ) ) {
				$context[ $key ] = '[redacted]';
			} elseif ( is_string( $value ) ) {
				$context[ $key ] = self::redact_string( $value );
			}
		}
		return $context;
	}

	/**
	 * Redact API keys / phone numbers accidentally present in strings.
	 *
	 * @param string $value Raw string.
	 *
	 * @return string
	 */
	private static function redact_string( string $value ): string {
		// OpenRouter style keys.
		$value = preg_replace( '/sk-or-[A-Za-z0-9\-_]{8,}/', 'sk-or-[redacted]', $value );
		$value = preg_replace( '/sk-[A-Za-z0-9\-_]{16,}/', 'sk-[redacted]', $value );
		// Iranian mobile numbers.
		$value = preg_replace( '/(?:\+?98|0)9\d{2}[\s\-]?\d{3}[\s\-]?\d{4}/', '[phone-redacted]', $value );
		return $value;
	}

	/**
	 * Clear all logs.
	 */
	public static function clear(): void {
		global $wpdb;
		$table = Database::table( 'logs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}
}
