<?php
/**
 * Conversations admin page.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view.

$partino_view_id = ( isset( $_GET['action'] ) && 'view' === $_GET['action'] && isset( $_GET['conversation'] ) ) ? absint( $_GET['conversation'] ) : 0;

if ( $partino_view_id > 0 ) {
	$partino_conv = Conversation::get( $partino_view_id );

	$partino_page_title = $partino_conv
		? sprintf( /* translators: %d conversation id */ __( 'گفتگو #%d', 'partino-smart-chatbot' ), $partino_view_id )
		: __( 'گفتگو یافت نشد', 'partino-smart-chatbot' );
	require __DIR__ . '/partials/header.php';

	if ( ! $partino_conv ) {
		echo '<div class="partino-empty"><p>' . esc_html__( 'این گفتگو وجود ندارد.', 'partino-smart-chatbot' ) . '</p></div></div>';
		return;
	}

	$partino_messages = Conversation::get_messages( $partino_view_id, true );
	?>
	<p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=partino-chatbot-conversations' ) ); ?>">
			← <?php esc_html_e( 'بازگشت به فهرست', 'partino-smart-chatbot' ); ?>
		</a>
	</p>

	<div class="partino-panel">
		<h2><?php esc_html_e( 'مشخصات گفتگو', 'partino-smart-chatbot' ); ?></h2>
		<table class="partino-detail-table">
			<tr>
				<th><?php esc_html_e( 'شناسه یکتا', 'partino-smart-chatbot' ); ?></th>
				<td dir="ltr"><?php echo esc_html( $partino_conv['uuid'] ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'وضعیت جریان', 'partino-smart-chatbot' ); ?></th>
				<td><code><?php echo esc_html( $partino_conv['state'] ); ?></code> (<?php echo esc_html( $partino_conv['status'] ); ?>)</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'شروع', 'partino-smart-chatbot' ); ?></th>
				<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $partino_conv['created_at'] ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'آخرین فعالیت', 'partino-smart-chatbot' ); ?></th>
				<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $partino_conv['updated_at'] ) ); ?></td>
			</tr>
		</table>
	</div>

	<div class="partino-panel">
		<h2><?php esc_html_e( 'پیام‌ها', 'partino-smart-chatbot' ); ?></h2>
		<?php if ( empty( $partino_messages ) ) : ?>
			<p><?php esc_html_e( 'پیامی ثبت نشده است.', 'partino-smart-chatbot' ); ?></p>
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

$partino_page_title = __( 'گفتگوها', 'partino-smart-chatbot' );
require __DIR__ . '/partials/header.php';

$partino_paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$partino_per_page = 20;
$partino_result   = Conversation::query( $partino_per_page, $partino_paged );
$partino_pages    = (int) ceil( $partino_result['total'] / $partino_per_page );
$partino_base_url = admin_url( 'admin.php?page=partino-chatbot-conversations' );
?>

<?php if ( empty( $partino_result['items'] ) ) : ?>
	<div class="partino-empty">
		<span class="dashicons dashicons-format-chat"></span>
		<p><?php esc_html_e( 'هنوز گفتگویی ثبت نشده است.', 'partino-smart-chatbot' ); ?></p>
	</div>
<?php else : ?>
	<table class="partino-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'شناسه', 'partino-smart-chatbot' ); ?></th>
				<th><?php esc_html_e( 'وضعیت جریان', 'partino-smart-chatbot' ); ?></th>
				<th><?php esc_html_e( 'پیام‌ها', 'partino-smart-chatbot' ); ?></th>
				<th><?php esc_html_e( 'وضعیت', 'partino-smart-chatbot' ); ?></th>
				<th><?php esc_html_e( 'آخرین فعالیت', 'partino-smart-chatbot' ); ?></th>
				<th><?php esc_html_e( 'عملیات', 'partino-smart-chatbot' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $partino_result['items'] as $partino_row ) : ?>
				<tr>
					<td>#<?php echo esc_html( $partino_row['id'] ); ?></td>
					<td><code><?php echo esc_html( $partino_row['state'] ); ?></code></td>
					<td><?php echo esc_html( number_format_i18n( (int) $partino_row['message_count'] ) ); ?></td>
					<td><?php echo esc_html( $partino_row['status'] ); ?></td>
					<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $partino_row['updated_at'] ) ); ?></td>
					<td>
						<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'action' => 'view', 'conversation' => (int) $partino_row['id'] ), $partino_base_url ) ); ?>">
							<?php esc_html_e( 'مشاهده', 'partino-smart-chatbot' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $partino_pages > 1 ) : ?>
		<div class="partino-pagination">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%', $partino_base_url ),
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
