<?php
/**
 * Conversation engine — the server-side state machine.
 *
 * AI (when enabled) only supplies structured NLU hints; every transition and
 * every write is decided and validated here in PHP.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Processes user input against the current state and returns bot replies.
 */
final class Engine {

	/**
	 * Handle a free-text user message.
	 *
	 * @param array  $conversation Conversation row.
	 * @param string $text         Sanitized user text.
	 *
	 * @return array Response payload (messages/options/state/...).
	 */
	public static function handle_text( array $conversation, string $text ): array {
		$state   = (string) $conversation['state'];
		$context = Conversation::context( $conversation );
		$id      = (int) $conversation['id'];

		Conversation::add_message( $id, 'user', $text );

		// AI first (hint only), rule-based fallback always available.
		$intent = null;
		if ( AI::is_enabled() ) {
			$intent = AI::analyze( $text, $state, $context );
		}
		if ( null === $intent ) {
			$intent = self::rule_based_intent( $text, $state );
		}

		return self::transition( $id, $state, $context, $intent, $text );
	}

	/**
	 * Handle an option (button) selection. Value comes from options WE issued.
	 *
	 * @param array  $conversation Conversation row.
	 * @param string $option_id    Selected option id.
	 * @param string $label        Selected option label (for the transcript).
	 *
	 * @return array
	 */
	public static function handle_select( array $conversation, string $option_id, string $label ): array {
		$state   = (string) $conversation['state'];
		$context = Conversation::context( $conversation );
		$id      = (int) $conversation['id'];

		Conversation::add_message( $id, 'user', $label ?: $option_id );

		// Quick action → part selection.
		if ( str_starts_with( $option_id, 'qa:' ) ) {
			$qa = Quick_Action::get( absint( substr( $option_id, 3 ) ) );
			if ( $qa && (int) $qa['is_active'] ) {
				$part = $qa['part_id'] ? Part::get( (int) $qa['part_id'] ) : null;
				if ( $part ) {
					return self::set_part( $id, $context, $part );
				}
				// Quick action without a linked part → treat title as text.
				return self::transition( $id, $state, $context, self::rule_based_intent( (string) $qa['title'], $state ), (string) $qa['title'] );
			}
		}

		// Part picked from an options list.
		if ( str_starts_with( $option_id, 'part:' ) ) {
			$part = Part::get( absint( substr( $option_id, 5 ) ) );
			if ( $part && (int) $part['is_active'] ) {
				return self::set_part( $id, $context, $part );
			}
		}

		// Brand picked.
		if ( str_starts_with( $option_id, 'brand:' ) ) {
			$brand = Vehicle::get( absint( substr( $option_id, 6 ) ) );
			if ( $brand && 'brand' === $brand['type'] ) {
				return self::set_brand( $id, $context, $brand );
			}
		}

		// Model picked.
		if ( str_starts_with( $option_id, 'model:' ) ) {
			$model = Vehicle::get( absint( substr( $option_id, 6 ) ) );
			if ( $model && 'model' === $model['type'] ) {
				$brand = Vehicle::get( (int) $model['parent_id'] );
				$model = array_merge(
					$model,
					array(
						'brand_id'      => (int) ( $brand['id'] ?? 0 ),
						'brand_name'    => (string) ( $brand['name'] ?? '' ),
						'brand_name_en' => (string) ( $brand['name_en'] ?? '' ),
					)
				);
				return self::set_model( $id, $context, $model );
			}
		}

		// Trim picked.
		if ( str_starts_with( $option_id, 'trim:' ) ) {
			$trim = Vehicle::get( absint( substr( $option_id, 5 ) ) );
			if ( $trim && 'trim' === $trim['type'] ) {
				return self::set_trim( $id, $context, (string) $trim['name'] );
			}
		}

		// Confirmation-stage actions.
		switch ( $option_id ) {
			case 'confirm':
				return self::to_phone( $id, $context );
			case 'edit_vehicle':
				return self::ask_vehicle( $id, $context, true );
			case 'edit_year':
				return self::ask_year( $id, $context );
			case 'edit_part':
				return self::ask_part( $id, $context, true );
			case 'restart':
				return self::restart( $id );
			case 'no_trim':
				return self::set_trim( $id, $context, '' );
		}

		// Unknown option — re-issue current step.
		return self::reprompt( $id, $state, $context );
	}

