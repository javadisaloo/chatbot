<?php
/**
 * Dashboard admin page.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

$partino_page_title = __( 'داشبورد Partino Chatbot', 'partino-smart-chatbot' );
require __DIR__ . '/partials/header.php';

$partino_stats  = Inquiry::stats();
$partino_latest = Inquiry::query( array( 'per_page' => 8, 'page' => 1 ) );
$partino_status_labels = Inquiry::statuses();
?>

<div class="partino-cards">
	<div class="partino-card">
		<span class="partino-card-value"><?php echo esc_html( number_format_i18n( $partino_stats['total'] ) ); ?></span>
		<span class="partino-card-label"><?php esc_html_e( 'کل استعلام‌ها', 'partino-smart-chatbot' ); ?></span>
	</div>
	<div class="partino-card partino-card-accent">
		<span class="partino-card-value"><?php echo esc_html( number_format_i18n( $partino_stats['new'] ) ); ?></span>
		<span class="partino-card-label"><?php esc_html_e( 'استعلام‌های جدید', 'partino-smart-chatbot' ); ?></span>
	</div>
	<div class="partino-card">
		<span class="partino-card-value"><?php echo esc_html( number_format_i18n( $partino_stats['today'] ) ); ?></span>
		<span class="partino-card-label"><?php esc_html_e( 'امروز', 'partino-smart-chatbot' ); ?></span>
	</div>
	<div class="partino-card">
		<span class="partino-card-value"><?php echo esc_html( number_format_i18n( $partino_stats['week'] ) ); ?></span>
		<span class="partino-card-label"><?php esc_html_e( 'این هفته', 'partino-smart-chatbot' ); ?></span>
	</div>
	<div class="partino-card">
		<span class="partino-card-value"><?php echo esc_html( number_format_i18n( $partino_stats['month'] ) ); ?></span>
		<span class="partino-card-label"><?php esc_html_e( 'این ماه', 'partino-smart-chatbot' ); ?></span>
	</div>
	<div class="partino-card">
		<span class="partino-card-value"><?php echo esc_html( $partino_stats['conversion'] ); ?>%</span>
		<span class="partino-card-label"><?php esc_html_e( 'نرخ تبدیل گفتگو به استعلام', 'partino-smart-chatbot' ); ?></span>
	</div>
	<div class="partino-card">
		<span class="partino-card-value"><?php echo esc_html( number_format_i18n( $partino_stats['ai_requests'] ) ); ?></span>
		<span class="partino-card-label"><?php esc_html_e( 'درخواست‌های AI', 'partino-smart-chatbot' ); ?></span>
	</div>
	<div class="partino-card">
		<span class="partino-card-value"><?php echo esc_html( number_format_i18n( $partino_stats['ai_errors'] ) ); ?></span>
		<span class="partino-card-label"><?php esc_html_e( 'خطاهای AI', 'partino-smart-chatbot' ); ?></span>
	</div>
</div>

<div class="partino-panel">
	<h2><?php esc_html_e( 'آخرین استعلام‌ها', 'partino-smart-chatbot' ); ?></h2>

	<?php if ( empty( $partino_latest['items'] ) ) : ?>
		<div class="partino-empty">
			<span class="dashicons dashicons-format-chat"></span>
			<p><?php esc_html_e( 'هنوز استعلامی ثبت نشده است. به محض ثبت اولین استعلام، اینجا نمایش داده می‌شود.', 'partino-smart-chatbot' ); ?></p>
		</div>
	<?php else : ?>
		<table class="partino-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'شناسه', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'تاریخ', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'شماره مشتری', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'قطعه', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'خودرو', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'partino-smart-chatbot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $partino_latest['items'] as $partino_row ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=partino-chatbot-inquiries&action=view&inquiry=' . (int) $partino_row['id'] ) ); ?>">
								#<?php echo esc_html( $partino_row['id'] ); ?>
							</a>
						</td>
						<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $partino_row['created_at'] ) ); ?></td>
						<td dir="ltr"><?php echo esc_html( partino_chatbot_mask_phone( (string) $partino_row['customer_phone'] ) ); ?></td>
						<td><?php echo esc_html( $partino_row['part_name'] ); ?></td>
						<td><?php echo esc_html( trim( $partino_row['vehicle_brand'] . ' ' . $partino_row['vehicle_model'] . ' ' . $partino_row['vehicle_trim'] . ' ' . $partino_row['vehicle_year'] ) ); ?></td>
						<td>
							<span class="partino-badge partino-badge-<?php echo esc_attr( $partino_row['status'] ); ?>">
								<?php echo esc_html( $partino_status_labels[ $partino_row['status'] ] ?? $partino_row['status'] ); ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
</div>
