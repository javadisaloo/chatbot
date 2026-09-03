<?php
/**
 * Vehicles admin page (brands / models / trims).
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters; writes go through nonce-checked handler.

$partino_page_title = __( 'مدیریت خودروها', 'partino-smart-chatbot' );
require __DIR__ . '/partials/header.php';

$partino_type   = isset( $_GET['type'] ) && in_array( $_GET['type'], array( 'brand', 'model', 'trim' ), true ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'brand';
$partino_parent = isset( $_GET['parent'] ) ? absint( $_GET['parent'] ) : 0;
$partino_edit   = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$partino_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$partino_edit_row = $partino_edit > 0 ? Vehicle::get( $partino_edit ) : null;

$partino_parent_row = $partino_parent > 0 ? Vehicle::get( $partino_parent ) : null;
$partino_base       = admin_url( 'admin.php?page=partino-chatbot-vehicles' );

$partino_rows = Vehicle::get_list( $partino_type, $partino_parent > 0 ? $partino_parent : null, false );
if ( '' !== $partino_search ) {
	$partino_needle = Normalizer::text( $partino_search );
	$partino_rows   = array_values(
		array_filter(
			$partino_rows,
			static function ( $r ) use ( $partino_needle ) {
				return str_contains( Normalizer::text( $r['name'] . ' ' . $r['name_en'] . ' ' . $r['aliases'] ), $partino_needle );
			}
		)
	);
}

$partino_type_labels = array(
	'brand' => __( 'برند', 'partino-smart-chatbot' ),
	'model' => __( 'مدل', 'partino-smart-chatbot' ),
	'trim'  => __( 'تیپ', 'partino-smart-chatbot' ),
);
?>

<nav class="partino-tabs">
	<a href="<?php echo esc_url( add_query_arg( 'type', 'brand', $partino_base ) ); ?>" class="<?php echo 'brand' === $partino_type && ! $partino_parent ? 'active' : ''; ?>"><?php esc_html_e( 'برندها', 'partino-smart-chatbot' ); ?></a>
	<a href="<?php echo esc_url( add_query_arg( 'type', 'model', $partino_base ) ); ?>" class="<?php echo 'model' === $partino_type ? 'active' : ''; ?>"><?php esc_html_e( 'مدل‌ها', 'partino-smart-chatbot' ); ?></a>
	<a href="<?php echo esc_url( add_query_arg( 'type', 'trim', $partino_base ) ); ?>" class="<?php echo 'trim' === $partino_type ? 'active' : ''; ?>"><?php esc_html_e( 'تیپ‌ها', 'partino-smart-chatbot' ); ?></a>
</nav>

<?php if ( $partino_parent_row ) : ?>
	<p>
		<?php
		/* translators: %s parent name */
		echo esc_html( sprintf( __( 'نمایش زیرمجموعه‌های: %s', 'partino-smart-chatbot' ), $partino_parent_row['name'] ) );
		?>
		— <a href="<?php echo esc_url( add_query_arg( 'type', $partino_type, $partino_base ) ); ?>"><?php esc_html_e( 'نمایش همه', 'partino-smart-chatbot' ); ?></a>
	</p>
<?php endif; ?>

