<?php
/**
 * Persian text / digit normalization for smart matching.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Text normalization helpers (rule-based NLU foundation).
 */
final class Normalizer {

	/**
	 * Convert Persian/Arabic digits to Latin.
	 *
	 * @param string $text Input.
	 *
	 * @return string
	 */
	public static function digits( string $text ): string {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $ar, $en, str_replace( $fa, $en, $text ) );
	}

	/**
	 * Normalize text for matching: digits, arabic letters, spacing, lowercase.
	 *
	 * @param string $text Input.
	 *
	 * @return string
	 */
	public static function text( string $text ): string {
		$text = self::digits( $text );
		// Arabic → Persian letters.
		$text = str_replace( array( 'ي', 'ك', 'ٔ', 'ة' ), array( 'ی', 'ک', '', 'ه' ), $text );
		// ZWNJ → space.
		$text = str_replace( "\u{200C}", ' ', $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * Persian number-words → digits (a pragmatic subset for model names).
	 *
	 * @param string $text Normalized text.
	 *
	 * @return string
	 */
	public static function number_words( string $text ): string {
		$map = array(
			'دویست و شش'    => '206',
			'دویست و هفت'   => '207',
			'چهارصد و پنج'  => '405',
			'صد و سی و یک'  => '131',
			'صد و یازده'    => '111',
			'ال نود'        => 'l90',
		);
		return str_replace( array_keys( $map ), array_values( $map ), $text );
	}

	/**
	 * Extract a Jalali or Gregorian vehicle year from free text.
	 *
	 * @param string $text Raw input.
	 *
	 * @return string Empty when not found / invalid.
	 */
	public static function extract_year( string $text ): string {
		$text = self::digits( $text );
		if ( preg_match( '/\b(13[5-9]\d|140[0-9])\b/', $text, $m ) ) {
			return $m[1]; // Jalali 1350–1409.
		}
		if ( preg_match( '/\b(19[7-9]\d|20[0-3]\d)\b/', $text, $m ) ) {
			return $m[1]; // Gregorian 1970–2039.
		}
		if ( preg_match( '/^\s*([5-9]\d)\s*$/', $text, $m ) ) {
			return '13' . $m[1]; // "88" → 1388.
		}
		return '';
	}

	/**
	 * Similarity score between a needle and a haystack term (0..100).
	 *
	 * @param string $needle   User input (normalized).
	 * @param string $haystack Candidate term (normalized).
	 *
	 * @return int
	 */
	public static function score( string $needle, string $haystack ): int {
		if ( '' === $needle || '' === $haystack ) {
			return 0;
		}
		if ( $needle === $haystack ) {
			return 100;
		}
		if ( str_contains( $haystack, $needle ) || str_contains( $needle, $haystack ) ) {
			return 85;
		}
		similar_text( $needle, $haystack, $percent );
		return (int) round( $percent );
	}
}
