<?php
/**
 * OpenRouter AI integration (NLU only — business logic stays in PHP).
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * OpenRouter client returning validated, structured intents.
 */
final class AI {

	private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * Valid intents the backend accepts from the model.
	 */
	private const VALID_INTENTS = array(
		'part_request',
		'vehicle_model',
		'vehicle_trim',
		'vehicle_year',
		'confirm',
		'deny',
		'edit_vehicle',
		'edit_year',
		'edit_part',
		'phone',
		'greeting',
		'ask_clarification',
		'unknown',
	);

	/**
	 * Whether AI is usable right now.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return (bool) Settings::get( 'ai.enabled', false ) && '' !== (string) Settings::get( 'ai.api_key', '' );
	}

	/**
	 * Default system prompt.
	 *
	 * @return string
	 */
	public static function default_system_prompt(): string {
		return <<<'PROMPT'
You are an automotive parts assistant NLU engine for an Iranian car-parts marketplace. You understand Persian (Farsi), colloquial Persian, Finglish and English.

Your ONLY job is to analyze the user's latest message in the context of the current conversation state and return a single minified JSON object. Never return prose, markdown or explanations.

JSON schema:
{"intent":"","part":null,"vehicle_brand":null,"vehicle_model":null,"vehicle_trim":null,"vehicle_year":null,"confidence":0,"requires_clarification":false,"clarification_type":null}

Rules:
- intent must be one of: part_request, vehicle_model, vehicle_trim, vehicle_year, confirm, deny, edit_vehicle, edit_year, edit_part, phone, greeting, ask_clarification, unknown
- Normalize vehicles: "206" / "پژو 206" / "Peugeot 206" / "دویست و شش" → vehicle_brand:"Peugeot", vehicle_model:"206"
- Normalize trims: "تیپ ۲" / "تیپ 2" / "type 2" → vehicle_trim:"تیپ 2"
- Years: Jalali (1388) or Gregorian (2009). Two digits like "88" mean Jalali "1388". Return as string in vehicle_year.
- Part names: return the Persian generic name (e.g. "لنت ترمز", "فیلتر روغن").
- confidence is 0..1. If you are not sure, NEVER guess: set intent "ask_clarification", requires_clarification true, and clarification_type to one of: part, vehicle, trim, year.
- Confirmation words (بله، آره، اوکی، تایید، درسته، yes) → intent "confirm". Negation (نه، خیر، غلطه، no) → intent "deny".
- If the user wants to change something already provided, use edit_vehicle / edit_year / edit_part.
- Output raw JSON only. No code fences.
PROMPT;
	}

