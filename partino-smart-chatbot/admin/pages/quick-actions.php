<?php
/**
 * Quick Actions admin page.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters; writes are nonce-checked.

$partino_page_title = __( 'اکشن‌های سریع', 'partino-smart-chatbot' );
require __DIR__ . '/partials/header.php';

$partino_edit     = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$partino_edit_row = $partino_edit > 0 ? Quick_Action::get( $partino_edit ) : null;
$partino_rows     = Quick_Action::get_list( false );
$partino_parts    = Part::get_list( false );
$partino_base     = admin_url( 'admin.php?page=partino-chatbot-quick-actions' );
?>

<p class="description">
	<?php esc_html_e( 'این دکمه‌ها در اولین پیام چت‌بات به کاربر نمایش داده می‌شوند و می‌توانند مستقیماً به یک قطعه متصل باشند.', 'partino-smart-chatbot' ); ?>
</p>

<div class="partino-detail-grid">
	<div class="partino-panel">
		<h2><?php echo $partino_edit_row ? esc_html__( 'ویرایش اکشن', 'partino-smart-chatbot' ) : esc_html__( 'افزودن اکشن', 'partino-smart-chatbot' ); ?></h2>

		<form method="post" action="<?php echo esc_url( $partino_base ); ?>">
			<?php wp_nonce_field( 'partino_admin_action', 'partino_nonce' ); ?>
			<input type="hidden" name="partino_action" value="qa_save" />
			<input type="hidden" name="id" value="<?php echo esc_attr( $partino_edit_row['id'] ?? 0 ); ?>" />

			<p>
				<label><?php esc_html_e( 'عنوان دکمه', 'partino-smart-chatbot' ); ?></label><br/>
				<input type="text" name="title" required value="<?php echo esc_attr( $partino_edit_row['title'] ?? '' ); ?>" class="regular-text" />
			</p>
			<p>
				<label><?php esc_html_e( 'قطعه مرتبط', 'partino-smart-chatbot' ); ?></label><br/>
				<select name="part_id">
					<option value="0"><?php esc_html_e( '— بدون قطعه (متن آزاد) —', 'partino-smart-chatbot' ); ?></option>
					<?php foreach ( $partino_parts as $partino_part ) : ?>
						<option value="<?php echo esc_attr( $partino_part['id'] ); ?>" <?php selected( (int) ( $partino_edit_row['part_id'] ?? 0 ), (int) $partino_part['id'] ); ?>>
							<?php echo esc_html( $partino_part['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label><?php esc_html_e( 'آیکون (اختیاری)', 'partino-smart-chatbot' ); ?></label><br/>
				<input type="text" name="icon" value="<?php echo esc_attr( $partino_edit_row['icon'] ?? '' ); ?>" class="regular-text" />
			</p>
			<p>
				<label><?php esc_html_e( 'ترتیب', 'partino-smart-chatbot' ); ?></label>
				<input type="number" name="sort_order" value="<?php echo esc_attr( $partino_edit_row['sort_order'] ?? 0 ); ?>" style="width:90px" />
				&nbsp;&nbsp;
				<label>
					<input type="checkbox" name="is_active" value="1" <?php checked( (int) ( $partino_edit_row['is_active'] ?? 1 ), 1 ); ?> />
					<?php esc_html_e( 'فعال', 'partino-smart-chatbot' ); ?>
				</label>
			</p>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'ذخیره', 'partino-smart-chatbot' ); ?></button>
				<?php if ( $partino_edit_row ) : ?>
					<a class="button" href="<?php echo esc_url( $partino_base ); ?>"><?php esc_html_e( 'انصراف', 'partino-smart-chatbot' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
	</div>

	<div class="partino-panel">
		<h2><?php esc_html_e( 'اکشن‌ها', 'partino-smart-chatbot' ); ?></h2>

		<?php if ( empty( $partino_rows ) ) : ?>
			<div class="partino-empty"><p><?php esc_html_e( 'اکشنی تعریف نشده است.', 'partino-smart-chatbot' ); ?></p></div>
		<?php else : ?>
			<table class="partino-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'عنوان', 'partino-smart-chatbot' ); ?></th>
						<th><?php esc_html_e( 'قطعه', 'partino-smart-chatbot' ); ?></th>
						<th><?php esc_html_e( 'ترتیب', 'partino-smart-chatbot' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'partino-smart-chatbot' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'partino-smart-chatbot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $partino_rows as $partino_row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $partino_row['title'] ); ?></strong></td>
							<td>
								<?php
								$partino_linked = $partino_row['part_id'] ? Part::get( (int) $partino_row['part_id'] ) : null;
								echo esc_html( $partino_linked['name'] ?? '—' );
								?>
							</td>
							<td><?php echo esc_html( $partino_row['sort_order'] ); ?></td>
							<td>
								<span class="partino-badge <?php echo (int) $partino_row['is_active'] ? 'partino-badge-completed' : 'partino-badge-cancelled'; ?>">
									<?php echo (int) $partino_row['is_active'] ? esc_html__( 'فعال', 'partino-smart-chatbot' ) : esc_html__( 'غیرفعال', 'partino-smart-chatbot' ); ?>
								</span>
							</td>
							<td class="partino-actions">
								<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit', (int) $partino_row['id'], $partino_base ) ); ?>"><?php esc_html_e( 'ویرایش', 'partino-smart-chatbot' ); ?></a>
								<?php
								$partino_del = wp_nonce_url(
									add_query_arg(
										array(
											'page'           => 'partino-chatbot-quick-actions',
											'partino_action' => 'qa_delete',
											'id'             => (int) $partino_row['id'],
										),
										admin_url( 'admin.php' )
									),
									'partino_admin_action',
									'partino_nonce'
								);
								?>
								<a class="button button-small partino-danger" href="<?php echo esc_url( $partino_del ); ?>" data-partino-confirm="delete"><?php esc_html_e( 'حذف', 'partino-smart-chatbot' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
</div>
