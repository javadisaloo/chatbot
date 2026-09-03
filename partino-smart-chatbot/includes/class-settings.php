<?php
/**
 * Settings management (single namespaced option, tabbed UI, sanitized).
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Settings store + sanitization.
 */
final class Settings {

	public const OPTION = 'partino_chatbot_settings';

	/**
	 * Runtime cache.
	 *
	 * @var array|null
	 */
	private static ?array $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'general'       => array(
				'enabled'      => true,
				'auto_open'    => true,
				'open_delay'   => 3000,
				'show_mobile'  => true,
				'show_desktop' => true,
				'position'     => 'bottom_left',
				'rtl'          => true,
				'language'     => 'fa',
			),
			'appearance'    => array(
				'primary_color'    => '#2069b2',
				'secondary_color'  => '#16a34a',
				'background_color' => '#eef2f7',
				'text_color'       => '#1e293b',
				'border_color'     => '#e2e8f0',
				'border_radius'    => 24,
				'chat_width'       => 400,
				'chat_height'      => 640,
				'header_height'    => 84,
				'button_radius'    => 14,
				'font_size'        => 14,
				'logo_url'         => '',
				'title'            => 'دستیار هوشمند پارتینو',
				'subtitle'         => 'آماده خرید قطعه با بهترین قیمت',
				'badge_text'       => 'ماشینت کنارته',
				'welcome_message'  => "سلام 👋 قطعه و مدل ماشینت رو بنویس\nخیلی سریع از بین صدها فروشنده معتبر برات استعلام می‌گیرم تا با بهترین قیمت و کیفیت قطعه رو بخری و سریع تحویل بگیری.",
				'placeholder'      => 'پیام خود را بنویسید...',
				'launcher_color'   => '#2069b2',
			),
			'chatbot'       => array(
				'typing_indicator' => true,
				'sound'            => false,
				'auto_scroll'      => true,
				'animation'        => true,
				'voice_input'      => true,
				'quick_actions'    => true,
				'show_timestamp'   => true,
			),
			'ai'            => array(
				'enabled'       => false,
				'api_key'       => '',
				'model'         => 'openai/gpt-4o-mini',
				'temperature'   => 0.2,
				'max_tokens'    => 500,
				'system_prompt' => '',
				'timeout'       => 15,
				'retry'         => 1,
			),
			'conversation'  => array(
				'max_length'         => 120,
				'session_timeout'    => 1440,
				'retention_days'     => 90,
				'reset_after_submit' => true,
				'allow_edit'         => true,
			),
			'lead'          => array(
				'phone_required'        => true,
				'phone_validation'      => true,
				'allow_duplicate_phone' => true,
				'duplicate_behavior'    => 'allow',
				'success_message'       => "استعلامت با موفقیت ثبت شد ✅\nبه‌زودی فروشنده‌های معتبر باهات تماس می‌گیرن.",
			),
			'privacy'       => array(
				'mode' => 'standard',
			),
			'notifications' => array(
				'email_enabled' => false,
				'admin_email'   => '',
				'subject'       => 'استعلام جدید قطعه ثبت شد - {part}',
				'template'      => "استعلام جدید قطعه ثبت شد.\n\nشماره مشتری: {phone}\nقطعه: {part}\nخودرو: {vehicle}\nسال: {year}\n\nمشاهده: {admin_url}",
			),
			'advanced'      => array(
				'rate_limit_per_minute'    => 20,
				'block_minutes'            => 10,
				'delete_data_on_uninstall' => false,
				'debug_log'                => false,
			),
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$saved    = get_option( self::OPTION, array() );
		$defaults = self::defaults();
		$merged   = $defaults;
		if ( is_array( $saved ) ) {
			foreach ( $defaults as $section => $fields ) {
				if ( isset( $saved[ $section ] ) && is_array( $saved[ $section ] ) ) {
					$merged[ $section ] = array_merge( $fields, $saved[ $section ] );
				}
			}
		}
		self::$cache = $merged;
		return $merged;
	}

	/**
	 * Get one setting by dot key ("general.enabled").
	 *
	 * @param string $key     Dot key.
	 * @param mixed  $default Fallback.
	 *
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all   = self::all();
		$parts = explode( '.', $key, 2 );
		if ( 2 === count( $parts ) ) {
			return $all[ $parts[0] ][ $parts[1] ] ?? $default;
		}
		return $all[ $key ] ?? $default;
	}

	/**
	 * Persist settings (already-sanitized array expected).
	 *
	 * @param array $settings Full settings array.
	 */
	public static function save( array $settings ): void {
		update_option( self::OPTION, $settings, false );
		self::$cache = null;
	}

	/**
	 * Reset to defaults (keeps API key unless $hard).
	 *
	 * @param bool $hard Also wipe the API key.
	 */
	public static function reset( bool $hard = false ): void {
		$defaults = self::defaults();
		if ( ! $hard ) {
			$defaults['ai']['api_key'] = (string) self::get( 'ai.api_key', '' );
		}
		self::save( $defaults );
	}

	/**
	 * Sanitize a raw (user-submitted) settings array against defaults.
	 *
	 * @param array $raw Raw input.
	 *
	 * @return array
	 */
	public static function sanitize( array $raw ): array {
		$current = self::all();
		$clean   = $current;

		$bool = static fn( $v ): bool => in_array( $v, array( '1', 1, true, 'true', 'on', 'yes' ), true );

		// General.
		if ( isset( $raw['general'] ) && is_array( $raw['general'] ) ) {
			$g                             = $raw['general'];
			$clean['general']['enabled']      = $bool( $g['enabled'] ?? false );
			$clean['general']['auto_open']    = $bool( $g['auto_open'] ?? false );
			$clean['general']['open_delay']   = min( 60000, absint( $g['open_delay'] ?? 3000 ) );
			$clean['general']['show_mobile']  = $bool( $g['show_mobile'] ?? false );
			$clean['general']['show_desktop'] = $bool( $g['show_desktop'] ?? false );
			$clean['general']['position']     = in_array( $g['position'] ?? '', array( 'bottom_right', 'bottom_left' ), true ) ? $g['position'] : 'bottom_left';
			$clean['general']['rtl']          = $bool( $g['rtl'] ?? true );
			$clean['general']['language']     = sanitize_key( $g['language'] ?? 'fa' );
		}

		// Appearance.
		if ( isset( $raw['appearance'] ) && is_array( $raw['appearance'] ) ) {
			$a = $raw['appearance'];
			foreach ( array( 'primary_color', 'secondary_color', 'background_color', 'text_color', 'border_color', 'launcher_color' ) as $c ) {
				if ( isset( $a[ $c ] ) ) {
					$hex = sanitize_hex_color( $a[ $c ] );
					if ( $hex ) {
						$clean['appearance'][ $c ] = $hex;
					}
				}
			}
			foreach ( array( 'border_radius', 'chat_width', 'chat_height', 'header_height', 'button_radius', 'font_size' ) as $n ) {
				if ( isset( $a[ $n ] ) ) {
					$clean['appearance'][ $n ] = min( 2000, absint( $a[ $n ] ) );
				}
			}
			foreach ( array( 'title', 'subtitle', 'badge_text', 'placeholder' ) as $t ) {
				if ( isset( $a[ $t ] ) ) {
					$clean['appearance'][ $t ] = sanitize_text_field( $a[ $t ] );
				}
			}
			if ( isset( $a['welcome_message'] ) ) {
				$clean['appearance']['welcome_message'] = sanitize_textarea_field( $a['welcome_message'] );
			}
			if ( isset( $a['logo_url'] ) ) {
				$clean['appearance']['logo_url'] = esc_url_raw( $a['logo_url'] );
			}
		}

		// Chatbot behaviour.
		if ( isset( $raw['chatbot'] ) && is_array( $raw['chatbot'] ) ) {
			foreach ( array( 'typing_indicator', 'sound', 'auto_scroll', 'animation', 'voice_input', 'quick_actions', 'show_timestamp' ) as $k ) {
				$clean['chatbot'][ $k ] = $bool( $raw['chatbot'][ $k ] ?? false );
			}
		}

		// AI.
		if ( isset( $raw['ai'] ) && is_array( $raw['ai'] ) ) {
			$ai                         = $raw['ai'];
			$clean['ai']['enabled']     = $bool( $ai['enabled'] ?? false );
			$clean['ai']['model']       = sanitize_text_field( $ai['model'] ?? $clean['ai']['model'] );
			$clean['ai']['temperature'] = max( 0, min( 2, (float) ( $ai['temperature'] ?? 0.2 ) ) );
			$clean['ai']['max_tokens']  = max( 50, min( 4000, absint( $ai['max_tokens'] ?? 500 ) ) );
			$clean['ai']['timeout']     = max( 3, min( 60, absint( $ai['timeout'] ?? 15 ) ) );
			$clean['ai']['retry']       = min( 3, absint( $ai['retry'] ?? 1 ) );
			if ( isset( $ai['system_prompt'] ) ) {
				$clean['ai']['system_prompt'] = sanitize_textarea_field( $ai['system_prompt'] );
			}
			// API key: keep old value if masked placeholder submitted.
			if ( isset( $ai['api_key'] ) ) {
				$key = trim( sanitize_text_field( $ai['api_key'] ) );
				if ( '' === $key ) {
					$clean['ai']['api_key'] = '';
				} elseif ( false === strpos( $key, '*' ) ) {
					$clean['ai']['api_key'] = $key;
				}
				// Masked value ⇒ keep existing key.
			}
		}

		// Conversation.
		if ( isset( $raw['conversation'] ) && is_array( $raw['conversation'] ) ) {
			$c                                         = $raw['conversation'];
			$clean['conversation']['max_length']         = max( 10, min( 1000, absint( $c['max_length'] ?? 120 ) ) );
			$clean['conversation']['session_timeout']    = max( 10, min( 10080, absint( $c['session_timeout'] ?? 1440 ) ) );
			$clean['conversation']['retention_days']     = min( 3650, absint( $c['retention_days'] ?? 90 ) );
			$clean['conversation']['reset_after_submit'] = $bool( $c['reset_after_submit'] ?? true );
			$clean['conversation']['allow_edit']         = $bool( $c['allow_edit'] ?? true );
		}

		// Lead.
		if ( isset( $raw['lead'] ) && is_array( $raw['lead'] ) ) {
			$l                                     = $raw['lead'];
			$clean['lead']['phone_required']        = $bool( $l['phone_required'] ?? true );
			$clean['lead']['phone_validation']      = $bool( $l['phone_validation'] ?? true );
			$clean['lead']['allow_duplicate_phone'] = $bool( $l['allow_duplicate_phone'] ?? true );
			$clean['lead']['duplicate_behavior']    = in_array( $l['duplicate_behavior'] ?? '', array( 'allow', 'merge', 'reject' ), true ) ? $l['duplicate_behavior'] : 'allow';
			if ( isset( $l['success_message'] ) ) {
				$clean['lead']['success_message'] = sanitize_textarea_field( $l['success_message'] );
			}
		}

		// Privacy.
		if ( isset( $raw['privacy']['mode'] ) ) {
			$clean['privacy']['mode'] = in_array( $raw['privacy']['mode'], array( 'minimal', 'standard', 'extended' ), true ) ? $raw['privacy']['mode'] : 'standard';
		}

		// Notifications.
		if ( isset( $raw['notifications'] ) && is_array( $raw['notifications'] ) ) {
			$n                                       = $raw['notifications'];
			$clean['notifications']['email_enabled'] = $bool( $n['email_enabled'] ?? false );
			$clean['notifications']['admin_email']   = sanitize_email( $n['admin_email'] ?? '' );
			if ( isset( $n['subject'] ) ) {
				$clean['notifications']['subject'] = sanitize_text_field( $n['subject'] );
			}
			if ( isset( $n['template'] ) ) {
				$clean['notifications']['template'] = sanitize_textarea_field( $n['template'] );
			}
		}

		// Advanced.
		if ( isset( $raw['advanced'] ) && is_array( $raw['advanced'] ) ) {
			$adv                                              = $raw['advanced'];
			$clean['advanced']['rate_limit_per_minute']       = max( 5, min( 600, absint( $adv['rate_limit_per_minute'] ?? 20 ) ) );
			$clean['advanced']['block_minutes']               = max( 1, min( 1440, absint( $adv['block_minutes'] ?? 10 ) ) );
			$clean['advanced']['delete_data_on_uninstall']    = $bool( $adv['delete_data_on_uninstall'] ?? false );
			$clean['advanced']['debug_log']                   = $bool( $adv['debug_log'] ?? false );
		}

		return $clean;
	}

	/**
	 * Masked API key for display (never expose the full key).
	 *
	 * @return string
	 */
	public static function masked_api_key(): string {
		$key = (string) self::get( 'ai.api_key', '' );
		if ( '' === $key ) {
			return '';
		}
		if ( strlen( $key ) <= 8 ) {
			return str_repeat( '*', strlen( $key ) );
		}
		return substr( $key, 0, 6 ) . str_repeat( '*', 14 ) . substr( $key, -4 );
	}

	/**
	 * Export settings as array with sensitive data masked.
	 *
	 * @return array
	 */
	public static function export(): array {
		$all                   = self::all();
		$all['ai']['api_key']  = '' !== $all['ai']['api_key'] ? self::masked_api_key() : '';
		return $all;
	}
}