	/**
	 * Handle phone submission.
	 *
	 * @param array  $conversation Conversation row.
	 * @param string $raw_phone    Raw phone input.
	 *
	 * @return array
	 */
	public static function handle_phone( array $conversation, string $raw_phone ): array {
		$context = Conversation::context( $conversation );
		$id      = (int) $conversation['id'];
		$state   = (string) $conversation['state'];

		if ( States::PHONE_REQUEST !== $state ) {
			return self::reprompt( $id, $state, $context );
		}

		$phone = Security::normalize_phone( $raw_phone );

		Conversation::add_message( $id, 'user', '' !== $phone ? partino_chatbot_mask_phone( $phone ) : sanitize_text_field( $raw_phone ) );

		if ( '' === $phone && Settings::get( 'lead.phone_validation', true ) ) {
			return self::respond(
				$id,
				States::PHONE_REQUEST,
				$context,
				array( __( 'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره ایرانی مثل 09121234567 وارد کن.', 'partino-smart-chatbot' ) ),
				array(),
				array( 'input_mode' => 'phone' )
			);
		}

		$context['phone']    = $phone ?: sanitize_text_field( $raw_phone );
		$context['page_url'] = $context['page_url'] ?? '';

		// Create the inquiry (server-side, validated).
		$inquiry_id = Inquiry::create( $context, $id );

		if ( -1 === $inquiry_id ) {
			return self::respond(
				$id,
				States::SUBMITTED,
				$context,
				array( __( 'برای این قطعه و خودرو اخیراً استعلامی با این شماره ثبت شده. همکاران ما به‌زودی باهات تماس می‌گیرن 🙏', 'partino-smart-chatbot' ) ),
				array( self::restart_option() ),
				array(),
				'completed'
			);
		}

		if ( 0 === $inquiry_id ) {
			return self::respond(
				$id,
				States::PHONE_REQUEST,
				$context,
				array( __( 'ثبت استعلام با خطا مواجه شد. لطفاً دوباره تلاش کن.', 'partino-smart-chatbot' ) ),
				array(),
				array( 'input_mode' => 'phone' )
			);
		}

		$context['inquiry_id'] = $inquiry_id;

		$success = (string) Settings::get( 'lead.success_message', '' );
		if ( '' === trim( $success ) ) {
			$success = __( 'استعلامت با موفقیت ثبت شد ✅', 'partino-smart-chatbot' );
		}

		$status = Settings::get( 'conversation.reset_after_submit', true ) ? 'completed' : 'active';

		return self::respond( $id, States::SUBMITTED, $context, array( $success ), array( self::restart_option() ), array(), $status );
	}

	// -----------------------------------------------------------------------
	// State transitions.
	// -----------------------------------------------------------------------

