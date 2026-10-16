<?php
/**
 * CompatibilityScanner — inventories active optimizers and detects
 * potential conflicts. Warns in admin when features are duplicated.
 *
 * @package    EDT_Performance_Optimizer
 * @subpackage EDT_Performance_Optimizer/includes/modules
 */

class EDT_Perf_Modules_Compatibility_Scanner {

	/**
	 * Detected optimizer inventory.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $inventory = [];

	/**
	 * Conflict warnings.
	 *
	 * @var string[]
	 */
	private array $warnings = [];

	/**
	 * Run the compatibility scan and build the inventory.
	 * Cached as a transient for 1 hour.
	 */
	public function scan(): array {
		$cached = get_transient( 'edt_perf_compatibility_scan' );
		if ( $cached && is_array( $cached ) ) {
			return $cached;
		}

		$this->detect_airlift();
		$this->detect_ac_smart_performance_guard();
		$this->detect_kinsta_cache();
		$this->detect_elementor_performance();
		$this->detect_wordpress_native();
		$this->generate_conflict_warnings();

		$result = [
			'inventory' => $this->inventory,
			'warnings'  => $this->warnings,
			'scanned_at' => current_time( 'mysql' ),
		];

		$ttl = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
		set_transient( 'edt_perf_compatibility_scan', $result, $ttl );

		return $result;
	}

	/**
	 * Returns warnings for display in admin.
	 *
	 * @return string[]
	 */
	public function get_warnings(): array {
		$result = $this->scan();
		return $result['warnings'];
	}

	// -------------------------------------------------------------------------
	// Detector methods — each fills $this->inventory[$optimizer_name]
	// -------------------------------------------------------------------------

	private function is_plugin_active_safe( string $plugin_basename ): bool {
		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( $plugin_basename );
		}

