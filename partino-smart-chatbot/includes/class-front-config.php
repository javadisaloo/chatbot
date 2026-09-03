<?php
/**
 * Frontend widget configuration builder (public-safe, no secrets).
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the config object handed to chatbot.js.
 */
final class Front_Config {

	/**
	 * Build the public config array. NEVER include the API key or any secret.
	 *
	 * @return array
	 */
	public static function build(): array {
		$s = Settings::all();

		$quick_actions = array();
		if ( ! empty( $s['chatbot']['quick_actions'] ) ) {
			foreach ( Quick_Action::get_list( true ) as $qa ) {
				$quick_actions[] = array(
					'id'    => 'qa:' . (int) $qa['id'],
					'label' => (string) $qa['title'],
					'icon'  => (string) $qa['icon'],
				);
			}
		}

		$config = array(
			'enabled'      => (bool) $s['general']['enabled'],
			'autoOpen'     => (bool) $s['general']['auto_open'],
			'openDelay'    => (int) $s['general']['open_delay'],
			'showMobile'   => (bool) $s['general']['show_mobile'],
			'showDesktop'  => (bool) $s['general']['show_desktop'],
			'position'     => (string) $s['general']['position'],
			'rtl'          => (bool) $s['general']['rtl'],
			'appearance'   => array(
				'primaryColor'    => (string) $s['appearance']['primary_color'],
				'secondaryColor'  => (string) $s['appearance']['secondary_color'],
				'backgroundColor' => (string) $s['appearance']['background_color'],
				'textColor'       => (string) $s['appearance']['text_color'],
				'borderColor'     => (string) $s['appearance']['border_color'],
				'borderRadius'    => (int) $s['appearance']['border_radius'],
				'chatWidth'       => (int) $s['appearance']['chat_width'],
				'chatHeight'      => (int) $s['appearance']['chat_height'],
				'headerHeight'    => (int) $s['appearance']['header_height'],
				'buttonRadius'    => (int) $s['appearance']['button_radius'],
				'fontSize'        => (int) $s['appearance']['font_size'],
				'logoUrl'         => (string) $s['appearance']['logo_url'],
				'title'           => (string) $s['appearance']['title'],
				'subtitle'        => (string) $s['appearance']['subtitle'],
				'badgeText'       => (string) $s['appearance']['badge_text'],
				'placeholder'     => (string) $s['appearance']['placeholder'],
				'launcherColor'   => (string) $s['appearance']['launcher_color'],
			),
			'behavior'     => array(
				'typingIndicator' => (bool) $s['chatbot']['typing_indicator'],
				'sound'           => (bool) $s['chatbot']['sound'],
				'autoScroll'      => (bool) $s['chatbot']['auto_scroll'],
				'animation'       => (bool) $s['chatbot']['animation'],
				'voiceInput'      => (bool) $s['chatbot']['voice_input'],
				'showTimestamp'   => (bool) $s['chatbot']['show_timestamp'],
			),
			'quickActions' => $quick_actions,
			'strings'      => array(
				'send'        => __( 'ارسال پیام', 'partino-smart-chatbot' ),
				'close'       => __( 'بستن چت', 'partino-smart-chatbot' ),
				'open'        => __( 'باز کردن چت', 'partino-smart-chatbot' ),
				'mic'         => __( 'ضبط پیام صوتی', 'partino-smart-chatbot' ),
				'micUnsupported' => __( 'مرورگر شما از ورودی صوتی پشتیبانی نمی‌کند.', 'partino-smart-chatbot' ),
				'menu'        => __( 'منو', 'partino-smart-chatbot' ),
				'quickBuy'    => __( 'خرید سریع', 'partino-smart-chatbot' ),
				'newRequest'  => __( 'درخواست جدید', 'partino-smart-chatbot' ),
				'checkStatus' => __( 'بررسی وضعیت', 'partino-smart-chatbot' ),
				'networkError' => __( "متأسفانه ارتباط با سرور برقرار نشد.\nلطفاً دوباره تلاش کنید.", 'partino-smart-chatbot' ),
				'retry'       => __( 'تلاش مجدد', 'partino-smart-chatbot' ),
				'now'         => __( 'الان', 'partino-smart-chatbot' ),
				'submitPhone' => __( 'ثبت استعلام', 'partino-smart-chatbot' ),
				'phonePlaceholder' => __( 'شماره موبایل', 'partino-smart-chatbot' ),
			),
		);

		/**
		 * Filter the public chatbot config.
		 *
		 * @param array $config Public config (must stay secret-free).
		 */
		return (array) apply_filters( 'partino_chatbot_config', $config );
	}
}