	/**
	 * Core transition from a structured intent.
	 *
	 * @param int    $id      Conversation id.
	 * @param string $state   Current state.
	 * @param array  $context Context.
	 * @param array  $intent  Structured intent (validated).
	 * @param string $text    Original text.
	 *
	 * @return array
	 */
	private static function transition( int $id, string $state, array $context, array $intent, string $text ): array {
		$kind = (string) ( $intent['intent'] ?? 'unknown' );

		// Global intents — can fire in any state.
		if ( 'edit_vehicle' === $kind ) {
			return self::ask_vehicle( $id, $context, true );
		}
		if ( 'edit_year' === $kind && ! empty( $context['vehicle_model'] ) ) {
			return self::ask_year( $id, $context );
		}
		if ( 'edit_part' === $kind ) {
			return self::ask_part( $id, $context, true );
		}
		if ( 'greeting' === $kind && States::INITIAL === $state ) {
			return self::ask_part( $id, $context, false );
		}

		switch ( $state ) {
			case States::INITIAL:
			case States::PART_REQUEST:
				// Try part from intent, then matcher.
				$part = null;
				if ( ! empty( $intent['part'] ) ) {
					$part = Part::match( (string) $intent['part'] );
				}
				if ( ! $part ) {
					$part = Part::match( $text );
				}

				// Maybe the user typed part AND vehicle together.
				if ( $part ) {
					$response = self::set_part( $id, $context, $part, true );
					$model    = null;
					if ( ! empty( $intent['vehicle_model'] ) ) {
						$model = Vehicle::match_model( trim( (string) ( $intent['vehicle_brand'] ?? '' ) . ' ' . (string) $intent['vehicle_model'] ) );
					}
					if ( ! $model ) {
						$model = Vehicle::match_model( $text );
					}
					if ( $model ) {
						$context = self::ctx( $id );
						return self::set_model( $id, $context, $model );
					}
					return $response;
				}

				// Maybe only a vehicle was given first.
				$model = Vehicle::match_model( $text );
				if ( $model ) {
					$resp = self::set_model( $id, $context, $model );
					return $resp;
				}

				return self::ask_part( $id, $context, false, true );

			case States::PART_SELECTED:
			case States::VEHICLE_REQUEST:
			case States::VEHICLE_BRAND:
			case States::VEHICLE_MODEL:
				$model = null;
				if ( ! empty( $intent['vehicle_model'] ) ) {
					$model = Vehicle::match_model( trim( (string) ( $intent['vehicle_brand'] ?? '' ) . ' ' . (string) $intent['vehicle_model'] ) );
				}
				if ( ! $model ) {
					$model = Vehicle::match_model( $text );
				}
				if ( $model ) {
					return self::set_model( $id, $context, $model );
				}
				return self::respond(
					$id,
					States::VEHICLE_MODEL,
					$context,
					array( __( 'متوجه مدل خودرو نشدم 🤔 لطفاً برند و مدل رو دقیق‌تر بنویس؛ مثلاً «پژو 206» یا «سمند LX».', 'partino-smart-chatbot' ) ),
					self::brand_options()
				);

			case States::VEHICLE_TRIM:
				$model_id = (int) ( $context['vehicle_model_id'] ?? 0 );
				$trim     = null;
				if ( ! empty( $intent['vehicle_trim'] ) ) {
					$trim = Vehicle::match_trim( (string) $intent['vehicle_trim'], $model_id );
				}
				if ( ! $trim ) {
					$trim = Vehicle::match_trim( $text, $model_id );
				}
				if ( $trim ) {
					return self::set_trim( $id, $context, (string) $trim['name'] );
				}
				return self::respond(
					$id,
					States::VEHICLE_TRIM,
					$context,
					array( __( 'این تیپ رو نشناختم. یکی از گزینه‌ها رو انتخاب کن:', 'partino-smart-chatbot' ) ),
					self::trim_options( $model_id )
				);

			case States::VEHICLE_YEAR:
				$year = '';
				if ( ! empty( $intent['vehicle_year'] ) ) {
					$year = Normalizer::extract_year( (string) $intent['vehicle_year'] );
				}
				if ( '' === $year ) {
					$year = Normalizer::extract_year( $text );
				}
				if ( '' !== $year ) {
					$context['vehicle_year'] = $year;
					return self::to_confirmation( $id, $context );
				}
				return self::respond(
					$id,
					States::VEHICLE_YEAR,
					$context,
					array( __( 'سال ساخت رو متوجه نشدم. لطفاً به شکل عددی بنویس؛ مثلاً 1388 یا 2015.', 'partino-smart-chatbot' ) )
				);

			case States::CONFIRMATION:
				if ( 'confirm' === $kind ) {
					return self::to_phone( $id, $context );
				}
				if ( 'deny' === $kind ) {
					return self::respond(
						$id,
						States::CONFIRMATION,
						$context,
						array( __( 'باشه، چه چیزی رو می‌خوای اصلاح کنی؟', 'partino-smart-chatbot' ) ),
						self::confirmation_options( false )
					);
				}
				return self::to_confirmation( $id, $context );

			case States::PHONE_REQUEST:
				// Treat text at the phone stage as a phone attempt.
				return self::handle_phone(
					array(
						'id'      => $id,
						'state'   => States::PHONE_REQUEST,
						'context' => wp_json_encode( $context, JSON_UNESCAPED_UNICODE ),
					),
					$text
				);

			case States::SUBMITTED:
				return self::restart( $id );

			default:
				return self::reprompt( $id, $state, $context );
		}
	}

	// -----------------------------------------------------------------------
	// Step helpers.
	// -----------------------------------------------------------------------

	/**
	 * Fresh context from DB.
	 *
	 * @param int $id Conversation id.
	 *
	 * @return array
	 */
	private static function ctx( int $id ): array {
		$row = Conversation::get( $id );
		return $row ? Conversation::context( $row ) : array();
	}

