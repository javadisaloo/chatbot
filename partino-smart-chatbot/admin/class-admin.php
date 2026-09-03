<?php
/**
 * Admin panel: menus, actions, assets, CSV export.
 *
 * @package Partino\Chatbot
 */

namespace Partino\Chatbot;

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin pages and handles admin-post actions.
 */
final class Admin {

	/**
	 * Page slugs.
	 */
	private const PAGES = array(
		'partino-chatbot',
		'partino-chatbot-inquiries',
		'partino-chatbot-conversations',
		'partino-chatbot-vehicles',
		'partino-chatbot-parts',
		'partino-chatbot-quick-actions',
		'partino-chatbot-settings',
		'partino-chatbot-logs',
	);

	/**
	 * Hook registration.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Capability shortcut.
	 *
	 * @return string
	 */
	private function cap(): string {
		return current_user_can( PARTINO_CHATBOT_CAP ) ? PARTINO_CHATBOT_CAP : 'manage_options';
	}

	/**
	 * Menu registration.
	 */
	public function menu(): void {
		$cap = $this->cap();

		add_menu_page(
			__( 'Partino Chatbot', 'partino-smart-chatbot' ),
			__( 'Partino Chatbot', 'partino-smart-chatbot' ),
			$cap,
			'partino-chatbot',
			array( $this, 'page_dashboard' ),
			'dashicons-format-chat',
			58
		);

		add_submenu_page( 'partino-chatbot', __( 'داشبورد', 'partino-smart-chatbot' ), __( 'داشبورد', 'partino-smart-chatbot' ), $cap, 'partino-chatbot', array( $this, 'page_dashboard' ) );
		add_submenu_page( 'partino-chatbot', __( 'استعلام‌ها', 'partino-smart-chatbot' ), __( 'استعلام‌ها', 'partino-smart-chatbot' ), $cap, 'partino-chatbot-inquiries', array( $this, 'page_inquiries' ) );
		add_submenu_page( 'partino-chatbot', __( 'گفتگوها', 'partino-smart-chatbot' ), __( 'گفتگوها', 'partino-smart-chatbot' ), $cap, 'partino-chatbot-conversations', array( $this, 'page_conversations' ) );
		add_submenu_page( 'partino-chatbot', __( 'خودروها', 'partino-smart-chatbot' ), __( 'خودروها', 'partino-smart-chatbot' ), $cap, 'partino-chatbot-vehicles', array( $this, 'page_vehicles' ) );
		add_submenu_page( 'partino-chatbot', __( 'قطعات', 'partino-smart-chatbot' ), __( 'قطعات', 'partino-smart-chatbot' ), $cap, 'partino-chatbot-parts', array( $this, 'page_parts' ) );
		add_submenu_page( 'partino-chatbot', __( 'اکشن‌های سریع', 'partino-smart-chatbot' ), __( 'اکشن‌های سریع', 'partino-smart-chatbot' ), $cap, 'partino-chatbot-quick-actions', array( $this, 'page_quick_actions' ) );
		add_submenu_page( 'partino-chatbot', __( 'تنظیمات', 'partino-smart-chatbot' ), __( 'تنظیمات', 'partino-smart-chatbot' ), $cap, 'partino-chatbot-settings', array( $this, 'page_settings' ) );
		add_submenu_page( 'partino-chatbot', __( 'لاگ‌ها', 'partino-smart-chatbot' ), __( 'لاگ‌ها', 'partino-smart-chatbot' ), $cap, 'partino-chatbot-logs', array( $this, 'page_logs' ) );
	}

