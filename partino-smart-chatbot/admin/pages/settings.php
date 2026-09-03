<?php
/**
 * Settings admin page (tabbed) with live preview.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- tab switch only; saves are nonce-checked.

$partino_page_title = __( 'تنظیمات Partino Chatbot', 'partino-smart-chatbot' );
require __DIR__ . '/partials/header.php';

$partino_s   = Settings::all();
$partino_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

$partino_tabs = array(
	'general'       => __( 'عمومی', 'partino-smart-chatbot' ),
	'appearance'    => __( 'ظاهر', 'partino-smart-chatbot' ),
	'chatbot'       => __( 'چت‌بات', 'partino-smart-chatbot' ),
	'ai'            => __( 'هوش مصنوعی', 'partino-smart-chatbot' ),
	'conversation'  => __( 'گفتگو', 'partino-smart-chatbot' ),
	'lead'          => __( 'سرنخ / استعلام', 'partino-smart-chatbot' ),
	'privacy'       => __( 'حریم خصوصی', 'partino-smart-chatbot' ),
	'notifications' => __( 'اعلان‌ها', 'partino-smart-chatbot' ),
	'advanced'      => __( 'پیشرفته', 'partino-smart-chatbot' ),
);
if ( ! isset( $partino_tabs[ $partino_tab ] ) ) {
	$partino_tab = 'general';
}

$partino_base = admin_url( 'admin.php?page=partino-chatbot-settings' );
?>

<nav class="partino-tabs">
	<?php foreach ( $partino_tabs as $partino_key => $partino_label ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'tab', $partino_key, $partino_base ) ); ?>" class="<?php echo $partino_tab === $partino_key ? 'active' : ''; ?>">
			<?php echo esc_html( $partino_label ); ?>
		</a>
	<?php endforeach; ?>
</nav>

<form method="post" action="<?php echo esc_url( add_query_arg( 'tab', $partino_tab, $partino_base ) ); ?>" class="partino-settings-form">
	<?php wp_nonce_field( 'partino_admin_action', 'partino_nonce' ); ?>
	<input type="hidden" name="partino_action" value="save_settings" />

	<div class="partino-settings-layout">
		<div class="partino-panel partino-settings-panel">

		<?php if ( 'general' === $partino_tab ) : ?>
			<h2><?php esc_html_e( 'تنظیمات عمومی', 'partino-smart-chatbot' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'فعال بودن چت‌بات', 'partino-smart-chatbot' ); ?></th>
					<td><label><input type="checkbox" name="partino_settings[general][enabled]" value="1" <?php checked( $partino_s['general']['enabled'] ); ?> /> <?php esc_html_e( 'نمایش چت‌بات در سایت', 'partino-smart-chatbot' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'باز شدن خودکار', 'partino-smart-chatbot' ); ?></th>
					<td><label><input type="checkbox" name="partino_settings[general][auto_open]" value="1" <?php checked( $partino_s['general']['auto_open'] ); ?> /> <?php esc_html_e( 'چت‌بات بعد از تأخیر مشخص باز شود', 'partino-smart-chatbot' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="partino-open-delay"><?php esc_html_e( 'تأخیر باز شدن (میلی‌ثانیه)', 'partino-smart-chatbot' ); ?></label></th>
					<td>
						<select id="partino-open-delay" name="partino_settings[general][open_delay]">
							<?php foreach ( array( 0, 1000, 2000, 3000, 5000, 10000 ) as $partino_d ) : ?>
								<option value="<?php echo esc_attr( $partino_d ); ?>" <?php selected( (int) $partino_s['general']['open_delay'], $partino_d ); ?>><?php echo esc_html( number_format_i18n( $partino_d ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'نمایش در موبایل', 'partino-smart-chatbot' ); ?></th>
					<td><label><input type="checkbox" name="partino_settings[general][show_mobile]" value="1" <?php checked( $partino_s['general']['show_mobile'] ); ?> /> <?php esc_html_e( 'فعال', 'partino-smart-chatbot' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'نمایش در دسکتاپ', 'partino-smart-chatbot' ); ?></th>
					<td><label><input type="checkbox" name="partino_settings[general][show_desktop]" value="1" <?php checked( $partino_s['general']['show_desktop'] ); ?> /> <?php esc_html_e( 'فعال', 'partino-smart-chatbot' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'موقعیت', 'partino-smart-chatbot' ); ?></th>
					<td>
						<select name="partino_settings[general][position]">
							<option value="bottom_left" <?php selected( $partino_s['general']['position'], 'bottom_left' ); ?>><?php esc_html_e( 'پایین چپ', 'partino-smart-chatbot' ); ?></option>
							<option value="bottom_right" <?php selected( $partino_s['general']['position'], 'bottom_right' ); ?>><?php esc_html_e( 'پایین راست', 'partino-smart-chatbot' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'در سایت‌های راست‌به‌چپ معمولاً «پایین چپ» مناسب‌تر است.', 'partino-smart-chatbot' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'راست‌به‌چپ (RTL)', 'partino-smart-chatbot' ); ?></th>
					<td><label><input type="checkbox" name="partino_settings[general][rtl]" value="1" <?php checked( $partino_s['general']['rtl'] ); ?> /> <?php esc_html_e( 'فعال', 'partino-smart-chatbot' ); ?></label></td>
				</tr>
			</table>

		<?php elseif ( 'appearance' === $partino_tab ) : ?>
			<h2><?php esc_html_e( 'ظاهر', 'partino-smart-chatbot' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				$partino_colors = array(
					'primary_color'    => __( 'رنگ اصلی', 'partino-smart-chatbot' ),
					'secondary_color'  => __( 'رنگ ثانویه', 'partino-smart-chatbot' ),
					'background_color' => __( 'رنگ پس‌زمینه بدنه', 'partino-smart-chatbot' ),
					'text_color'       => __( 'رنگ متن', 'partino-smart-chatbot' ),
					'border_color'     => __( 'رنگ حاشیه', 'partino-smart-chatbot' ),
					'launcher_color'   => __( 'رنگ دکمه شناور', 'partino-smart-chatbot' ),
				);
				foreach ( $partino_colors as $partino_key => $partino_label ) :
					?>
					<tr>
						<th><label><?php echo esc_html( $partino_label ); ?></label></th>
						<td><input type="color" name="partino_settings[appearance][<?php echo esc_attr( $partino_key ); ?>]" value="<?php echo esc_attr( $partino_s['appearance'][ $partino_key ] ); ?>" data-partino-preview="<?php echo esc_attr( $partino_key ); ?>" /></td>
					</tr>
				<?php endforeach; ?>
				<?php
				$partino_nums = array(
					'border_radius' => array( __( 'گردی گوشه کارت‌ها (px)', 'partino-smart-chatbot' ), 0, 60 ),
					'button_radius' => array( __( 'گردی دکمه‌ها (px)', 'partino-smart-chatbot' ), 0, 40 ),
					'chat_width'    => array( __( 'عرض پنجره (px)', 'partino-smart-chatbot' ), 300, 600 ),
					'chat_height'   => array( __( 'ارتفاع پنجره (px)', 'partino-smart-chatbot' ), 400, 900 ),
					'header_height' => array( __( 'ارتفاع هدر (px)', 'partino-smart-chatbot' ), 56, 140 ),
					'font_size'     => array( __( 'اندازه فونت (px)', 'partino-smart-chatbot' ), 11, 20 ),
				);
				foreach ( $partino_nums as $partino_key => $partino_cfg ) :
					?>
					<tr>
						<th><label><?php echo esc_html( $partino_cfg[0] ); ?></label></th>
						<td><input type="number" min="<?php echo esc_attr( $partino_cfg[1] ); ?>" max="<?php echo esc_attr( $partino_cfg[2] ); ?>" name="partino_settings[appearance][<?php echo esc_attr( $partino_key ); ?>]" value="<?php echo esc_attr( $partino_s['appearance'][ $partino_key ] ); ?>" data-partino-preview="<?php echo esc_attr( $partino_key ); ?>" style="width:110px" /></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th><label><?php esc_html_e( 'لوگو', 'partino-smart-chatbot' ); ?></label></th>
					<td>
						<input type="url" name="partino_settings[appearance][logo_url]" id="partino-logo-url" value="<?php echo esc_attr( $partino_s['appearance']['logo_url'] ); ?>" class="regular-text" dir="ltr" data-partino-preview="logo_url" />
						<button type="button" class="button" id="partino-logo-upload"><?php esc_html_e( 'انتخاب از رسانه', 'partino-smart-chatbot' ); ?></button>
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'عنوان', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="text" name="partino_settings[appearance][title]" value="<?php echo esc_attr( $partino_s['appearance']['title'] ); ?>" class="regular-text" data-partino-preview="title" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'زیرعنوان', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="text" name="partino_settings[appearance][subtitle]" value="<?php echo esc_attr( $partino_s['appearance']['subtitle'] ); ?>" class="regular-text" data-partino-preview="subtitle" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'متن Badge', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="text" name="partino_settings[appearance][badge_text]" value="<?php echo esc_attr( $partino_s['appearance']['badge_text'] ); ?>" class="regular-text" data-partino-preview="badge_text" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'پیام خوش‌آمدگویی', 'partino-smart-chatbot' ); ?></label></th>
					<td><textarea name="partino_settings[appearance][welcome_message]" rows="4" class="large-text" data-partino-preview="welcome_message"><?php echo esc_textarea( $partino_s['appearance']['welcome_message'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Placeholder ورودی', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="text" name="partino_settings[appearance][placeholder]" value="<?php echo esc_attr( $partino_s['appearance']['placeholder'] ); ?>" class="regular-text" data-partino-preview="placeholder" /></td>
				</tr>
			</table>

		<?php elseif ( 'chatbot' === $partino_tab ) : ?>
			<h2><?php esc_html_e( 'رفتار چت‌بات', 'partino-smart-chatbot' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				$partino_flags = array(
					'typing_indicator' => __( 'نشانگر در حال تایپ', 'partino-smart-chatbot' ),
					'sound'            => __( 'صدا', 'partino-smart-chatbot' ),
					'auto_scroll'      => __( 'اسکرول خودکار', 'partino-smart-chatbot' ),
					'animation'        => __( 'انیمیشن پیام‌ها', 'partino-smart-chatbot' ),
					'voice_input'      => __( 'ورودی صوتی (Web Speech API)', 'partino-smart-chatbot' ),
					'quick_actions'    => __( 'اکشن‌های سریع', 'partino-smart-chatbot' ),
					'show_timestamp'   => __( 'نمایش ساعت پیام', 'partino-smart-chatbot' ),
				);
				foreach ( $partino_flags as $partino_key => $partino_label ) :
					?>
					<tr>
						<th><?php echo esc_html( $partino_label ); ?></th>
						<td><label><input type="checkbox" name="partino_settings[chatbot][<?php echo esc_attr( $partino_key ); ?>]" value="1" <?php checked( $partino_s['chatbot'][ $partino_key ] ); ?> /> <?php esc_html_e( 'فعال', 'partino-smart-chatbot' ); ?></label></td>
					</tr>
				<?php endforeach; ?>
			</table>

		<?php elseif ( 'ai' === $partino_tab ) : ?>
			<h2><?php esc_html_e( 'هوش مصنوعی (OpenRouter)', 'partino-smart-chatbot' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'فعال‌سازی AI', 'partino-smart-chatbot' ); ?></th>
					<td>
						<label><input type="checkbox" name="partino_settings[ai][enabled]" value="1" <?php checked( $partino_s['ai']['enabled'] ); ?> /> <?php esc_html_e( 'استفاده از OpenRouter برای فهم زبان طبیعی', 'partino-smart-chatbot' ); ?></label>
						<p class="description"><?php esc_html_e( 'در صورت غیرفعال بودن یا خطای AI، جریان قاعده‌محور داخلی به‌صورت خودکار استفاده می‌شود.', 'partino-smart-chatbot' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="partino-api-key"><?php esc_html_e( 'کلید API', 'partino-smart-chatbot' ); ?></label></th>
					<td>
						<input type="password" id="partino-api-key" name="partino_settings[ai][api_key]" value="<?php echo esc_attr( Settings::masked_api_key() ); ?>" class="regular-text" dir="ltr" autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'کلید به‌صورت Mask نمایش داده می‌شود. برای تغییر، مقدار جدید را کامل وارد کنید. کلید هرگز به فرانت‌اند ارسال نمی‌شود.', 'partino-smart-chatbot' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'مدل', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="text" name="partino_settings[ai][model]" value="<?php echo esc_attr( $partino_s['ai']['model'] ); ?>" class="regular-text" dir="ltr" placeholder="openai/gpt-4o-mini" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Temperature', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="number" step="0.1" min="0" max="2" name="partino_settings[ai][temperature]" value="<?php echo esc_attr( $partino_s['ai']['temperature'] ); ?>" style="width:100px" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Max Tokens', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="number" min="50" max="4000" name="partino_settings[ai][max_tokens]" value="<?php echo esc_attr( $partino_s['ai']['max_tokens'] ); ?>" style="width:110px" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Timeout (ثانیه)', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="number" min="3" max="60" name="partino_settings[ai][timeout]" value="<?php echo esc_attr( $partino_s['ai']['timeout'] ); ?>" style="width:100px" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'تعداد تلاش مجدد', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="number" min="0" max="3" name="partino_settings[ai][retry]" value="<?php echo esc_attr( $partino_s['ai']['retry'] ); ?>" style="width:80px" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'System Prompt (اختیاری)', 'partino-smart-chatbot' ); ?></label></th>
					<td>
						<textarea name="partino_settings[ai][system_prompt]" rows="6" class="large-text" dir="ltr" placeholder="<?php esc_attr_e( 'خالی بگذارید تا از Prompt پیش‌فرض استفاده شود.', 'partino-smart-chatbot' ); ?>"><?php echo esc_textarea( $partino_s['ai']['system_prompt'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'تست اتصال', 'partino-smart-chatbot' ); ?></th>
					<td>
						<button type="button" class="button" id="partino-test-ai"><?php esc_html_e( 'تست اتصال OpenRouter', 'partino-smart-chatbot' ); ?></button>
						<span id="partino-test-ai-result" role="status"></span>
						<p class="description"><?php esc_html_e( 'ابتدا کلید را ذخیره کنید، سپس تست بگیرید.', 'partino-smart-chatbot' ); ?></p>
					</td>
				</tr>
			</table>

		<?php elseif ( 'conversation' === $partino_tab ) : ?>
			<h2><?php esc_html_e( 'گفتگو', 'partino-smart-chatbot' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label><?php esc_html_e( 'حداکثر تعداد پیام هر گفتگو', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="number" min="10" max="1000" name="partino_settings[conversation][max_length]" value="<?php echo esc_attr( $partino_s['conversation']['max_length'] ); ?>" style="width:110px" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'مهلت نشست (دقیقه)', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="number" min="10" max="10080" name="partino_settings[conversation][session_timeout]" value="<?php echo esc_attr( $partino_s['conversation']['session_timeout'] ); ?>" style="width:110px" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'نگهداری گفتگوهای بدون استعلام (روز)', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="number" min="0" max="3650" name="partino_settings[conversation][retention_days]" value="<?php echo esc_attr( $partino_s['conversation']['retention_days'] ); ?>" style="width:110px" />
					<p class="description"><?php esc_html_e( '۰ به معنی نگهداری نامحدود است.', 'partino-smart-chatbot' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'ریست بعد از ثبت', 'partino-smart-chatbot' ); ?></th>
					<td><label><input type="checkbox" name="partino_settings[conversation][reset_after_submit]" value="1" <?php checked( $partino_s['conversation']['reset_after_submit'] ); ?> /> <?php esc_html_e( 'بعد از ثبت استعلام، گفتگو بسته و جریان جدید آغاز شود', 'partino-smart-chatbot' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'امکان اصلاح پاسخ‌ها', 'partino-smart-chatbot' ); ?></th>
					<td><label><input type="checkbox" name="partino_settings[conversation][allow_edit]" value="1" <?php checked( $partino_s['conversation']['allow_edit'] ); ?> /> <?php esc_html_e( 'کاربر بتواند خودرو/سال/قطعه را قبل از ثبت اصلاح کند', 'partino-smart-chatbot' ); ?></label></td>
				</tr>
			</table>

		<?php elseif ( 'lead' === $partino_tab ) : ?>
			<h2><?php esc_html_e( 'سرنخ / استعلام', 'partino-smart-chatbot' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'شماره موبایل اجباری', 'partino-smart-chatbot' ); ?></th>
					<td><label><input type="checkbox" name="partino_settings[lead][phone_required]" value="1" <?php checked( $partino_s['lead']['phone_required'] ); ?> /> <?php esc_html_e( 'فعال', 'partino-smart-chatbot' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'اعتبارسنجی شماره ایرانی', 'partino-smart-chatbot' ); ?></th>
					<td><label><input type="checkbox" name="partino_settings[lead][phone_validation]" value="1" <?php checked( $partino_s['lead']['phone_validation'] ); ?> /> <?php esc_html_e( 'فعال (09xxxxxxxxx)', 'partino-smart-chatbot' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'رفتار استعلام تکراری', 'partino-smart-chatbot' ); ?></th>
					<td>
						<select name="partino_settings[lead][duplicate_behavior]">
							<option value="allow" <?php selected( $partino_s['lead']['duplicate_behavior'], 'allow' ); ?>><?php esc_html_e( 'اجازه ثبت', 'partino-smart-chatbot' ); ?></option>
							<option value="reject" <?php selected( $partino_s['lead']['duplicate_behavior'], 'reject' ); ?>><?php esc_html_e( 'جلوگیری از تکرار ۲۴ ساعته', 'partino-smart-chatbot' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'پیام موفقیت', 'partino-smart-chatbot' ); ?></label></th>
					<td><textarea name="partino_settings[lead][success_message]" rows="3" class="large-text"><?php echo esc_textarea( $partino_s['lead']['success_message'] ); ?></textarea></td>
				</tr>
			</table>

		<?php elseif ( 'privacy' === $partino_tab ) : ?>
			<h2><?php esc_html_e( 'حریم خصوصی', 'partino-smart-chatbot' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'حالت حریم خصوصی', 'partino-smart-chatbot' ); ?></th>
					<td>
						<select name="partino_settings[privacy][mode]">
							<option value="minimal" <?php selected( $partino_s['privacy']['mode'], 'minimal' ); ?>><?php esc_html_e( 'حداقلی — بدون IP و User Agent', 'partino-smart-chatbot' ); ?></option>
							<option value="standard" <?php selected( $partino_s['privacy']['mode'], 'standard' ); ?>><?php esc_html_e( 'استاندارد — هش IP و User Agent', 'partino-smart-chatbot' ); ?></option>
							<option value="extended" <?php selected( $partino_s['privacy']['mode'], 'extended' ); ?>><?php esc_html_e( 'گسترده — استاندارد + Referrer', 'partino-smart-chatbot' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'IP هرگز به‌صورت خام ذخیره نمی‌شود؛ فقط هش نمک‌دار (برای Rate Limit و ضدتقلب).', 'partino-smart-chatbot' ); ?></p>
					</td>
				</tr>
			</table>

		<?php elseif ( 'notifications' === $partino_tab ) : ?>
			<h2><?php esc_html_e( 'اعلان‌ها', 'partino-smart-chatbot' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'اعلان ایمیلی', 'partino-smart-chatbot' ); ?></th>
					<td><label><input type="checkbox" name="partino_settings[notifications][email_enabled]" value="1" <?php checked( $partino_s['notifications']['email_enabled'] ); ?> /> <?php esc_html_e( 'ارسال ایمیل پس از ثبت هر استعلام', 'partino-smart-chatbot' ); ?></label></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'ایمیل مدیر', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="email" name="partino_settings[notifications][admin_email]" value="<?php echo esc_attr( $partino_s['notifications']['admin_email'] ); ?>" class="regular-text" dir="ltr" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'موضوع', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="text" name="partino_settings[notifications][subject]" value="<?php echo esc_attr( $partino_s['notifications']['subject'] ); ?>" class="large-text" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'قالب پیام', 'partino-smart-chatbot' ); ?></label></th>
					<td>
						<textarea name="partino_settings[notifications][template]" rows="6" class="large-text"><?php echo esc_textarea( $partino_s['notifications']['template'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'متغیرها: {phone} {part} {vehicle} {year} {id} {admin_url}', 'partino-smart-chatbot' ); ?></p>
					</td>
				</tr>
			</table>
			<p class="description"><?php esc_html_e( 'برای اتصال SMS از هوک partino_inquiry_notification استفاده کنید (مستندات README).', 'partino-smart-chatbot' ); ?></p>

		<?php elseif ( 'advanced' === $partino_tab ) : ?>
			<h2><?php esc_html_e( 'پیشرفته', 'partino-smart-chatbot' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label><?php esc_html_e( 'سقف درخواست در دقیقه (هر IP)', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="number" min="5" max="600" name="partino_settings[advanced][rate_limit_per_minute]" value="<?php echo esc_attr( $partino_s['advanced']['rate_limit_per_minute'] ); ?>" style="width:110px" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'مدت مسدودسازی موقت (دقیقه)', 'partino-smart-chatbot' ); ?></label></th>
					<td><input type="number" min="1" max="1440" name="partino_settings[advanced][block_minutes]" value="<?php echo esc_attr( $partino_s['advanced']['block_minutes'] ); ?>" style="width:110px" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'لاگ Debug', 'partino-smart-chatbot' ); ?></th>
					<td><label><input type="checkbox" name="partino_settings[advanced][debug_log]" value="1" <?php checked( $partino_s['advanced']['debug_log'] ); ?> /> <?php esc_html_e( 'فعال (فقط برای عیب‌یابی)', 'partino-smart-chatbot' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'حذف داده‌ها هنگام Uninstall', 'partino-smart-chatbot' ); ?></th>
					<td>
						<label><input type="checkbox" name="partino_settings[advanced][delete_data_on_uninstall]" value="1" <?php checked( $partino_s['advanced']['delete_data_on_uninstall'] ); ?> /> <?php esc_html_e( 'در صورت حذف کامل افزونه، جداول و تنظیمات نیز حذف شوند', 'partino-smart-chatbot' ); ?></label>
						<p class="description"><?php esc_html_e( 'پیش‌فرض: خاموش — داده‌ها حفظ می‌شوند.', 'partino-smart-chatbot' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Import / Export / Reset', 'partino-smart-chatbot' ); ?></h3>
			<p>
				<?php
				$partino_export_settings_url = wp_nonce_url(
					add_query_arg(
						array(
							'page'           => 'partino-chatbot-settings',
							'partino_action' => 'export_settings',
						),
						admin_url( 'admin.php' )
					),
					'partino_admin_action',
					'partino_nonce'
				);
				$partino_reset_url = wp_nonce_url(
					add_query_arg(
						array(
							'page'           => 'partino-chatbot-settings',
							'partino_action' => 'reset_settings',
						),
						admin_url( 'admin.php' )
					),
					'partino_admin_action',
					'partino_nonce'
				);
				?>
				<a class="button" href="<?php echo esc_url( $partino_export_settings_url ); ?>"><?php esc_html_e( 'خروجی JSON تنظیمات', 'partino-smart-chatbot' ); ?></a>
				<a class="button partino-danger" href="<?php echo esc_url( $partino_reset_url ); ?>" data-partino-confirm="reset"><?php esc_html_e( 'بازنشانی تنظیمات', 'partino-smart-chatbot' ); ?></a>
			</p>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'ذخیره تنظیمات', 'partino-smart-chatbot' ); ?></button>
		</p>
		</div>

		<?php if ( 'appearance' === $partino_tab ) : ?>
			<div class="partino-panel partino-preview-panel">
				<h2><?php esc_html_e( 'پیش‌نمایش زنده', 'partino-smart-chatbot' ); ?></h2>
				<div class="partino-preview" id="partino-preview"
					data-primary="<?php echo esc_attr( $partino_s['appearance']['primary_color'] ); ?>"
					data-bg="<?php echo esc_attr( $partino_s['appearance']['background_color'] ); ?>">
					<div class="partino-preview-window">
						<div class="partino-preview-header">
							<div class="partino-preview-close">✕</div>
							<div class="partino-preview-logo"><img src="" alt="" style="display:none" /><span>پ</span></div>
							<div class="partino-preview-titles">
								<strong data-preview-bind="title"><?php echo esc_html( $partino_s['appearance']['title'] ); ?></strong>
								<small data-preview-bind="subtitle"><?php echo esc_html( $partino_s['appearance']['subtitle'] ); ?></small>
							</div>
						</div>
						<div class="partino-preview-body">
							<div class="partino-preview-card">
								<span class="partino-preview-badge" data-preview-bind="badge_text"><?php echo esc_html( $partino_s['appearance']['badge_text'] ); ?></span>
								<p data-preview-bind="welcome_message"><?php echo esc_html( $partino_s['appearance']['welcome_message'] ); ?></p>
							</div>
							<div class="partino-preview-options">
								<span>لنت ترمز خودروی من</span>
								<span>روغن و فیلتر روغن</span>
							</div>
						</div>
						<div class="partino-preview-input">
							<span class="partino-preview-send">↑</span>
							<em data-preview-bind="placeholder"><?php echo esc_html( $partino_s['appearance']['placeholder'] ); ?></em>
							<span class="partino-preview-mic">🎙</span>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</form>

<?php if ( 'advanced' === $partino_tab ) : ?>
	<div class="partino-panel">
		<h2><?php esc_html_e( 'ورود تنظیمات از JSON', 'partino-smart-chatbot' ); ?></h2>
		<form method="post" action="<?php echo esc_url( add_query_arg( 'tab', 'advanced', $partino_base ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'partino_admin_action', 'partino_nonce' ); ?>
			<input type="hidden" name="partino_action" value="import_settings" />
			<input type="file" name="partino_import_file" accept="application/json" required />
			<button type="submit" class="button"><?php esc_html_e( 'ورود تنظیمات', 'partino-smart-chatbot' ); ?></button>
			<p class="description"><?php esc_html_e( 'کلید API از فایل‌های Export شده وارد نمی‌شود (Mask شده است).', 'partino-smart-chatbot' ); ?></p>
		</form>
	</div>
<?php endif; ?>
</div>
