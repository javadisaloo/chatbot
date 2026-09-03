<?php
/**
 * Class autoloader for the Partino\Chatbot namespace.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Maps Partino\Chatbot\* class names to files inside includes/ and admin/.
 */
final class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload a class.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function autoload( string $class ): void {
		if ( 0 !== strpos( $class, 'Partino\\Chatbot\\' ) ) {
			return;
		}

		$relative = substr( $class, strlen( 'Partino\\Chatbot\\' ) );
		$relative = strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative ) );
		$file     = 'class-' . basename( $relative ) . '.php';
		$subdir   = trim( dirname( $relative ), '.' );

		$candidates = array();
		if ( '' !== $subdir && '.' !== $subdir ) {
			$candidates[] = PARTINO_CHATBOT_PATH . $subdir . '/' . $file;
		}
		$candidates[] = PARTINO_CHATBOT_PATH . 'includes/' . $file;
		$candidates[] = PARTINO_CHATBOT_PATH . 'admin/' . $file;
		$candidates[] = PARTINO_CHATBOT_PATH . 'public/' . $file;

		foreach ( $candidates as $path ) {
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