	/**
	 * Analyze user text via OpenRouter. Returns validated structured array or null on failure.
	 *
	 * @param string $text  User message.
	 * @param string $state Current conversation state.
	 * @param array  $context Current context (part/vehicle already known).
	 *
	 * @return array|null
	 */
	public static function analyze( string $text, string $state, array $context = array() ): ?array {
		if ( ! self::is_enabled() ) {
			return null;
		}

		$system = (string) Settings::get( 'ai.system_prompt', '' );
		if ( '' === trim( $system ) ) {
			$system = self::default_system_prompt();
		}

		$state_info = wp_json_encode(
			array(
				'current_state' => $state,
				'known_context' => array(
					'part'          => $context['part_name'] ?? null,
					'vehicle_brand' => $context['vehicle_brand'] ?? null,
					'vehicle_model' => $context['vehicle_model'] ?? null,
					'vehicle_trim'  => $context['vehicle_trim'] ?? null,
					'vehicle_year'  => $context['vehicle_year'] ?? null,
				),
			),
			JSON_UNESCAPED_UNICODE
		);

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system,
			),
			array(
				'role'    => 'user',
				'content' => "STATE: {$state_info}\nUSER MESSAGE: {$text}",
			),
		);

		/**
		 * Filter the AI request payload before sending.
		 *
		 * @param array $messages Chat messages.
		 * @param string $state   Conversation state.
		 */
		$messages = (array) apply_filters( 'partino_before_ai_request', $messages, $state );

		$body = array(
			'model'       => (string) Settings::get( 'ai.model', 'openai/gpt-4o-mini' ),
			'messages'    => $messages,
			'temperature' => (float) Settings::get( 'ai.temperature', 0.2 ),
			'max_tokens'  => (int) Settings::get( 'ai.max_tokens', 500 ),
		);

		$retries = max( 0, (int) Settings::get( 'ai.retry', 1 ) );
		$result  = null;

		for ( $attempt = 0; $attempt <= $retries; $attempt++ ) {
			$result = self::request( $body );
			if ( null !== $result ) {
				break;
			}
		}

		if ( null === $result ) {
			return null;
		}

		$parsed = self::validate_output( $result );

		/**
		 * Filter the validated AI response.
		 *
		 * @param array|null $parsed Structured intent.
		 * @param string     $text   Original user text.
		 */
		return apply_filters( 'partino_after_ai_response', $parsed, $text );
	}

	/**
	 * Perform one HTTP request to OpenRouter. Returns raw content string or null.
	 *
	 * @param array $body Request payload.
	 *
	 * @return string|null
	 */
	private static function request( array $body ): ?string {
		$api_key = (string) Settings::get( 'ai.api_key', '' );
		$timeout = max( 3, (int) Settings::get( 'ai.timeout', 15 ) );

		self::bump_counter( 'partino_chatbot_ai_requests' );

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url( '/' ),
					'X-Title'       => 'Partino Smart Parts Chatbot',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::bump_counter( 'partino_chatbot_ai_errors' );
			Logger::error( 'ai', 'OpenRouter request failed: ' . $response->get_error_message() );
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			self::bump_counter( 'partino_chatbot_ai_errors' );
			Logger::error( 'ai', 'OpenRouter HTTP error.', array( 'status' => $code ) );
			return null;
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $json ) || empty( $json['choices'][0]['message']['content'] ) ) {
			self::bump_counter( 'partino_chatbot_ai_errors' );
			Logger::error( 'ai', 'OpenRouter returned an unexpected response shape.' );
			return null;
		}

		return (string) $json['choices'][0]['message']['content'];
	}

	/**
	 * Validate model output into the strict schema. Null when unusable.
	 *
	 * @param string $content Raw model text.
	 *
	 * @return array|null
	 */
	public static function validate_output( string $content ): ?array {
		$content = trim( $content );
		// Strip accidental code fences.
		$content = preg_replace( '/^```(?:json)?|```$/m', '', $content );
		$content = trim( (string) $content );

		// Extract first JSON object if wrapped in text.
		if ( '' !== $content && '{' !== $content[0] ) {
			$start = strpos( $content, '{' );
			$end   = strrpos( $content, '}' );
			if ( false === $start || false === $end || $end <= $start ) {
				return null;
			}
			$content = substr( $content, $start, $end - $start + 1 );
		}

		$data = json_decode( $content, true );
		if ( ! is_array( $data ) || empty( $data['intent'] ) || ! is_string( $data['intent'] ) ) {
			return null;
		}

		$intent = sanitize_key( $data['intent'] );
		if ( ! in_array( $intent, self::VALID_INTENTS, true ) ) {
			return null;
		}

		$str = static function ( $v ): ?string {
			if ( null === $v || '' === $v ) {
				return null;
			}
			return is_scalar( $v ) ? sanitize_text_field( (string) $v ) : null;
		};

		return array(
			'intent'                 => $intent,
			'part'                   => $str( $data['part'] ?? null ),
			'vehicle_brand'          => $str( $data['vehicle_brand'] ?? null ),
			'vehicle_model'          => $str( $data['vehicle_model'] ?? null ),
			'vehicle_trim'           => $str( $data['vehicle_trim'] ?? null ),
			'vehicle_year'           => $str( $data['vehicle_year'] ?? null ),
			'confidence'             => max( 0.0, min( 1.0, (float) ( $data['confidence'] ?? 0 ) ) ),
			'requires_clarification' => ! empty( $data['requires_clarification'] ),
			'clarification_type'     => $str( $data['clarification_type'] ?? null ),
		);
	}

	/**
	 * Test connection to OpenRouter with the stored key.
	 *
	 * @return array{success: bool, message: string}
	 */
	public static function test_connection(): array {
		$api_key = (string) Settings::get( 'ai.api_key', '' );
		if ( '' === $api_key ) {
			return array(
				'success' => false,
				'message' => __( 'کلید API تنظیم نشده است.', 'partino-smart-chatbot' ),
			);
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => max( 3, (int) Settings::get( 'ai.timeout', 15 ) ),
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url( '/' ),
					'X-Title'       => 'Partino Smart Parts Chatbot',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => (string) Settings::get( 'ai.model', 'openai/gpt-4o-mini' ),
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => 'Reply with exactly: OK',
							),
						),
						'max_tokens' => 10,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s error message */
					__( 'خطا در اتصال: %s', 'partino-smart-chatbot' ),
					$response->get_error_message()
				),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code ) {
			return array(
				'success' => false,
				'message' => __( 'کلید API نامعتبر است (401).', 'partino-smart-chatbot' ),
			);
		}
		if ( $code < 200 || $code >= 300 ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %d HTTP status code */
					__( 'پاسخ غیرمنتظره از OpenRouter (کد %d).', 'partino-smart-chatbot' ),
					$code
				),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'اتصال با موفقیت برقرار شد ✅', 'partino-smart-chatbot' ),
		);
	}

	/**
	 * Increment a counter option.
	 *
	 * @param string $option Option name.
	 */
	private static function bump_counter( string $option ): void {
		update_option( $option, (int) get_option( $option, 0 ) + 1, false );
	}
}
