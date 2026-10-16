<?php
/**
 * The core plugin class. Wires all modules together.
 *
 * @link       https://sujay-kumar-kotal.vercel.app
 * @since      1.0.0
 * @package    EDT_Performance_Optimizer
 * @subpackage EDT_Performance_Optimizer/includes
 */

if ( ! class_exists( 'Edt_Performnce_Optimizer' ) ) {
	class Edt_Performnce_Optimizer {

	protected Edt_Performnce_Optimizer_Loader $loader;
	protected string $plugin_name;
	protected string $version;

	// Module instances
	private EDT_Perf_Modules_Page_Classifier $classifier;
	private EDT_Perf_Modules_Compatibility_Scanner $scanner;
	private EDT_Perf_Modules_Conditional_Asset_Manager $asset_manager;
	private EDT_Perf_Modules_Lcp_Priority_Manager $lcp_manager;
	private EDT_Perf_Modules_Diagnostics $diagnostics;

	public function __construct() {
		$this->version     = defined( 'EDT_PERF_VERSION' ) ? EDT_PERF_VERSION : '2.0.0';
		$this->plugin_name = 'edt-performnce-optimizer';

		$this->load_dependencies();
		$this->set_locale();
		$this->init_modules();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies(): void {
		require_once EDT_PERF_PLUGIN_DIR . 'includes/class-edt-performnce-optimizer-loader.php';
		require_once EDT_PERF_PLUGIN_DIR . 'includes/class-edt-performnce-optimizer-i18n.php';

		// Module classes
		require_once EDT_PERF_PLUGIN_DIR . 'includes/modules/class-page-classifier.php';
		require_once EDT_PERF_PLUGIN_DIR . 'includes/modules/class-compatibility-scanner.php';
		require_once EDT_PERF_PLUGIN_DIR . 'includes/modules/class-conditional-asset-manager.php';
		require_once EDT_PERF_PLUGIN_DIR . 'includes/modules/class-lcp-priority-manager.php';
		require_once EDT_PERF_PLUGIN_DIR . 'includes/modules/class-diagnostics.php';

		// Admin class
		require_once EDT_PERF_PLUGIN_DIR . 'admin/class-edt-performnce-optimizer-admin.php';

		// Public class
		require_once EDT_PERF_PLUGIN_DIR . 'public/class-edt-performnce-optimizer-public.php';

		$this->loader = new Edt_Performnce_Optimizer_Loader();
	}

	private function init_modules(): void {
		$this->upgrade_options_if_needed();

		// Page classifier must be initialized first — other modules depend on it.
		$this->classifier    = new EDT_Perf_Modules_Page_Classifier();
		$this->scanner       = new EDT_Perf_Modules_Compatibility_Scanner();
		$this->asset_manager = new EDT_Perf_Modules_Conditional_Asset_Manager( $this->classifier );
		$this->lcp_manager   = new EDT_Perf_Modules_Lcp_Priority_Manager( $this->classifier );
		$this->diagnostics   = new EDT_Perf_Modules_Diagnostics( $this->classifier, $this->scanner );
	}

	/**
	 * Seamlessly migrates stored database options from 2.0.0 handles (with -css/-js)
	 * to canonical WordPress handles in 2.1.0, preserving all user toggles.
	 */
	private function upgrade_options_if_needed(): void {
		$options = get_option( EDT_PERF_OPTIONS_KEY );
		if ( ! is_array( $options ) ) {
			return;
		}

		$current_ver   = $options['options_version'] ?? '2.0.0';
		$needs_upgrade = version_compare( (string) $current_ver, '2.4.0', '<' );

		if ( ! empty( $options['asset_rules'] ) && is_array( $options['asset_rules'] ) ) {
			foreach ( array_keys( $options['asset_rules'] ) as $k ) {
				if ( str_ends_with( (string) $k, '-css' ) || str_ends_with( (string) $k, '-js' ) ) {
					$needs_upgrade = true;
					break;
				}
			}
		}

		if ( ! $needs_upgrade ) {
			return;
		}

		require_once EDT_PERF_PLUGIN_DIR . 'includes/class-edt-performnce-optimizer-activator.php';
		$default_rules  = EDT_Performance_Optimizer_Activator::get_default_asset_rules();
		$migrated_rules = [];

		foreach ( $default_rules as $canonical_handle => $rule_def ) {
			$migrated_rules[ $canonical_handle ] = $rule_def;
		}

		if ( ! empty( $options['asset_rules'] ) && is_array( $options['asset_rules'] ) ) {
			foreach ( $options['asset_rules'] as $old_key => $old_data ) {
				$canonical = preg_replace( '/-(?:css|js)$/', '', (string) $old_key );
				if ( isset( $migrated_rules[ $canonical ] ) && isset( $old_data['enabled'] ) ) {
					$migrated_rules[ $canonical ]['enabled'] = (bool) $old_data['enabled'];
				}
			}
		}

		$options['asset_rules']     = $migrated_rules;
		$options['options_version'] = '2.4.0';
		update_option( EDT_PERF_OPTIONS_KEY, $options );
	}

	private function set_locale(): void {
		$plugin_i18n = new Edt_Performnce_Optimizer_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	private function define_admin_hooks(): void {
		$plugin_admin = new Edt_Performnce_Optimizer_Admin(
			$this->plugin_name,
			$this->version,
			$this->diagnostics,
			$this->scanner
		);

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		$this->loader->add_action( 'admin_post_edt_perf_save_options', $plugin_admin, 'handle_save_options' );
		$this->loader->add_action( 'admin_post_edt_perf_clear_log', $plugin_admin, 'handle_clear_log' );
		$this->loader->add_action( 'admin_post_edt_perf_rollback', $plugin_admin, 'handle_rollback' );
		$this->loader->add_action( 'admin_post_edt_perf_rescan', $plugin_admin, 'handle_rescan' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'show_admin_notices' );

		// Invalidate compatibility scan on plugin/theme updates.
		$this->loader->add_action( 'upgrader_process_complete', $plugin_admin, 'invalidate_caches' );
	}

	private function define_public_hooks(): void {
		// Module hooks — must run AFTER WordPress resolves the current query (wp hook).
		$this->loader->add_action( 'wp', $this, 'init_public_modules' );
	}

	/**
	 * Init public-facing modules after the WP query is resolved.
	 * This guarantees is_page(), is_singular(), etc. return correct values.
	 */
	public function init_public_modules(): void {
		// Classifier needs to be run after wp() — call classify() to warm the cache.
		$this->classifier->classify();

		// Init each module (modules check their own safety conditions).
		$this->asset_manager->init();
		$this->lcp_manager->init();
	}

	public function run(): void {
		$this->loader->run();
	}

	public function get_plugin_name(): string {
		return $this->plugin_name;
	}

	public function get_loader(): Edt_Performnce_Optimizer_Loader {
		return $this->loader;
	}

	public function get_version(): string {
		return $this->version;
	}
}
}

if ( ! class_exists( 'EDT_Performance_Optimizer' ) && class_exists( 'Edt_Performnce_Optimizer' ) ) {
	class_alias( 'Edt_Performnce_Optimizer', 'EDT_Performance_Optimizer' );
}
