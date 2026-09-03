<?php
/**
 * Conversation state constants.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Finite states of the inquiry conversation.
 */
final class States {

	public const INITIAL         = 'INITIAL';
	public const PART_REQUEST    = 'PART_REQUEST';
	public const PART_SELECTED   = 'PART_SELECTED';
	public const VEHICLE_REQUEST = 'VEHICLE_REQUEST';
	public const VEHICLE_BRAND   = 'VEHICLE_BRAND';
	public const VEHICLE_MODEL   = 'VEHICLE_MODEL';
	public const VEHICLE_TRIM    = 'VEHICLE_TRIM';
	public const VEHICLE_YEAR    = 'VEHICLE_YEAR';
	public const CONFIRMATION    = 'CONFIRMATION';
	public const PHONE_REQUEST   = 'PHONE_REQUEST';
	public const SUBMITTING      = 'SUBMITTING';
	public const SUBMITTED       = 'SUBMITTED';
	public const ERROR           = 'ERROR';

	/**
	 * All valid states.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::INITIAL,
			self::PART_REQUEST,
			self::PART_SELECTED,
			self::VEHICLE_REQUEST,
			self::VEHICLE_BRAND,
			self::VEHICLE_MODEL,
			self::VEHICLE_TRIM,
			self::VEHICLE_YEAR,
			self::CONFIRMATION,
			self::PHONE_REQUEST,
			self::SUBMITTING,
			self::SUBMITTED,
			self::ERROR,
		);
	}
}
