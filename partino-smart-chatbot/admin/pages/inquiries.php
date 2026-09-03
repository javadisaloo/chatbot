<?php
/**
 * Inquiries admin page: list + detail view.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters; state-changing actions are nonce-checked in Admin::handle_actions().

$partino_view_id = ( isset( $_GET['action'] ) && 'view' === $_GET['action'] && isset( $_GET['inquiry'] ) ) ? absint( $_GET['inquiry'] ) : 0;

if ( $partino_view_id > 0 ) {
	$partino_inquiry = Inquiry::get( $partino_view_id );

	$partino_page_title = $partino_inquiry
		? sprintf( /* translators: %d inquiry id */ __( 'جزئیات استعلام #%d', 'partino-smart-chatbot' ), $partino_view_id )
		: __( 'استعلام یافت نشد', 'partino-smart-chatbot' );
	require __DIR__ . '/partials/header.php';

	if ( ! $partino_inquiry ) {
		echo '<div class="partino-empty"><p>' . esc_html__( 'این استعلام وجود ندارد یا حذف شده است.', 'partino-smart-chatbot' ) . '</p></div></div>';
		return;
	}

	$partino_statuses = Inquiry::statuses();
	$partino_action_url = admin_url( 'admin.php' );
	?>
	<p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=partino-chatbot-inquiries' ) ); ?>">
			← <?php esc_html_e( 'بازگشت به فهرست', 'partino-smart-chatbot' ); ?>
		</a>
	</p>

	<div class="partino-detail-grid">
		<div class="partino-panel">
			<h2><?php esc_html_e( 'اطلاعات مشتری', 'partino-smart-chatbot' ); ?></h2>
			<table class="partino-detail-table">
				<tr>
					<th><?php esc_html_e( 'شماره موبایل', 'partino-smart-chatbot' ); ?></th>
					<td dir="ltr"><strong><?php echo esc_html( $partino_inquiry['customer_phone'] ); ?></strong></td>
				</tr>
				<?php if ( '' !== $partino_inquiry['customer_name'] ) : ?>
				<tr>
					<th><?php esc_html_e( 'نام', 'partino-smart-chatbot' ); ?></th>
					<td><?php echo esc_html( $partino_inquiry['customer_name'] ); ?></td>
				</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'تاریخ ثبت', 'partino-smart-chatbot' ); ?></th>
					<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $partino_inquiry['created_at'] ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'منبع', 'partino-smart-chatbot' ); ?></th>
					<td><?php echo esc_html( $partino_inquiry['source'] ); ?></td>
				</tr>
				<?php if ( ! empty( $partino_inquiry['page_url'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'صفحه', 'partino-smart-chatbot' ); ?></th>
					<td dir="ltr"><a href="<?php echo esc_url( $partino_inquiry['page_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $partino_inquiry['page_url'] ); ?></a></td>
				</tr>
				<?php endif; ?>
				<?php if ( ! empty( $partino_inquiry['device_type'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'دستگاه', 'partino-smart-chatbot' ); ?></th>
					<td><?php echo esc_html( $partino_inquiry['device_type'] ); ?></td>
				</tr>
				<?php endif; ?>
			</table>
		</div>

		<div class="partino-panel">
			<h2><?php esc_html_e( 'اطلاعات خودرو و قطعه', 'partino-smart-chatbot' ); ?></h2>
			<table class="partino-detail-table">
				<tr>
					<th><?php esc_html_e( 'قطعه', 'partino-smart-chatbot' ); ?></th>
					<td><strong><?php echo esc_html( $partino_inquiry['part_name'] ); ?></strong></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'برند', 'partino-smart-chatbot' ); ?></th>
					<td><?php echo esc_html( $partino_inquiry['vehicle_brand'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'مدل', 'partino-smart-chatbot' ); ?></th>
					<td><?php echo esc_html( $partino_inquiry['vehicle_model'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'تیپ', 'partino-smart-chatbot' ); ?></th>
					<td><?php echo esc_html( $partino_inquiry['vehicle_trim'] ?: '—' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'سال', 'partino-smart-chatbot' ); ?></th>
					<td><?php echo esc_html( $partino_inquiry['vehicle_year'] ); ?></td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'تغییر وضعیت', 'partino-smart-chatbot' ); ?></h3>
			<form method="get" action="<?php echo esc_url( $partino_action_url ); ?>" class="partino-inline-form">
				<input type="hidden" name="page" value="partino-chatbot-inquiries" />
				<input type="hidden" name="action" value="view" />
				<input type="hidden" name="inquiry" value="<?php echo esc_attr( $partino_view_id ); ?>" />
				<input type="hidden" name="partino_action" value="inquiry_status" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $partino_view_id ); ?>" />
				<?php wp_nonce_field( 'partino_admin_action', 'partino_nonce' ); ?>
				<select name="status">
					<?php foreach ( $partino_statuses as $partino_key => $partino_label ) : ?>
						<option value="<?php echo esc_attr( $partino_key ); ?>" <?php selected( $partino_inquiry['status'], $partino_key ); ?>>
							<?php echo esc_html( $partino_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'به‌روزرسانی', 'partino-smart-chatbot' ); ?></button>
			</form>
		</div>
	</div>

	<div class="partino-panel">
		<h2><?php esc_html_e( 'تاریخچه گفتگو', 'partino-smart-chatbot' ); ?></h2>
		<?php
		$partino_conv_id  = (int) ( $partino_inquiry['conversation_id'] ?? 0 );
		$partino_messages = $partino_conv_id > 0 ? Conversation::get_messages( $partino_conv_id, true ) : array();
		?>
		<?php if ( empty( $partino_messages ) ) : ?>
			<p><?php esc_html_e( 'گفتگویی برای این استعلام ثبت نشده است.', 'partino-smart-chatbot' ); ?></p>
		<?php else : ?>
			<div class="partino-chat-log">
				<?php foreach ( $partino_messages as $partino_msg ) : ?>
					<div class="partino-chat-line partino-chat-<?php echo esc_attr( $partino_msg['role'] ); ?>">
						<span class="partino-chat-role"><?php echo esc_html( 'assistant' === $partino_msg['role'] ? __( 'ربات', 'partino-smart-chatbot' ) : ( 'user' === $partino_msg['role'] ? __( 'کاربر', 'partino-smart-chatbot' ) : __( 'سیستم', 'partino-smart-chatbot' ) ) ); ?></span>
						<span class="partino-chat-content"><?php echo esc_html( $partino_msg['content'] ); ?></span>
						<span class="partino-chat-time"><?php echo esc_html( mysql2date( 'H:i', $partino_msg['created_at'] ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	</div>
	<?php
	return;
}

// ---------------------------------------------------------------------------
// List view.
// ---------------------------------------------------------------------------

$partino_page_title = __( 'استعلام‌ها', 'partino-smart-chatbot' );
require __DIR__ . '/partials/header.php';

$partino_search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$partino_status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$partino_paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$partino_per_page = 20;

$partino_result   = Inquiry::query(
	array(
		'search'   => $partino_search,
		'status'   => $partino_status,
		'per_page' => $partino_per_page,
		'page'     => $partino_paged,
	)
);
$partino_statuses = Inquiry::statuses();
$partino_pages    = (int) ceil( $partino_result['total'] / $partino_per_page );

$partino_base_url = admin_url( 'admin.php?page=partino-chatbot-inquiries' );
?>

<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="partino-filters">
	<input type="hidden" name="page" value="partino-chatbot-inquiries" />
	<input type="search" name="s" value="<?php echo esc_attr( $partino_search ); ?>" placeholder="<?php esc_attr_e( 'جستجو: شماره، قطعه، خودرو...', 'partino-smart-chatbot' ); ?>" />
	<select name="status">
		<option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'partino-smart-chatbot' ); ?></option>
		<?php foreach ( $partino_statuses as $partino_key => $partino_label ) : ?>
			<option value="<?php echo esc_attr( $partino_key ); ?>" <?php selected( $partino_status, $partino_key ); ?>><?php echo esc_html( $partino_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<button type="submit" class="button"><?php esc_html_e( 'فیلتر', 'partino-smart-chatbot' ); ?></button>

	<?php
	$partino_export_url = wp_nonce_url(
		add_query_arg(
			array(
				'page'           => 'partino-chatbot-inquiries',
				'partino_action' => 'export_csv',
				's'              => $partino_search,
				'status'         => $partino_status,
			),
			admin_url( 'admin.php' )
		),
		'partino_admin_action',
		'partino_nonce'
	);
	?>
	<a href="<?php echo esc_url( $partino_export_url ); ?>" class="button"><?php esc_html_e( 'خروجی CSV', 'partino-smart-chatbot' ); ?></a>
</form>

<?php if ( empty( $partino_result['items'] ) ) : ?>
	<div class="partino-empty">
		<span class="dashicons dashicons-format-chat"></span>
		<p><?php esc_html_e( 'استعلامی مطابق فیلترها یافت نشد.', 'partino-smart-chatbot' ); ?></p>
	</div>
<?php else : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=partino-chatbot-inquiries' ) ); ?>">
		<?php wp_nonce_field( 'partino_admin_action', 'partino_nonce' ); ?>
		<input type="hidden" name="partino_action" value="inquiry_bulk" />

		<div class="partino-bulkbar">
			<select name="bulk_action">
				<option value=""><?php esc_html_e( 'عملیات گروهی', 'partino-smart-chatbot' ); ?></option>
				<option value="delete"><?php esc_html_e( 'حذف', 'partino-smart-chatbot' ); ?></option>
				<?php foreach ( $partino_statuses as $partino_key => $partino_label ) : ?>
					<option value="status_<?php echo esc_attr( $partino_key ); ?>">
						<?php
						/* translators: %s status label */
						echo esc_html( sprintf( __( 'تغییر وضعیت به: %s', 'partino-smart-chatbot' ), $partino_label ) );
						?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button" data-partino-confirm="bulk"><?php esc_html_e( 'اجرا', 'partino-smart-chatbot' ); ?></button>
		</div>

		<table class="partino-table">
			<thead>
				<tr>
					<th class="partino-col-check"><input type="checkbox" data-partino-check-all aria-label="<?php esc_attr_e( 'انتخاب همه', 'partino-smart-chatbot' ); ?>" /></th>
					<th><?php esc_html_e( 'شناسه', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'تاریخ', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'شماره مشتری', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'قطعه', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'خودرو', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'سال', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'partino-smart-chatbot' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'partino-smart-chatbot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $partino_result['items'] as $partino_row ) : ?>
					<tr>
						<td><input type="checkbox" name="inquiry_ids[]" value="<?php echo esc_attr( $partino_row['id'] ); ?>" aria-label="<?php esc_attr_e( 'انتخاب', 'partino-smart-chatbot' ); ?>" /></td>
						<td>#<?php echo esc_html( $partino_row['id'] ); ?></td>
						<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $partino_row['created_at'] ) ); ?></td>
						<td dir="ltr"><?php echo esc_html( partino_chatbot_mask_phone( (string) $partino_row['customer_phone'] ) ); ?></td>
						<td><?php echo esc_html( $partino_row['part_name'] ); ?></td>
						<td><?php echo esc_html( trim( $partino_row['vehicle_brand'] . ' ' . $partino_row['vehicle_model'] . ' ' . $partino_row['vehicle_trim'] ) ); ?></td>
						<td><?php echo esc_html( $partino_row['vehicle_year'] ); ?></td>
						<td>
							<span class="partino-badge partino-badge-<?php echo esc_attr( $partino_row['status'] ); ?>">
								<?php echo esc_html( $partino_statuses[ $partino_row['status'] ] ?? $partino_row['status'] ); ?>
							</span>
						</td>
						<td class="partino-actions">
							<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'action' => 'view', 'inquiry' => (int) $partino_row['id'] ), $partino_base_url ) ); ?>">
								<?php esc_html_e( 'مشاهده', 'partino-smart-chatbot' ); ?>
							</a>
							<?php
							$partino_delete_url = wp_nonce_url(
								add_query_arg(
									array(
										'page'           => 'partino-chatbot-inquiries',
										'partino_action' => 'inquiry_delete',
										'id'             => (int) $partino_row['id'],
									),
									admin_url( 'admin.php' )
								),
								'partino_admin_action',
								'partino_nonce'
							);
							?>
							<a class="button button-small partino-danger" href="<?php echo esc_url( $partino_delete_url ); ?>" data-partino-confirm="delete">
								<?php esc_html_e( 'حذف', 'partino-smart-chatbot' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</form>

	<?php if ( $partino_pages > 1 ) : ?>
		<div class="partino-pagination">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%', add_query_arg( array( 's' => $partino_search, 'status' => $partino_status ), $partino_base_url ) ),
						'format'    => '',
						'current'   => $partino_paged,
						'total'     => $partino_pages,
						'prev_text' => '‹',
						'next_text' => '›',
					)
				) ?: ''
			);
			?>
		</div>
	<?php endif; ?>
<?php endif; ?>
</div>
