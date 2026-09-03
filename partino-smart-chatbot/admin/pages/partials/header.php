<?php
/**
 * Shared admin header + notices.
 *
 * @package Partino\Chatbot
 *
 * @var string $partino_page_title Page title (set by including page).
 */

defined( 'ABSPATH' ) || exit;

$partino_notice = isset( $_GET['partino_notice'] ) ? sanitize_key( wp_unslash( $_GET['partino_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$partino_notices = array(
	'saved'          => array( 'success', __( 'با موفقیت ذخیره شد.', 'partino-smart-chatbot' ) ),
	'deleted'        => array( 'success', __( 'با موفقیت حذف شد.', 'partino-smart-chatbot' ) ),
	'status-updated' => array( 'success', __( 'وضعیت به‌روزرسانی شد.', 'partino-smart-chatbot' ) ),
	'bulk-done'      => array( 'success', __( 'عملیات گروهی انجام شد.', 'partino-smart-chatbot' ) ),
	'settings-reset' => array( 'success', __( 'تنظیمات به مقادیر پیش‌فرض بازگشت.', 'partino-smart-chatbot' ) ),
	'imported'       => array( 'success', __( 'تنظیمات با موفقیت وارد شد.', 'partino-smart-chatbot' ) ),
	'import-error'   => array( 'error', __( 'فایل واردشده معتبر نیست.', 'partino-smart-chatbot' ) ),
	'logs-cleared'   => array( 'success', __( 'لاگ‌ها پاک شدند.', 'partino-smart-chatbot' ) ),
);
?>
<div class="wrap partino-admin" dir="rtl">
	<h1 class="partino-admin-title"><?php echo esc_html( $partino_page_title ?? '' ); ?></h1>

	<?php if ( '' !== $partino_notice && isset( $partino_notices[ $partino_notice ] ) ) : ?>
		<div class="partino-notice partino-notice-<?php echo esc_attr( $partino_notices[ $partino_notice ][0] ); ?>">
			<?php echo esc_html( $partino_notices[ $partino_notice ][1] ); ?>
		</div>
	<?php endif; ?>