<div class="partino-detail-grid">
	<div class="partino-panel">
		<h2><?php echo $partino_edit_row ? esc_html__( 'ویرایش', 'partino-smart-chatbot' ) : esc_html__( 'افزودن جدید', 'partino-smart-chatbot' ); ?> (<?php echo esc_html( $partino_type_labels[ $partino_type ] ); ?>)</h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=partino-chatbot-vehicles&type=' . $partino_type ) ); ?>">
			<?php wp_nonce_field( 'partino_admin_action', 'partino_nonce' ); ?>
			<input type="hidden" name="partino_action" value="vehicle_save" />
			<input type="hidden" name="id" value="<?php echo esc_attr( $partino_edit_row['id'] ?? 0 ); ?>" />
			<input type="hidden" name="type" value="<?php echo esc_attr( $partino_edit_row['type'] ?? $partino_type ); ?>" />

			<?php if ( 'brand' !== $partino_type ) : ?>
				<p>
					<label><?php echo 'model' === $partino_type ? esc_html__( 'برند والد', 'partino-smart-chatbot' ) : esc_html__( 'مدل والد', 'partino-smart-chatbot' ); ?></label><br/>
					<select name="parent_id" required>
						<option value=""><?php esc_html_e( '— انتخاب —', 'partino-smart-chatbot' ); ?></option>
						<?php
						$partino_parents = Vehicle::get_list( 'model' === $partino_type ? 'brand' : 'model', null, false );
						foreach ( $partino_parents as $partino_p ) :
							$partino_p_label = $partino_p['name'];
							if ( 'trim' === $partino_type ) {
								$partino_pb       = Vehicle::get( (int) $partino_p['parent_id'] );
								$partino_p_label = ( $partino_pb['name'] ?? '' ) . ' ' . $partino_p['name'];
							}
							?>
							<option value="<?php echo esc_attr( $partino_p['id'] ); ?>" <?php selected( (int) ( $partino_edit_row['parent_id'] ?? 0 ), (int) $partino_p['id'] ); ?>>
								<?php echo esc_html( $partino_p_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>

			<p>
				<label><?php esc_html_e( 'نام (فارسی)', 'partino-smart-chatbot' ); ?></label><br/>
				<input type="text" name="name" required value="<?php echo esc_attr( $partino_edit_row['name'] ?? '' ); ?>" class="regular-text" />
			</p>
			<p>
				<label><?php esc_html_e( 'نام انگلیسی', 'partino-smart-chatbot' ); ?></label><br/>
				<input type="text" name="name_en" value="<?php echo esc_attr( $partino_edit_row['name_en'] ?? '' ); ?>" class="regular-text" dir="ltr" />
			</p>
			<p>
				<label><?php esc_html_e( 'نام‌های جایگزین (با کاما جدا کنید)', 'partino-smart-chatbot' ); ?></label><br/>
				<textarea name="aliases" rows="2" class="large-text"><?php echo esc_textarea( $partino_edit_row['aliases'] ?? '' ); ?></textarea>
			</p>
			<?php if ( 'model' === $partino_type ) : ?>
				<p>
					<label><?php esc_html_e( 'بازه سال تولید', 'partino-smart-chatbot' ); ?></label><br/>
					<input type="number" name="year_start" min="0" max="2100" value="<?php echo esc_attr( $partino_edit_row['year_start'] ?? '' ); ?>" style="width:110px" />
					—
					<input type="number" name="year_end" min="0" max="2100" value="<?php echo esc_attr( $partino_edit_row['year_end'] ?? '' ); ?>" style="width:110px" />
				</p>
			<?php endif; ?>
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
					<a class="button" href="<?php echo esc_url( add_query_arg( 'type', $partino_type, $partino_base ) ); ?>"><?php esc_html_e( 'انصراف', 'partino-smart-chatbot' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
	</div>

	<div class="partino-panel">
		<h2><?php echo esc_html( $partino_type_labels[ $partino_type ] ); ?> (<?php echo esc_html( number_format_i18n( count( $partino_rows ) ) ); ?>)</h2>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="partino-filters">
			<input type="hidden" name="page" value="partino-chatbot-vehicles" />
			<input type="hidden" name="type" value="<?php echo esc_attr( $partino_type ); ?>" />
			<input type="search" name="s" value="<?php echo esc_attr( $partino_search ); ?>" placeholder="<?php esc_attr_e( 'جستجو...', 'partino-smart-chatbot' ); ?>" />
			<button type="submit" class="button"><?php esc_html_e( 'جستجو', 'partino-smart-chatbot' ); ?></button>
		</form>

		<?php if ( empty( $partino_rows ) ) : ?>
			<div class="partino-empty"><p><?php esc_html_e( 'موردی یافت نشد.', 'partino-smart-chatbot' ); ?></p></div>
		<?php else : ?>
			<table class="partino-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'نام', 'partino-smart-chatbot' ); ?></th>
						<th><?php esc_html_e( 'انگلیسی', 'partino-smart-chatbot' ); ?></th>
						<?php if ( 'brand' !== $partino_type ) : ?>
							<th><?php esc_html_e( 'والد', 'partino-smart-chatbot' ); ?></th>
						<?php endif; ?>
						<th><?php esc_html_e( 'وضعیت', 'partino-smart-chatbot' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'partino-smart-chatbot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $partino_rows as $partino_row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $partino_row['name'] ); ?></strong></td>
							<td dir="ltr"><?php echo esc_html( $partino_row['name_en'] ?: '—' ); ?></td>
							<?php if ( 'brand' !== $partino_type ) : ?>
								<td>
									<?php
									$partino_par = Vehicle::get( (int) $partino_row['parent_id'] );
									echo esc_html( $partino_par['name'] ?? '—' );
									?>
								</td>
							<?php endif; ?>
							<td>
								<span class="partino-badge <?php echo (int) $partino_row['is_active'] ? 'partino-badge-completed' : 'partino-badge-cancelled'; ?>">
									<?php echo (int) $partino_row['is_active'] ? esc_html__( 'فعال', 'partino-smart-chatbot' ) : esc_html__( 'غیرفعال', 'partino-smart-chatbot' ); ?>
								</span>
							</td>
							<td class="partino-actions">
								<?php if ( 'brand' === $partino_type ) : ?>
									<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'type' => 'model', 'parent' => (int) $partino_row['id'] ), $partino_base ) ); ?>"><?php esc_html_e( 'مدل‌ها', 'partino-smart-chatbot' ); ?></a>
								<?php elseif ( 'model' === $partino_type ) : ?>
									<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'type' => 'trim', 'parent' => (int) $partino_row['id'] ), $partino_base ) ); ?>"><?php esc_html_e( 'تیپ‌ها', 'partino-smart-chatbot' ); ?></a>
								<?php endif; ?>
								<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'type' => $partino_type, 'edit' => (int) $partino_row['id'], 'parent' => $partino_parent ), $partino_base ) ); ?>"><?php esc_html_e( 'ویرایش', 'partino-smart-chatbot' ); ?></a>
								<?php
								$partino_del = wp_nonce_url(
									add_query_arg(
										array(
											'page'           => 'partino-chatbot-vehicles',
											'type'           => $partino_type,
											'partino_action' => 'vehicle_delete',
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