	/**
	 * Enqueue admin assets on plugin pages only.
	 *
	 * @param string $hook Current hook suffix.
	 */
	public function assets( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $page, self::PAGES, true ) ) {
			return;
		}

		$css = PARTINO_CHATBOT_PATH . 'admin/assets/css/admin.css';
		$js  = PARTINO_CHATBOT_PATH . 'admin/assets/js/admin.js';

		wp_enqueue_style(
			'partino-chatbot-admin',
			PARTINO_CHATBOT_URL . 'admin/assets/css/admin.css',
			array(),
			file_exists( $css ) ? (string) filemtime( $css ) : PARTINO_CHATBOT_VERSION
		);

		wp_enqueue_script(
			'partino-chatbot-admin',
			PARTINO_CHATBOT_URL . 'admin/assets/js/admin.js',
			array(),
			file_exists( $js ) ? (string) filemtime( $js ) : PARTINO_CHATBOT_VERSION,
			array( 'in_footer' => true )
		);

		wp_localize_script(
			'partino-chatbot-admin',
			'PartinoAdminData',
			array(
				'restUrl' => esc_url_raw( rest_url( PARTINO_CHATBOT_REST_NS ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'confirmDelete' => __( 'آیا از حذف مطمئن هستید؟ این عمل قابل بازگشت نیست.', 'partino-smart-chatbot' ),
					'confirmReset'  => __( 'همه تنظیمات به مقادیر پیش‌فرض بازمی‌گردند. ادامه می‌دهید؟', 'partino-smart-chatbot' ),
					'testing'       => __( 'در حال تست اتصال...', 'partino-smart-chatbot' ),
				),
			)
		);

		if ( 'partino-chatbot-settings' === $page ) {
			wp_enqueue_media();
		}
	}

	// -----------------------------------------------------------------------
	// Action handling (admin_init).
	// -----------------------------------------------------------------------

	/**
	 * Handle POST/GET admin actions with nonce + capability checks.
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( $this->cap() ) ) {
			return;
		}

		$action = isset( $_REQUEST['partino_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['partino_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $action ) {
			return;
		}

		check_admin_referer( 'partino_admin_action', 'partino_nonce' );

		switch ( $action ) {
			case 'save_settings':
				$this->action_save_settings();
				break;
			case 'reset_settings':
				Settings::reset();
				$this->redirect_back( 'settings-reset' );
				break;
			case 'import_settings':
				$this->action_import_settings();
				break;
			case 'export_csv':
				$this->action_export_csv();
				break;
			case 'export_settings':
				$this->action_export_settings();
				break;
			case 'inquiry_status':
				$this->action_inquiry_status();
				break;
			case 'inquiry_delete':
				$this->action_inquiry_delete();
				break;
			case 'inquiry_bulk':
				$this->action_inquiry_bulk();
				break;
			case 'vehicle_save':
				$this->action_vehicle_save();
				break;
			case 'vehicle_delete':
				$id = absint( $_REQUEST['id'] ?? 0 );
				if ( $id ) {
					Vehicle::delete( $id );
				}
				$this->redirect_back( 'deleted' );
				break;
			case 'part_save':
				$this->action_part_save();
				break;
			case 'part_delete':
				$id = absint( $_REQUEST['id'] ?? 0 );
				if ( $id ) {
					Part::delete( $id );
				}
				$this->redirect_back( 'deleted' );
				break;
			case 'qa_save':
				$this->action_qa_save();
				break;
			case 'qa_delete':
				$id = absint( $_REQUEST['id'] ?? 0 );
				if ( $id ) {
					Quick_Action::delete( $id );
				}
				$this->redirect_back( 'deleted' );
				break;
			case 'clear_logs':
				Logger::clear();
				$this->redirect_back( 'logs-cleared' );
				break;
		}
	}

	/**
	 * Save settings form.
	 */
	private function action_save_settings(): void {
		$raw = isset( $_POST['partino_settings'] ) && is_array( $_POST['partino_settings'] )
			? map_deep( wp_unslash( $_POST['partino_settings'] ), 'sanitize_textarea_field' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			: array();

		$clean = Settings::sanitize( $raw );
		Settings::save( $clean );
		$this->redirect_back( 'saved' );
	}

	/**
	 * Import settings from an uploaded JSON file.
	 */
	private function action_import_settings(): void {
		if ( empty( $_FILES['partino_import_file']['tmp_name'] ) ) {
			$this->redirect_back( 'import-error' );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- binary file read, validated as JSON below.
		$contents = file_get_contents( sanitize_text_field( $_FILES['partino_import_file']['tmp_name'] ) );
		$data     = json_decode( (string) $contents, true );

		if ( ! is_array( $data ) ) {
			$this->redirect_back( 'import-error' );
			return;
		}

		// Never import a masked API key.
		if ( isset( $data['ai']['api_key'] ) && str_contains( (string) $data['ai']['api_key'], '*' ) ) {
			unset( $data['ai']['api_key'] );
		}

		$clean = Settings::sanitize( $data );
		Settings::save( $clean );
		$this->redirect_back( 'imported' );
	}

	/**
	 * Export settings as a JSON download (API key masked).
	 */
	private function action_export_settings(): void {
		$data = Settings::export();
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=partino-chatbot-settings-' . gmdate( 'Ymd-His' ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * CSV export of inquiries (honors current filters).
	 */
	private function action_export_csv(): void {
		$args = array(
			'search'   => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
			'status'   => isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '',
			'per_page' => 5000,
			'page'     => 1,
		);

		$result = Inquiry::query( $args );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=partino-inquiries-' . gmdate( 'Ymd-His' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM for Excel.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'ID', 'Date', 'Phone', 'Part', 'Brand', 'Model', 'Trim', 'Year', 'Status' ) );

		foreach ( $result['items'] as $row ) {
			fputcsv(
				$out,
				array(
					$row['id'],
					$row['created_at'],
					$row['customer_phone'],
					$row['part_name'],
					$row['vehicle_brand'],
					$row['vehicle_model'],
					$row['vehicle_trim'],
					$row['vehicle_year'],
					$row['status'],
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Change inquiry status.
	 */
	private function action_inquiry_status(): void {
		$id     = absint( $_REQUEST['id'] ?? 0 );
		$status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		if ( $id && '' !== $status ) {
			Inquiry::set_status( $id, $status );
		}
		$this->redirect_back( 'status-updated' );
	}

	/**
	 * Delete inquiry.
	 */
	private function action_inquiry_delete(): void {
		$id = absint( $_REQUEST['id'] ?? 0 );
		if ( $id ) {
			Inquiry::delete( $id );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'partino-chatbot-inquiries', 'partino_notice' => 'deleted' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Bulk delete inquiries.
	 */
	private function action_inquiry_bulk(): void {
		$ids = isset( $_REQUEST['inquiry_ids'] ) && is_array( $_REQUEST['inquiry_ids'] ) ? array_map( 'absint', wp_unslash( $_REQUEST['inquiry_ids'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$bulk = isset( $_REQUEST['bulk_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['bulk_action'] ) ) : '';

		if ( 'delete' === $bulk ) {
			foreach ( $ids as $id ) {
				if ( $id > 0 ) {
					Inquiry::delete( $id );
				}
			}
		} elseif ( str_starts_with( $bulk, 'status_' ) ) {
			$status = substr( $bulk, 7 );
			foreach ( $ids as $id ) {
				if ( $id > 0 ) {
					Inquiry::set_status( $id, $status );
				}
			}
		}
		$this->redirect_back( 'bulk-done' );
	}

	/**
	 * Save vehicle row.
	 */
	private function action_vehicle_save(): void {
		$id   = absint( $_POST['id'] ?? 0 );
		$data = array(
			'parent_id'  => absint( $_POST['parent_id'] ?? 0 ),
			'type'       => sanitize_key( wp_unslash( $_POST['type'] ?? 'brand' ) ),
			'name'       => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'name_en'    => sanitize_text_field( wp_unslash( $_POST['name_en'] ?? '' ) ),
			'aliases'    => sanitize_textarea_field( wp_unslash( $_POST['aliases'] ?? '' ) ),
			'year_start' => absint( $_POST['year_start'] ?? 0 ),
			'year_end'   => absint( $_POST['year_end'] ?? 0 ),
			'is_active'  => ! empty( $_POST['is_active'] ),
			'sort_order' => intval( $_POST['sort_order'] ?? 0 ),
		);
		Vehicle::save( $data, $id );
		$this->redirect_back( 'saved' );
	}

	/**
	 * Save part.
	 */
	private function action_part_save(): void {
		$id   = absint( $_POST['id'] ?? 0 );
		$data = array(
			'name'       => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'slug'       => sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) ),
			'category'   => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
			'icon'       => sanitize_text_field( wp_unslash( $_POST['icon'] ?? '' ) ),
			'aliases'    => sanitize_textarea_field( wp_unslash( $_POST['aliases'] ?? '' ) ),
			'is_active'  => ! empty( $_POST['is_active'] ),
			'sort_order' => intval( $_POST['sort_order'] ?? 0 ),
		);
		Part::save( $data, $id );
		$this->redirect_back( 'saved' );
	}

	/**
	 * Save quick action.
	 */
	private function action_qa_save(): void {
		$id   = absint( $_POST['id'] ?? 0 );
		$data = array(
			'title'      => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'icon'       => sanitize_text_field( wp_unslash( $_POST['icon'] ?? '' ) ),
			'part_id'    => absint( $_POST['part_id'] ?? 0 ),
			'is_active'  => ! empty( $_POST['is_active'] ),
			'sort_order' => intval( $_POST['sort_order'] ?? 0 ),
		);
		Quick_Action::save( $data, $id );
		$this->redirect_back( 'saved' );
	}

	/**
	 * Redirect back to the referring page with a notice.
	 *
	 * @param string $notice Notice slug.
	 */
	private function redirect_back( string $notice ): void {
		$url = wp_get_referer();
		if ( ! $url ) {
			$url = admin_url( 'admin.php?page=partino-chatbot' );
		}
		$url = remove_query_arg( array( 'partino_action', 'partino_nonce', 'id', 'status', '_wpnonce' ), $url );
		wp_safe_redirect( add_query_arg( 'partino_notice', rawurlencode( $notice ), $url ) );
		exit;
	}

	// -----------------------------------------------------------------------
	// Page renderers (delegate to templates).
	// -----------------------------------------------------------------------

	/**
	 * Require a page template.
	 *
	 * @param string $template Basename inside admin/pages/.
	 */
	private function render( string $template ): void {
		if ( ! current_user_can( $this->cap() ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'partino-smart-chatbot' ) );
		}
		$file = PARTINO_CHATBOT_PATH . 'admin/pages/' . $template . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}

	public function page_dashboard(): void {
		$this->render( 'dashboard' );
	}

	public function page_inquiries(): void {
		$this->render( 'inquiries' );
	}

	public function page_conversations(): void {
		$this->render( 'conversations' );
	}

	public function page_vehicles(): void {
		$this->render( 'vehicles' );
	}

	public function page_parts(): void {
		$this->render( 'parts' );
	}

	public function page_quick_actions(): void {
		$this->render( 'quick-actions' );
	}

	public function page_settings(): void {
		$this->render( 'settings' );
	}

	public function page_logs(): void {
		$this->render( 'logs' );
	}
}