	/**
	 * First bot prompt (welcome) — also used by /start.
	 *
	 * @param int  $id       Conversation id.
	 * @param bool $store    Persist the welcome as a message.
	 *
	 * @return array
	 */
	public static function welcome( int $id, bool $store = true ): array {
		$welcome = (string) Settings::get( 'appearance.welcome_message', '' );

		$options = array();
		if ( Settings::get( 'chatbot.quick_actions', true ) ) {
			foreach ( Quick_Action::get_list( true ) as $qa ) {
				$options[] = array(
					'id'    => 'qa:' . (int) $qa['id'],
					'label' => (string) $qa['title'],
				);
			}
		}

		if ( $store ) {
			Conversation::add_message( $id, 'assistant', $welcome, array( 'options' => $options ) );
			Conversation::update_state( $id, States::PART_REQUEST, array() );
		}

		return array(
			'state'    => States::PART_REQUEST,
			'messages' => array( $welcome ),
			'options'  => $options,
			'ui'       => array( 'input_mode' => 'text' ),
		);
	}

	/**
	 * Ask which part is needed.
	 */
	private static function ask_part( int $id, array $context, bool $editing, bool $not_understood = false ): array {
		$messages = array();
		if ( $not_understood ) {
			$messages[] = __( 'متوجه نشدم دقیقاً چه قطعه‌ای می‌خوای 🤔', 'partino-smart-chatbot' );
		}
		$messages[] = $editing
			? __( 'چه قطعه‌ای می‌خوای؟ از گزینه‌ها انتخاب کن یا اسم قطعه رو بنویس.', 'partino-smart-chatbot' )
			: __( 'چه قطعه‌ای برای ماشینت لازم داری؟ اسم قطعه رو بنویس یا از گزینه‌ها انتخاب کن.', 'partino-smart-chatbot' );

		$options = array();
		foreach ( array_slice( Part::get_list( true ), 0, 8 ) as $part ) {
			$options[] = array(
				'id'    => 'part:' . (int) $part['id'],
				'label' => (string) $part['name'],
			);
		}

		return self::respond( $id, States::PART_REQUEST, $context, $messages, $options );
	}

	/**
	 * Save the part and move to vehicle step (or confirmation when vehicle known).
	 */
	private static function set_part( int $id, array $context, array $part, bool $silent = false ): array {
		$context['part_id']       = (int) $part['id'];
		$context['part_name']     = (string) $part['name'];
		$context['part_category'] = (string) $part['category'];

		if ( ! empty( $context['vehicle_model'] ) && ! empty( $context['vehicle_year'] ) ) {
			return self::to_confirmation( $id, $context );
		}
		if ( ! empty( $context['vehicle_model'] ) ) {
			return self::ask_year( $id, $context );
		}

		$messages = array(
			sprintf(
				/* translators: %s part name */
				__( 'برای چه خودرویی؟%sبرند و مدل خودرو رو بگو تا قیمت دقیق بگیرم.', 'partino-smart-chatbot' ),
				"\n"
			),
		);

		if ( $silent ) {
			// Caller may replace this response; still persist state.
		}

		return self::respond( $id, States::VEHICLE_MODEL, $context, $messages, self::brand_options() );
	}

	/**
	 * Ask for the vehicle (edit flow).
	 */
	private static function ask_vehicle( int $id, array $context, bool $editing ): array {
		unset( $context['vehicle_brand'], $context['vehicle_model'], $context['vehicle_model_id'], $context['vehicle_trim'], $context['vehicle_year'], $context['brand_id'] );

		return self::respond(
			$id,
			States::VEHICLE_MODEL,
			$context,
			array( __( 'برند و مدل خودرو رو بنویس؛ مثلاً «پژو 206» یا از گزینه‌ها انتخاب کن.', 'partino-smart-chatbot' ) ),
			self::brand_options()
		);
	}