		$active_plugins = (array) get_option( 'active_plugins', [] );
		if ( in_array( $plugin_basename, $active_plugins, true ) ) {
			return true;
		}

		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
			$network_active = (array) get_site_option( 'active_sitewide_plugins', [] );
			if ( isset( $network_active[ $plugin_basename ] ) ) {
				return true;
			}
		}

		return false;
	}

	private function is_plugin_active_matching( string $pattern ): bool {
		$active_plugins = (array) get_option( 'active_plugins', [] );
		foreach ( $active_plugins as $plugin ) {
			if ( preg_match( $pattern, $plugin ) ) {
				return true;
			}
		}

		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
			$network_active = (array) get_site_option( 'active_sitewide_plugins', [] );
			foreach ( array_keys( $network_active ) as $plugin ) {
				if ( preg_match( $pattern, $plugin ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function detect_airlift(): void {
		$advanced_cache = defined( 'WP_CONTENT_DIR' ) && file_exists( WP_CONTENT_DIR . '/advanced-cache.php' )
			? (string) @file_get_contents( WP_CONTENT_DIR . '/advanced-cache.php' )
			: '';

		$active = defined( 'AIRLIFT_PLUGIN_LOADED' ) ||
		          defined( 'AIRLIFT_VERSION' ) ||
		          defined( 'AL_REQUEST_ID' ) ||
		          defined( 'AL_VERSION' ) ||
		          defined( 'BV_PLUGIN_VERSION' ) ||
		          defined( 'BV_VERSION' ) ||
		          class_exists( 'ALWPSettings' ) ||
		          class_exists( 'ALWPAction' ) ||
		          class_exists( 'ALWPSiteInfo' ) ||
		          class_exists( 'ALWPAdmin' ) ||
		          class_exists( 'ALInfo' ) ||
		          class_exists( 'BVSupport' ) ||
		          class_exists( 'AirLift' ) ||
		          class_exists( 'AirLift_Plugin' ) ||
		          function_exists( 'al_clear_cache_config' ) ||
		          function_exists( 'al_clear_bv_services_config' ) ||
		          function_exists( 'bv_get_option' ) ||
		          $this->is_plugin_active_safe( 'airlift/airlift.php' ) ||
		          $this->is_plugin_active_matching( '/airlift/i' ) ||
		          ( defined( 'WP_PLUGIN_DIR' ) && is_dir( WP_PLUGIN_DIR . '/airlift' ) ) ||
		          ( defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/airlift.php' ) ) ||
		          get_option( 'al_services_config' ) !== false ||
		          get_option( 'al_cache_config' ) !== false ||
		          get_option( 'airlift_settings' ) !== false ||
		          get_option( 'airlift_options' ) !== false ||
		          get_option( 'bv_site_id' ) !== false ||
		          get_option( 'bv_account_id' ) !== false ||
		          ( ! empty( $advanced_cache ) && ( str_contains( $advanced_cache, 'airlift' ) || str_contains( $advanced_cache, 'al_opt_content' ) || str_contains( $advanced_cache, 'aircdn' ) ) );

		$this->inventory['airlift'] = [
			'name'   => 'AirLift',
			'active' => (bool) $active,
			'owns'   => [
				'critical_css_inline'   => 'bv-critical-css style elements detected in page source',
				'js_delay'              => 'type="bv_inline_delayed_js" detected — AirLift owns JS deferral',
				'css_delay'             => 'bv-dl-styles-list JSON detected — AirLift owns CSS deferral',
				'image_preload'         => 'class="bv-preload" link tags detected — AirLift owns hero preloads',
				'image_lazy_load'       => 'bv-image-lazyload class + bvDefaultImageLazyObserver runtime',
				'image_webp_avif'       => '.bv.webp / .bv.avif URL suffix transformation',
				'font_balancing'        => 'bv-balanced-font-css style element',
				'cdn_delivery'          => 'd-0bb27392-azpvkm.ps1.aircdn.net CDN for CSS/fonts',
				'fetchpriority_high'    => 'fetchpriority="high" on hero images already set by AirLift',
				'loading_eager'         => 'loading="eager" on hero images already set by AirLift',
			],
			'our_plugin_must_not' => [
				'Do NOT add <link rel="preload"> for hero images — AirLift already preloads them',
				'Do NOT set fetchpriority="high" on hero — AirLift already does this',
				'Do NOT set loading="eager" on hero — AirLift already does this',
				'Do NOT delay or dequeue JS that AirLift is already deferring',
				'Do NOT regenerate or inline critical CSS — AirLift handles this',
			],
		];
	}

	private function detect_ac_smart_performance_guard(): void {
		$active = defined( 'ACSP_VERSION' ) ||
		          defined( 'ACSP_FILE' ) ||
		          defined( 'ACSPG_VERSION' ) ||
		          class_exists( 'ACSP_Plugin' ) ||
		          class_exists( 'ACSP_Settings' ) ||
		          class_exists( 'ACSmartPerformanceGuard' ) ||
		          $this->is_plugin_active_safe( 'ac-smart-performance-guard/ac-smart-performance-guard.php' ) ||
		          $this->is_plugin_active_safe( 'ac-smart-performance/ac-smart-performance.php' ) ||
		          $this->is_plugin_active_matching( '/ac-smart-performance/i' ) ||
		          get_option( 'acsp_settings' ) !== false;

		$this->inventory['ac_smart_performance_guard'] = [
			'name'   => 'AC Smart Performance Guard',
			'active' => (bool) $active,
			'owns'   => [
				'lcp_visibility'  => 'acsp-lcp-instant-render: .LCP/[data-ac-lcp="1"] content-visibility override',
				'topbar_animation' => 'acsp-composited-anim: GPU-composited top-bar flash animation',
			],
			'our_plugin_must_not' => [
				'Do NOT override content-visibility on .LCP — ACSPG already handles this',
				'Do NOT remove acsp-lcp-instant-render or acsp-composited-anim style blocks',
			],
		];
	}

	private function detect_kinsta_cache(): void {
		$active = defined( 'KINSTA_CACHE_ZONE' ) ||
		          ( defined( 'WP_CONTENT_DIR' ) && file_exists( WP_CONTENT_DIR . '/mu-plugins/kinsta-mu-plugins.php' ) );

		$this->inventory['kinsta'] = [
			'name'   => 'Kinsta Cache / CDN',
			'active' => $active,
			'owns'   => [
				'page_cache'     => 'Full-page cache via Kinsta MU Plugin; WP_CACHE=true',
				'server_caching' => 'Nginx-level caching controlled by Kinsta',
				'cdn'            => 'Kinsta CDN for static assets',
			],
			'our_plugin_must_not' => [
				'Do NOT implement a PHP full-page cache — Kinsta owns this',
				'Do NOT modify advanced-cache.php or Kinsta MU plugin files',
				'Do NOT set headers that would bypass Kinsta cache (no-store, no-cache) on public pages',
			],
		];
	}

	private function detect_elementor_performance(): void {
		$active = defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' );

		$optimized_assets = false;
		$lazy_bg          = false;

		if ( $active && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ) {
			$kits_manager = \Elementor\Plugin::$instance->kits_manager ?? null;
			if ( $kits_manager && method_exists( $kits_manager, 'get_active_kit' ) ) {
				$kit = $kits_manager->get_active_kit();
				if ( $kit && method_exists( $kit, 'get_settings' ) ) {
					$settings         = (array) $kit->get_settings();
					$optimized_assets = ! empty( $settings['optimized_assets_loading'] );
					$lazy_bg          = ! empty( $settings['lazy_load_background_images'] );
				}
			}
		}

		$this->inventory['elementor'] = [
			'name'               => 'Elementor',
			'active'             => $active,
			'optimized_assets'   => $optimized_assets,
			'lazy_bg_images'     => $lazy_bg,
			'owns'               => [
				'conditional_css' => 'Widget-level CSS files (widget-image-css, widget-heading-css, etc.)',
				'inline_css'      => 'Per-post inline <style> in <head> for Elementor page styles',
			],
			'our_plugin_must_not' => [
				'Do NOT dequeue Elementor widget CSS that IS being used on the page',
				'Do NOT dequeue elementor-frontend or elementor-pro-frontend CSS/JS',
				'Do NOT interfere with Elementor CSS-print-method=internal setting',
			],
		];
	}

	private function detect_wordpress_native(): void {
		$this->inventory['wordpress_native'] = [
			'name'  => 'WordPress Native',
			'owns'  => [
				'native_lazy_load' => 'loading="lazy" on offscreen images — WordPress core since 5.5',
				'fetchpriority'    => 'WordPress 6.3+ sets fetchpriority="high" on first above-fold image',
			],
			'our_plugin_must_not' => [
				'Do NOT remove native lazy-load attributes — AirLift and WP core both manage this',
			],
		];
	}

	// -------------------------------------------------------------------------
	// Conflict detection
	// -------------------------------------------------------------------------

	private function generate_conflict_warnings(): void {
		$options = get_option( EDT_PERF_OPTIONS_KEY, [] );
		$rules   = $options['asset_rules'] ?? [];

		// Warn if any of our rules try to dequeue assets we identified as protected.
		$protected_handles = [
			'elementor-frontend',
			'elementor-pro-frontend',
			'oceanwp-style',
			'child-style',
		];

		foreach ( $rules as $handle => $rule ) {
			if ( ( $rule['enabled'] ?? false ) && in_array( $handle, $protected_handles, true ) ) {
				$this->warnings[] = sprintf(
					'⚠️ Rule for "%s" is enabled but this handle is protected. Disable this rule.',
					esc_html( $handle )
				);
			}
		}

		// Warn if AirLift is active but our plugin is also trying to do image priority.
		if ( ! empty( $this->inventory['airlift']['active'] ) ) {
			$this->warnings[] = '✅ AirLift is active and owns: critical CSS, JS delay, image preloads, WebP/AVIF. Our plugin will NOT duplicate these.';
		}

		if ( ! empty( $this->inventory['ac_smart_performance_guard']['active'] ) ) {
			$this->warnings[] = '✅ AC Smart Performance Guard is active and owns: .LCP visibility rules. Our plugin will NOT add competing LCP CSS.';
		}

		if ( ! empty( $this->inventory['kinsta']['active'] ) ) {
			$this->warnings[] = '✅ Kinsta cache is active. Our plugin does NOT implement page-level caching.';
		}
	}
}

