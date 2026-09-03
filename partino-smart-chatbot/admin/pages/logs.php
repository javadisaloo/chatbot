<?php
/**
 * Logs admin page.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters; clear action is nonce-checked.

$partino_page_title = __( 'لاگ‌ها', 'partino-smart-chatbot' );
require __DIR__ . '/partials/header.php';

global $wpdb;
$partino_table = Database::table( 'logs' );

$partino_level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
$partino_paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$partino_pp    = 50;
$partino_off   = ( $partino_paged - 1 ) * $partino_pp;

// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
if ( '' !== $partino_level && in_array( $partino_level, array( 'debug', 'info', 'warning', 'error' ), true ) ) {
	$partino_total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$partino_table} WHERE level = %s", $partino_level ) );
	$partino_rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$partino_table} WHERE level = %s ORDER BY id DESC LIMIT %d OFFSET %d", $partino_level, $partino_pp, $partino_off ), ARRAY_A );
} else {
	$partino_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$partino_table}" );
	$partino_rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$partino_table} ORDER BY id DESC LIMIT %d OFFSET %d", $partino_pp, $partino_off ), ARRAY_A );
}
// phpcs:enable

$partino_pages = (int) ceil( $partino_total / $partino_pp );
$partino_base  = admin_url( 'admin.php?page=partino-chatbot-logs' );

$partino_clear_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'           => 'partino-chatbot-logs',
			'partino_action' => 'clear_logs',
		),
		admin_url( 'admin.php' )
	),
	'partino_admin_action',
	'partino_nonce'
);
?>

<div class="partino-filters">
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="partino-chatbot-logs" />
		<select name="level">
			<option value=""><?php esc_html_e( 'همه سطوح', 'partino-smart-chatbot' ); ?></option>
			<?php foreach ( array( 'error', 'warning', 'info', 'debug' ) as $partino_l ) : ?>
				<option value="<?php echo esc_attr( $partino_l ); ?>" <?php selected( $partino_level, $partino_l ); ?>><?php echo esc_html( strtoupper( $partino_l ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'فیلتر', 'partino-smart-chatbot' ); ?></button>
	</form>
	<a href="<?php echo esc_url( $partino_clear_url ); ?>" class="button partino-danger" data-partino-confirm="delete"><?php esc_html_e( 'پاک‌سازی همه لاگ‌ها', 'partino-smart-chatbot' ); ?></a>
</div>

<?php if ( empty( $partino_rows ) ) : ?>
	<div class="partino-empty">
		<span class="dashicons dashicons-yes-alt"></span>
		<p><?php esc_html_e( 'لاگی ثبت نشده است. همه‌چیز مرتب است!', 'partino-smart-chatbot' ); ?></p>
	</div>
<?php else : ?>
	<table class="partino-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'زمان', 'partino-smart-chatbot' ); ?></th>
				<th><?php esc_html_e( 'سطح', 'partino-smart-chatbot' ); ?></th>
				<th><?php esc_html_e( 'کانال', 'partino-smart-chatbot' ); ?></th>
				<th><?php esc_html_e( 'پیام', 'partino-smart-chatbot' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $partino_rows as $partino_row ) : ?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'Y/m/d H:i:s', $partino_row['created_at'] ) ); ?></td>
					<td><span class="partino-badge partino-log-<?php echo esc_attr( $partino_row['level'] ); ?>"><?php echo esc_html( strtoupper( $partino_row['level'] ) ); ?></span></td>
					<td><code><?php echo esc_html( $partino_row['channel'] ); ?></code></td>
					<td dir="auto"><?php echo esc_html( $partino_row['message'] ); ?></td>
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
						'base'      => add_query_arg( 'paged', '%#%', add_query_arg( 'level', $partino_level, $partino_base ) ),
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
