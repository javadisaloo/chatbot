<?php
/**
 * REST API — namespace partino-chatbot/v1.
 *
 * Public endpoints: nonce + rate limit + validation.
 * Admin endpoints: capability checks.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles all REST routes.
 */
final class Rest_Api {

	/**
	 * Hook registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Define routes.
	 */
	public function routes(): void {
		$public = array( $this, 'permission_public' );
		$admin  = array( $this, 'permission_admin' );

		register_rest_route(
			PARTINO_CHATBOT_REST_NS,
			'/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => $public,
			)
		);

		register_rest_route(
			PARTINO_CHATBOT_REST_NS,
			'/conversation/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_conversation' ),
				'permission_callback' => $public,
				'args'                => array(
					'conversation' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page_url'     => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			PARTINO_CHATBOT_REST_NS,
			'/conversation/message',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_message' ),
				'permission_callback' => $public,
				'args'                => array(
					'conversation' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'text'         => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		register_rest_route(
			PARTINO_CHATBOT_REST_NS,
			'/conversation/select',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_select' ),
				'permission_callback' => $public,
				'args'                => array(
					'conversation' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'option'       => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'label'        => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			PARTINO_CHATBOT_REST_NS,
			'/conversation/phone',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_phone' ),
				'permission_callback' => $public,
				'args'                => array(
					'conversation' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'phone'        => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Admin: OpenRouter connection test.
		register_rest_route(
			PARTINO_CHATBOT_REST_NS,
			'/admin/test-ai',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_test_ai' ),
				'permission_callback' => $admin,
			)
		);
	}

	// -----------------------------------------------------------------------
	// Permissions.
	// -----------------------------------------------------------------------

	/**
	 * Public permission: nonce + rate limit.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return true|WP_Error
	 */
	public function permission_public( WP_REST_Request $request ) {
		// Nonce (cookie-based REST nonce).
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'partino_invalid_nonce',
				__( 'نشست شما منقضی شده است. لطفاً صفحه را تازه‌سازی کنید.', 'partino-smart-chatbot' ),
				array( 'status' => 403 )
			);
		}

		// Rate limit only on write methods.
		if ( 'GET' !== $request->get_method() && ! Security::check_rate_limit() ) {
			return new WP_Error(
				'partino_rate_limited',
				__( 'تعداد درخواست‌ها بیش از حد مجاز است. لطفاً کمی صبر کنید.', 'partino-smart-chatbot' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Admin permission.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return true|WP_Error
	 */
	public function permission_admin( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'partino_invalid_nonce', __( 'نشست نامعتبر است.', 'partino-smart-chatbot' ), array( 'status' => 403 ) );
		}
		if ( ! current_user_can( PARTINO_CHATBOT_CAP ) ) {
			return new WP_Error( 'partino_forbidden', __( 'دسترسی غیرمجاز.', 'partino-smart-chatbot' ), array( 'status' => 403 ) );
		}
		return true;
	}

	// -----------------------------------------------------------------------
	// Public handlers.
	// -----------------------------------------------------------------------

	/**
	 * GET /config — safe, public widget configuration (never the API key).
	 *
	 * @return WP_REST_Response
	 */
	public function get_config(): WP_REST_Response {
		$config = Front_Config::build();
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $config,
			),
			200
		);
	}

	/**
	 * POST /conversation/start — create or resume a conversation.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function start_conversation( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Settings::get( 'general.enabled', true ) ) {
			return $this->fail( __( 'چت‌بات در حال حاضر غیرفعال است.', 'partino-smart-chatbot' ), 503 );
		}

		$uuid     = (string) $request->get_param( 'conversation' );
		$page_url = (string) ( $request->get_param( 'page_url' ) ?? '' );

		// Resume?
		if ( '' !== $uuid ) {
			$existing = Conversation::get_by_uuid( $uuid );
			if ( $existing ) {
				$messages = Conversation::get_messages( (int) $existing['id'] );
				$history  = array();
				foreach ( $messages as $m ) {
					$meta      = json_decode( (string) ( $m['meta'] ?? '' ), true );
					$history[] = array(
						'role'    => $m['role'],
						'content' => $m['content'],
						'time'    => mysql2date( 'H:i', $m['created_at'] ),
						'options' => is_array( $meta ) && ! empty( $meta['options'] ) ? $meta['options'] : array(),
					);
				}
				return $this->ok(
					array(
						'conversation' => $existing['uuid'],
						'state'        => $existing['state'],
						'resumed'      => true,
						'history'      => $history,
						'ui'           => array( 'input_mode' => States::PHONE_REQUEST === $existing['state'] ? 'phone' : 'text' ),
					)
				);
			}
		}

		$conversation = Conversation::create( $page_url );
		if ( ! $conversation ) {
			return $this->fail( __( 'ایجاد گفتگو با خطا مواجه شد.', 'partino-smart-chatbot' ), 500 );
		}

		$welcome = Engine::welcome( (int) $conversation['id'], true );

		return $this->ok(
			array(
				'conversation' => $conversation['uuid'],
				'resumed'      => false,
				'state'        => $welcome['state'],
				'messages'     => $welcome['messages'],
				'options'      => $welcome['options'],
				'ui'           => $welcome['ui'],
			)
		);
	}

	/**
	 * POST /conversation/message — free text.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function post_message( WP_REST_Request $request ): WP_REST_Response {
		$conversation = $this->resolve_conversation( $request );
		if ( is_wp_error( $conversation ) ) {
			return $this->fail( $conversation->get_error_message(), 404, $conversation->get_error_code() );
		}

		$text = trim( (string) $request->get_param( 'text' ) );
		if ( '' === $text || mb_strlen( $text ) > 1000 ) {
			return $this->fail( __( 'متن پیام نامعتبر است.', 'partino-smart-chatbot' ), 400 );
		}

		try {
			$result = Engine::handle_text( $conversation, $text );
		} catch ( \Throwable $e ) {
			Logger::error( 'engine', 'Engine failure: ' . $e->getMessage() );
			return $this->fail( __( 'متأسفانه ارتباط با سرور برقرار نشد. لطفاً دوباره تلاش کنید.', 'partino-smart-chatbot' ), 500 );
		}

		return $this->ok( $result );
	}

	/**
	 * POST /conversation/select — option button.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function post_select( WP_REST_Request $request ): WP_REST_Response {
		$conversation = $this->resolve_conversation( $request );
		if ( is_wp_error( $conversation ) ) {
			return $this->fail( $conversation->get_error_message(), 404, $conversation->get_error_code() );
		}

		$option = (string) $request->get_param( 'option' );
		$label  = (string) ( $request->get_param( 'label' ) ?? '' );

		if ( '' === $option || strlen( $option ) > 64 || ! preg_match( '/^[a-z0-9_:\-]+$/', $option ) ) {
			return $this->fail( __( 'گزینه نامعتبر است.', 'partino-smart-chatbot' ), 400 );
		}

		try {
			$result = Engine::handle_select( $conversation, $option, mb_substr( $label, 0, 120 ) );
		} catch ( \Throwable $e ) {
			Logger::error( 'engine', 'Engine failure (select): ' . $e->getMessage() );
			return $this->fail( __( 'متأسفانه ارتباط با سرور برقرار نشد. لطفاً دوباره تلاش کنید.', 'partino-smart-chatbot' ), 500 );
		}

		return $this->ok( $result );
	}

	/**
	 * POST /conversation/phone — phone submission (final step).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function post_phone( WP_REST_Request $request ): WP_REST_Response {
		$conversation = $this->resolve_conversation( $request );
		if ( is_wp_error( $conversation ) ) {
			return $this->fail( $conversation->get_error_message(), 404, $conversation->get_error_code() );
		}

		$phone = (string) $request->get_param( 'phone' );
		if ( '' === trim( $phone ) || strlen( $phone ) > 30 ) {
			return $this->fail( __( 'شماره موبایل نامعتبر است.', 'partino-smart-chatbot' ), 400 );
		}

		try {
			$result = Engine::handle_phone( $conversation, $phone );
		} catch ( \Throwable $e ) {
			Logger::error( 'engine', 'Engine failure (phone): ' . $e->getMessage() );
			return $this->fail( __( 'ثبت استعلام با خطا مواجه شد. لطفاً دوباره تلاش کنید.', 'partino-smart-chatbot' ), 500 );
		}

		return $this->ok( $result );
	}

	// -----------------------------------------------------------------------
	// Admin handlers.
	// -----------------------------------------------------------------------

	/**
	 * POST /admin/test-ai.
	 *
	 * @return WP_REST_Response
	 */
	public function admin_test_ai(): WP_REST_Response {
		$result = AI::test_connection();
		return new WP_REST_Response(
			array(
				'success' => (bool) $result['success'],
				'message' => (string) $result['message'],
			),
			$result['success'] ? 200 : 400
		);
	}

	// -----------------------------------------------------------------------
	// Internals.
	// -----------------------------------------------------------------------

	/**
	 * Resolve the conversation row from a request.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return array|WP_Error
	 */
	private function resolve_conversation( WP_REST_Request $request ) {
		$uuid = (string) $request->get_param( 'conversation' );
		$row  = Conversation::get_by_uuid( $uuid );
		if ( ! $row ) {
			return new WP_Error(
				'partino_conversation_not_found',
				__( 'گفتگو یافت نشد یا منقضی شده است. لطفاً گفتگوی جدیدی شروع کنید.', 'partino-smart-chatbot' )
			);
		}
		return $row;
	}

	/**
	 * Success envelope.
	 *
	 * @param array $data Payload.
	 *
	 * @return WP_REST_Response
	 */
	private function ok( array $data ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Failure envelope.
	 *
	 * @param string $message Friendly message.
	 * @param int    $status  HTTP status.
	 * @param string $code    Machine code.
	 *
	 * @return WP_REST_Response
	 */
	private function fail( string $message, int $status = 400, string $code = 'partino_error' ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}
}