	/**
	 * Save brand from options, list its models.
	 */
	private static function set_brand( int $id, array $context, array $brand ): array {
		$context['brand_id']      = (int) $brand['id'];
		$context['vehicle_brand'] = (string) ( $brand['name_en'] ?: $brand['name'] );

		$options = array();
		foreach ( Vehicle::get_list( 'model', (int) $brand['id'], true ) as $model ) {
			$options[] = array(
				'id'    => 'model:' . (int) $model['id'],
				'label' => (string) $model['name'],
			);
		}

		if ( empty( $options ) ) {
			return self::respond(
				$id,
				States::VEHICLE_MODEL,
				$context,
				array(
					sprintf(
						/* translators: %s brand name */
						__( 'مدل %s رو بنویس تا ادامه بدیم.', 'partino-smart-chatbot' ),
						(string) $brand['name']
					),
				)
			);
		}

		return self::respond(
			$id,
			States::VEHICLE_MODEL,
			$context,
			array(
				sprintf(
					/* translators: %s brand name */
					__( 'کدوم مدل %s؟', 'partino-smart-chatbot' ),
					(string) $brand['name']
				),
			),
			$options
		);
	}

	/**
	 * Save model, ask for trim (or year when the model has no trims).
	 */
	private static function set_model( int $id, array $context, array $model ): array {
		$context['vehicle_brand']    = (string) ( $model['brand_name_en'] ?: $model['brand_name'] );
		$context['vehicle_model']    = (string) ( $model['name_en'] ?: $model['name'] );
		$context['vehicle_model_id'] = (int) $model['id'];
		$context['brand_id']         = (int) ( $model['brand_id'] ?? 0 );
		unset( $context['vehicle_trim'] );

		$trims = self::trim_options( (int) $model['id'] );

		// No part yet? (user gave vehicle first).
		if ( empty( $context['part_name'] ) ) {
			if ( ! empty( $trims ) ) {
				return self::respond(
					$id,
					States::VEHICLE_TRIM,
					$context,
					array(
						sprintf(
							/* translators: 1: brand 2: model */
							__( 'تیپ %1$s %2$s؟', 'partino-smart-chatbot' ),
							(string) $model['brand_name'],
							(string) $model['name']
						),
					),
					$trims
				);
			}
			return self::ask_year( $id, $context );
		}

		if ( ! empty( $trims ) ) {
			return self::respond(
				$id,
				States::VEHICLE_TRIM,
				$context,
				array(
					sprintf(
						/* translators: 1: brand 2: model */
						__( 'تیپ %1$s %2$s؟', 'partino-smart-chatbot' ),
						(string) $model['brand_name'],
						(string) $model['name']
					),
				),
				$trims
			);
		}

		return self::ask_year( $id, $context );
	}

	/**
	 * Save trim, ask year.
	 */
	private static function set_trim( int $id, array $context, string $trim_name ): array {
		$context['vehicle_trim'] = $trim_name;

		if ( ! empty( $context['vehicle_year'] ) && ! empty( $context['part_name'] ) ) {
			return self::to_confirmation( $id, $context );
		}

		return self::ask_year( $id, $context );
	}

	/**
	 * Ask for the model year.
	 */
	private static function ask_year( int $id, array $context ): array {
		unset( $context['vehicle_year'] );
		return self::respond(
			$id,
			States::VEHICLE_YEAR,
			$context,
			array( __( 'مدل ماشینت چنده؟ (سال ساخت، مثلاً 1388)', 'partino-smart-chatbot' ) )
		);
	}

	/**
	 * Build confirmation summary.
	 */
	private static function to_confirmation( int $id, array $context ): array {
		// If part is still missing, go get it.
		if ( empty( $context['part_name'] ) ) {
			return self::ask_part( $id, $context, false );
		}
		if ( empty( $context['vehicle_model'] ) ) {
			return self::ask_vehicle( $id, $context, false );
		}
		if ( empty( $context['vehicle_year'] ) ) {
			return self::ask_year( $id, $context );
		}

		$vehicle = trim(
			(string) ( $context['vehicle_brand'] ?? '' ) . ' ' .
			(string) ( $context['vehicle_model'] ?? '' ) .
			( ! empty( $context['vehicle_trim'] ) ? ' ' . $context['vehicle_trim'] : '' )
		);

		$summary = sprintf(
			/* translators: 1: part 2: vehicle 3: year */
			__( '%1$s برای %2$s مدل %3$s درسته؟%4$sتأیید می‌کنی تا استعلام قیمت ثبت بشه؟', 'partino-smart-chatbot' ),
			(string) $context['part_name'],
			$vehicle,
			(string) $context['vehicle_year'],
			"\n"
		);

		return self::respond( $id, States::CONFIRMATION, $context, array( $summary ), self::confirmation_options( true ) );
	}

