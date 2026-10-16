<?php
/**
 * Admin-specific functionality — dashboard, settings, and AJAX handlers.
 *
 * @package    EDT_Performance_Optimizer
 * @subpackage EDT_Performance_Optimizer/admin
 */

class Edt_Performnce_Optimizer_Admin {

	private string $plugin_name;
	private string $version;
	private EDT_Perf_Modules_Diagnostics $diagnostics;
	private EDT_Perf_Modules_Compatibility_Scanner $scanner;

	public function __construct(
		string $plugin_name,
		string $version,
		EDT_Perf_Modules_Diagnostics $diagnostics,
		EDT_Perf_Modules_Compatibility_Scanner $scanner
	) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->diagnostics = $diagnostics;
		$this->scanner     = $scanner;
	}

	public function enqueue_styles( ?string $hook_suffix = '' ): void {
		if ( empty( $hook_suffix ) || ! str_contains( (string) $hook_suffix, 'edt-perf' ) ) {
			return;
		}
		wp_enqueue_style(
			$this->plugin_name,
			EDT_PERF_PLUGIN_URL . 'admin/css/edt-performnce-optimizer-admin.css',
			[],
			$this->version,
			'all'
		);
	}

	public function enqueue_scripts( ?string $hook_suffix = '' ): void {
		if ( empty( $hook_suffix ) || ! str_contains( (string) $hook_suffix, 'edt-perf' ) ) {
			return;
		}
		wp_enqueue_script(
			$this->plugin_name,
			EDT_PERF_PLUGIN_URL . 'admin/js/edt-performnce-optimizer-admin.js',
			[ 'jquery' ],
			$this->version,
			true
		);
	}

	/**
	 * Register admin menu pages.
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'EDT Performance', 'edt-performnce-optimizer' ),
			__( 'EDT Perf', 'edt-performnce-optimizer' ),
			'manage_options',
			'edt-perf-dashboard',
			[ $this, 'render_dashboard' ],
			'dashicons-performance',
			80
		);

		add_submenu_page(
			'edt-perf-dashboard',
			__( 'Dashboard', 'edt-performnce-optimizer' ),
			__( 'Dashboard', 'edt-performnce-optimizer' ),
			'manage_options',
			'edt-perf-dashboard',
			[ $this, 'render_dashboard' ]
		);

		add_submenu_page(
			'edt-perf-dashboard',
			__( 'Asset Rules', 'edt-performnce-optimizer' ),
			__( 'Asset Rules', 'edt-performnce-optimizer' ),
			'manage_options',
			'edt-perf-asset-rules',
			[ $this, 'render_asset_rules' ]
		);

		add_submenu_page(
			'edt-perf-dashboard',
			__( 'Settings', 'edt-performnce-optimizer' ),
			__( 'Settings', 'edt-performnce-optimizer' ),
			'manage_options',
			'edt-perf-settings',
			[ $this, 'render_settings' ]
		);

		add_submenu_page(
			'edt-perf-dashboard',
			__( 'Diagnostics Log', 'edt-performnce-optimizer' ),
			__( 'Diagnostics', 'edt-performnce-optimizer' ),
			'manage_options',
			'edt-perf-diagnostics',
			[ $this, 'render_diagnostics' ]
		);
	}

	public function register_settings(): void {
		register_setting(
			'edt_perf_options_group',
			EDT_PERF_OPTIONS_KEY,
			[ 'sanitize_callback' => [ $this, 'sanitize_options' ] ]
		);
	}

	/**
	 * Sanitize and validate options before saving.
	 */
	public function sanitize_options( mixed $input ): array {
		$existing = get_option( EDT_PERF_OPTIONS_KEY, [] );
		$clean    = $existing;

		if ( ! is_array( $input ) ) {
			return $existing;
		}

		// Boolean toggles.
		$bool_keys = [
			'master_enabled',
			'module_asset_manager',
			'module_lcp_guard',
			'module_compatibility_scanner',
			'module_diagnostics',
			'enable_html_comment_log',
			'emergency_rollback',
		];

		foreach ( $bool_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = (bool) $input[ $key ];
			}
		}

		// Integer.
		if ( isset( $input['log_max_entries'] ) ) {
			$clean['log_max_entries'] = max( 10, min( 1000, (int) $input['log_max_entries'] ) );
		}

		// Excluded page IDs — handle comma-separated string or array.
		if ( isset( $input['excluded_page_ids_raw'] ) ) {
			$raw_ids = explode( ',', (string) $input['excluded_page_ids_raw'] );
			$clean['excluded_page_ids'] = array_values( array_filter( array_map( 'absint', array_map( 'trim', $raw_ids ) ) ) );
		} elseif ( isset( $input['excluded_page_ids'] ) ) {
			$ids = array_map( 'absint', (array) $input['excluded_page_ids'] );
			$clean['excluded_page_ids'] = array_values( array_filter( $ids ) );
		}

		// Excluded path patterns — array of sanitized strings.
		if ( isset( $input['excluded_path_patterns'] ) ) {
			$patterns = (array) $input['excluded_path_patterns'];
			$clean['excluded_path_patterns'] = array_map(
				fn( $p ) => sanitize_text_field( $p ),
				$patterns
			);
		}

		// Asset rules — validate each rule structure.
		if ( isset( $input['asset_rules'] ) && is_array( $input['asset_rules'] ) ) {
			$existing_rules = $existing['asset_rules'] ?? [];
			foreach ( $input['asset_rules'] as $handle => $rule ) {
				$safe_handle = sanitize_key( $handle );
				if ( isset( $existing_rules[ $safe_handle ] ) && is_array( $rule ) ) {
					// Only allow toggling 'enabled' from the UI; other fields read-only.
					$clean['asset_rules'][ $safe_handle ] = array_merge(
						$existing_rules[ $safe_handle ],
						[ 'enabled' => ! empty( $rule['enabled'] ) ]
					);
				}
			}
		}

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Admin form POST handlers (using admin-post.php pattern for security)
	// -------------------------------------------------------------------------

	public function handle_save_options(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}
		check_admin_referer( 'edt_perf_save_options' );

		$input = $_POST['edt_perf'] ?? [];

		// If saving from settings page, handle unchecked checkboxes explicitly.
		if ( ! empty( $_POST['edt_perf_settings_form'] ) ) {
			$bool_keys = [
				'master_enabled',
				'module_asset_manager',
				'module_lcp_guard',
				'module_compatibility_scanner',
				'enable_html_comment_log',
				'emergency_rollback',
			];
			foreach ( $bool_keys as $key ) {
				$input[ $key ] = ! empty( $input[ $key ] );
			}
		}

		$clean = $this->sanitize_options( $input );
		update_option( EDT_PERF_OPTIONS_KEY, $clean );

		$redirect_page = ! empty( $_POST['edt_perf_settings_form'] ) ? 'edt-perf-settings' : 'edt-perf-asset-rules';
		wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=' . $redirect_page ) ) );
		exit;
	}

	public function handle_clear_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}
		check_admin_referer( 'edt_perf_clear_log' );

		$this->diagnostics->clear_log();

		wp_safe_redirect( add_query_arg( 'cleared', '1', admin_url( 'admin.php?page=edt-perf-diagnostics' ) ) );
		exit;
	}

	public function handle_rollback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}
		check_admin_referer( 'edt_perf_rollback' );

		$options = get_option( EDT_PERF_OPTIONS_KEY, [] );
		$options['emergency_rollback'] = true;
		update_option( EDT_PERF_OPTIONS_KEY, $options );

		wp_safe_redirect( add_query_arg( 'rollback', '1', admin_url( 'admin.php?page=edt-perf-dashboard' ) ) );
		exit;
	}

	public function handle_rescan(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}
		check_admin_referer( 'edt_perf_rescan' );

		$this->diagnostics->invalidate_scan_cache();

		wp_safe_redirect( add_query_arg( 'rescanned', '1', admin_url( 'admin.php?page=edt-perf-dashboard' ) ) );
		exit;
	}

	public function show_admin_notices(): void {
		$options = get_option( EDT_PERF_OPTIONS_KEY, [] );

		if ( $options['emergency_rollback'] ?? false ) {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>EDT Performance Optimizer:</strong> ';
			esc_html_e( '🚨 Emergency Rollback is ACTIVE — all optimization rules are disabled. Disable rollback in Settings.', 'edt-performnce-optimizer' );
			echo '</p></div>';
		}
	}

	public function invalidate_caches(): void {
		delete_transient( 'edt_perf_compatibility_scan' );
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'edt-performnce-optimizer' ) );
		}
		$scan = $this->scanner->scan();
		require EDT_PERF_PLUGIN_DIR . 'admin/partials/edt-performnce-optimizer-admin-display.php';
	}

	public function render_asset_rules(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'edt-performnce-optimizer' ) );
		}
		require EDT_PERF_PLUGIN_DIR . 'admin/partials/edt-perf-asset-rules.php';
	}

	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'edt-performnce-optimizer' ) );
		}
		require EDT_PERF_PLUGIN_DIR . 'admin/partials/edt-perf-settings.php';
	}

	public function render_diagnostics(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'edt-performnce-optimizer' ) );
		}
		$scan = $this->scanner->scan();
		require EDT_PERF_PLUGIN_DIR . 'admin/partials/edt-perf-diagnostics.php';
	}

	/**
	 * Returns the diagnostics module (used in partials).
	 */
	public function get_diagnostics(): EDT_Perf_Modules_Diagnostics {
		return $this->diagnostics;
	}
}
