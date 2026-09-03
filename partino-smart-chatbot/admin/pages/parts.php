<?php
/**
 * Parts admin page.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters; writes are nonce-checked.

$partino_page_title = __( 'مدیریت قطعات', 'partino-smart-chatbot' );
require __DIR__ . '/partials/header.php';

$partino_edit     = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$partino_edit_row = $partino_edit > 0 ? Part::get( $partino_edit ) : null;
$partino_rows     = Part::get_list( false );
$partino_base     = admin_url( 'admin.php?page=partino-chatbot-parts' );
?>

<div class="partino-detail-grid">
	<div class="partino-panel">
		<h2><?php echo $partino_edit_row ? esc_html__( 'ویرایش قطعه', 'partino-smart-chatbot' ) : esc_html__( 'افزودن قطعه', 'partino-smart-chatbot' ); ?></h2>

		<form method="post" action="<?php echo esc_url( $partino_base ); ?>">
			<?php wp_nonce_field( 'partino_admin_action', 'partino_nonce' ); ?>
			<input type="hidden" name="partino_action" value="part_save" />
			<input type="hidden" name="id" value="<?php echo esc_attr( $partino_edit_row['id'] ?? 0 ); ?>" />

			<p>
				<label><?php esc_html_e( 'نام قطعه', 'partino-smart-chatbot' ); ?></label><br/>
				<input type="text" name="name" required value="<?php echo esc_attr( $partino_edit_row['name'] ?? '' ); ?>" class="regular-text" />
			</p>
			<p>
				<label><?php esc_html_e( 'نامک (slug)', 'partino-smart-chatbot' ); ?></label><br/>
				<input type="text" name="slug" value="<?php echo esc_attr( $partino_edit_row['slug'] ?? '' ); ?>" class="regular-text" dir="ltr" />
			</p>
			<p>
				<label><?php esc_html_e( 'دسته‌بندی', 'partino-smart-chatbot' ); ?></label><br/>
				<input type="text" name="category" value="<?php echo esc_attr( $partino_edit_row['category'] ?? '' ); ?>" class="regular-text" />
			</p>
			<p>
				<label><?php esc_html_e( 'آیکون (اختیاری، نام یا ایموجی)', 'partino-smart-chatbot' ); ?></label><br/>
				<input type="text" name="icon" value="<?php echo esc_attr( $partino_edit_row['icon'] ?? '' ); ?>" class="regular-text" />
			</p>
			<p>
				<label><?php esc_html_e( 'نام‌های جایگزین (با کاما)', 'partino-smart-chatbot' ); ?></label><br/>
				<textarea name="aliases" rows="2" class="large-text"><?php echo esc_textarea( $partino_edit_row['aliases'] ?? '' ); ?></textarea>
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
		<h2><?php esc_html_e( 'قطعات', 'partino-smart-chatbot' ); ?> (<?php echo esc_html( number_format_i18n( count( $partino_rows ) ) ); ?>)</h2>

		<?php if ( empty( $partino_rows ) ) : ?>
			<div class="partino-empty"><p><?php esc_html_e( 'قطعه‌ای ثبت نشده است.', 'partino-smart-chatbot' ); ?></p></div>
		<?php else : ?>
			<table class="partino-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'نام', 'partino-smart-chatbot' ); ?></th>
						<th><?php esc_html_e( 'نامک', 'partino-smart-chatbot' ); ?></th>
						<th><?php esc_html_e( 'دسته', 'partino-smart-chatbot' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'partino-smart-chatbot' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'partino-smart-chatbot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $partino_rows as $partino_row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $partino_row['name'] ); ?></strong></td>
							<td dir="ltr"><code><?php echo esc_html( $partino_row['slug'] ); ?></code></td>
							<td><?php echo esc_html( $partino_row['category'] ?: '—' ); ?></td>
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
											'page'           => 'partino-chatbot-parts',
											'partino_action' => 'part_delete',
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