	/**
	 * Move to phone request.
	 */
	private static function to_phone( int $id, array $context ): array {
		return self::respond(
			$id,
			States::PHONE_REQUEST,
			$context,
			array( __( "عالیه 👌\nبرای ثبت استعلام، شماره موبایلت رو وارد کن تا فروشنده‌ها بتونن باهات تماس بگیرن.", 'partino-smart-chatbot' ) ),
			array(),
			array( 'input_mode' => 'phone' )
		);
	}

	/**
	 * Restart the flow within the same conversation.
	 */
	public static function restart( int $id ): array {
		Conversation::update_state( $id, States::PART_REQUEST, array() );
		return self::welcome( $id, true );
	}

	/**
	 * Re-issue the prompt for the current state.
	 */
	private static function reprompt( int $id, string $state, array $context ): array {
		switch ( $state ) {
			case States::VEHICLE_TRIM:
				return self::respond( $id, $state, $context, array( __( 'یکی از تیپ‌های زیر رو انتخاب کن:', 'partino-smart-chatbot' ) ), self::trim_options( (int) ( $context['vehicle_model_id'] ?? 0 ) ) );
			case States::VEHICLE_YEAR:
				return self::ask_year( $id, $context );
			case States::CONFIRMATION:
				return self::to_confirmation( $id, $context );
			case States::PHONE_REQUEST:
				return self::to_phone( $id, $context );
			case States::VEHICLE_MODEL:
			case States::VEHICLE_REQUEST:
			case States::VEHICLE_BRAND:
				return self::ask_vehicle( $id, $context, false );
			default:
				return self::ask_part( $id, $context, false );
		}
	}

	// -----------------------------------------------------------------------
	// Response builder.
	// -----------------------------------------------------------------------

	/**
	 * Persist state + bot messages, then build the REST payload.
	 *
	 * @param int    $id       Conversation id.
	 * @param string $state    New state.
	 * @param array  $context  Context to persist.
	 * @param array  $messages Bot messages (plain strings).
	 * @param array  $options  Option buttons.
	 * @param array  $ui       Extra UI hints (input_mode...).
	 * @param string $status   Conversation status.
	 *
	 * @return array
	 */
	private static function respond( int $id, string $state, array $context, array $messages, array $options = array(), array $ui = array(), string $status = 'active' ): array {
		if ( ! in_array( $state, States::all(), true ) ) {
			$state = States::ERROR;
		}

		Conversation::update_state( $id, $state, $context, $status );

		foreach ( $messages as $message ) {
			Conversation::add_message( $id, 'assistant', (string) $message, ! empty( $options ) ? array( 'options' => $options ) : array() );
		}

		$input_mode = $ui['input_mode'] ?? ( States::PHONE_REQUEST === $state ? 'phone' : 'text' );

		return array(
			'state'    => $state,
			'messages' => array_values( array_map( 'strval', $messages ) ),
			'options'  => array_values( $options ),
			'ui'       => array( 'input_mode' => $input_mode ),
			'done'     => States::SUBMITTED === $state,
		);
	}

	// -----------------------------------------------------------------------
	// Options builders.
	// -----------------------------------------------------------------------

	/**
	 * Brand quick options (top 6).
	 *
	 * @return array[]
	 */
	private static function brand_options(): array {
		$options = array();
		foreach ( array_slice( Vehicle::get_list( 'brand', null, true ), 0, 6 ) as $brand ) {
			$options[] = array(
				'id'    => 'brand:' . (int) $brand['id'],
				'label' => (string) $brand['name'],
			);
		}
		return $options;
	}

	/**
	 * Trim options for a model.
	 *
	 * @param int $model_id Model id.
	 *
	 * @return array[]
	 */
	private static function trim_options( int $model_id ): array {
		if ( $model_id <= 0 ) {
			return array();
		}
		$options = array();
		foreach ( Vehicle::get_list( 'trim', $model_id, true ) as $trim ) {
			$options[] = array(
				'id'    => 'trim:' . (int) $trim['id'],
				'label' => (string) $trim['name'],
			);
		}
		if ( ! empty( $options ) ) {
			$options[] = array(
				'id'    => 'no_trim',
				'label' => __( 'نمی‌دونم / مهم نیست', 'partino-smart-chatbot' ),
			);
		}
		return $options;
	}

	/**
	 * Confirmation options.
	 *
	 * @param bool $with_confirm Include the confirm button.
	 *
	 * @return array[]
	 */
	private static function confirmation_options( bool $with_confirm ): array {
		$options = array();
		if ( $with_confirm ) {
			$options[] = array(
				'id'    => 'confirm',
				'label' => __( 'تایید و استعلام', 'partino-smart-chatbot' ),
				'style' => 'primary',
			);
		}
		$allow_edit = (bool) Settings::get( 'conversation.allow_edit', true );
		if ( $allow_edit ) {
			$options[] = array(
				'id'    => 'edit_vehicle',
				'label' => __( 'اصلاح خودرو', 'partino-smart-chatbot' ),
			);
			$options[] = array(
				'id'    => 'edit_year',
				'label' => __( 'اصلاح سال', 'partino-smart-chatbot' ),
			);
			$options[] = array(
				'id'    => 'edit_part',
				'label' => __( 'اصلاح قطعه', 'partino-smart-chatbot' ),
			);
		}
		return $options;
	}

	/**
	 * Restart option shown after submission.
	 *
	 * @return array
	 */
	private static function restart_option(): array {
		return array(
			'id'    => 'restart',
			'label' => __( 'درخواست جدید', 'partino-smart-chatbot' ),
			'style' => 'primary',
		);
	}

	// -----------------------------------------------------------------------
	// Rule-based fallback NLU.
	// -----------------------------------------------------------------------

	/**
	 * Rule-based intent detection (works fully offline / without AI).
	 *
	 * @param string $text  User text.
	 * @param string $state Current state.
	 *
	 * @return array Structured intent (same schema as AI).
	 */
	public static function rule_based_intent( string $text, string $state ): array {
		$norm = Normalizer::text( $text );

		$intent = array(
			'intent'                 => 'unknown',
			'part'                   => null,
			'vehicle_brand'          => null,
			'vehicle_model'          => null,
			'vehicle_trim'           => null,
			'vehicle_year'           => null,
			'confidence'             => 0.6,
			'requires_clarification' => false,
			'clarification_type'     => null,
		);

		// Confirm / deny words.
		$confirm_words = array( 'بله', 'اره', 'آره', 'تایید', 'تأیید', 'درسته', 'اوکی', 'اوکیه', 'ok', 'yes', 'باشه', 'قبول' );
		$deny_words    = array( 'نه', 'خیر', 'غلطه', 'اشتباهه', 'no', 'نمیخوام', 'نمی خوام' );

		foreach ( $confirm_words as $w ) {
			if ( $norm === Normalizer::text( $w ) || str_starts_with( $norm, Normalizer::text( $w ) . ' ' ) ) {
				$intent['intent'] = 'confirm';
				return $intent;
			}
		}
		foreach ( $deny_words as $w ) {
			if ( $norm === Normalizer::text( $w ) || str_starts_with( $norm, Normalizer::text( $w ) . ' ' ) ) {
				$intent['intent'] = 'deny';
				return $intent;
			}
		}

		// Edit requests.
		if ( preg_match( '/(عوض|اصلاح|تغییر|اشتباه)/u', $norm ) ) {
			if ( preg_match( '/(ماشین|خودرو|مدل)/u', $norm ) ) {
				$intent['intent'] = 'edit_vehicle';
				return $intent;
			}
			if ( preg_match( '/سال/u', $norm ) ) {
				$intent['intent'] = 'edit_year';
				return $intent;
			}
			if ( preg_match( '/قطعه/u', $norm ) ) {
				$intent['intent'] = 'edit_part';
				return $intent;
			}
		}

		// Year?
		$year = Normalizer::extract_year( $text );
		if ( '' !== $year && ( States::VEHICLE_YEAR === $state || preg_match( '/^\s*\d+\s*$/', Normalizer::digits( $text ) ) ) ) {
			if ( States::VEHICLE_YEAR === $state ) {
				$intent['intent']       = 'vehicle_year';
				$intent['vehicle_year'] = $year;
				return $intent;
			}
		}

		// Greeting only.
		if ( preg_match( '/^(سلام|درود|هی|hi|hello|سلام علیکم)[\s!.،]*$/u', $norm ) ) {
			$intent['intent'] = 'greeting';
			return $intent;
		}

		// Otherwise unknown — the state machine will try part/vehicle matchers.
		return $intent;
	}
}
