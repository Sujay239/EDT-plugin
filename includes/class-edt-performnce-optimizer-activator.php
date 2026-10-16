<?php
/**
 * Fired during plugin activation.
 *
 * @package    EDT_Performance_Optimizer
 * @subpackage EDT_Performance_Optimizer/includes
 */

if ( ! class_exists( 'EDT_Performance_Optimizer_Activator' ) ) {
	class EDT_Performance_Optimizer_Activator {

	/**
	 * Creates default options and database tables on activation.
	 */
	public static function activate(): void {
		// Set default options if not already present.
		if ( ! get_option( EDT_PERF_OPTIONS_KEY ) ) {
			$defaults = self::get_default_options();
			add_option( EDT_PERF_OPTIONS_KEY, $defaults, '', 'no' );
		}

		// Create log option.
		if ( ! get_option( EDT_PERF_LOG_KEY ) ) {
			add_option( EDT_PERF_LOG_KEY, [], '', 'no' );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Returns the default plugin configuration.
	 */
	public static function get_default_options(): array {
		return [
			// Master switch
			'master_enabled'                  => true,

			// Module toggles
			'module_asset_manager'            => true,
			'module_lcp_guard'               => true,
			'module_compatibility_scanner'   => true,
			'module_diagnostics'             => true,

			// Safe exclusions — always skip these pages regardless of other rules
			'excluded_page_ids'              => [],
			'excluded_path_patterns'         => [
				'/wp-admin/',
				'/wp-login.php',
				'/cart/',
				'/checkout/',
				'/my-account/',
				'/order-received/',
			],

			// Asset dequeue rules (populated by admin)
			'asset_rules'                    => self::get_default_asset_rules(),

			// Diagnostics
			'enable_html_comment_log'        => false, // only enable on staging
			'log_max_entries'                => 100,

			// Emergency rollback
			'emergency_rollback'             => false,

			// Version stamp for cache-busting option migrations
			'options_version'                => EDT_PERF_VERSION,
		];
	}

	/**
	 * Pre-populated asset rules based on forensic page-source audit.
	 * These are ONLY applied on anonymous, public, non-sensitive pages.
	 *
	 * Evidence: All rules derived from bv-dl-styles-list analysis across
	 * mascot.html, home.html, zetland.html, camperdown.html, marricillive.html,
	 * newtoen.html, alexenderia.html, puppyschool.html, dogtrainign.html
	 *
	 * Risk level: low = dequeue is safe with high confidence
	 *             medium = dequeue with conditional check
	 *             protected = never dequeue
	 */
	public static function get_default_asset_rules(): array {
		return [
			// ----------------------------------------------------------------
			// LEARNDASH — 13 CSS files loaded on every page despite no course
			// content being present on public marketing pages.
			// ----------------------------------------------------------------
			'learndash_quiz_front_css' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'Loaded on every page; required only on quiz pages',
				'enabled'     => true,
			],
			'learndash_style' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'LearnDash legacy style; not used on marketing pages',
				'enabled'     => true,
			],
			'learndash' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'LearnDash main CSS; not used on marketing pages',
				'enabled'     => true,
			],
			'sfwd_front_css' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'sfwd front CSS; not used on marketing pages',
				'enabled'     => true,
			],
			'jquery-dropdown-css' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'LearnDash dropdown; not used on marketing pages',
				'enabled'     => true,
			],
			'learndash_pager_css' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'Pager CSS; not used on marketing pages',
				'enabled'     => true,
			],
			'learndash_template_style_css' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'Template style; not used on marketing pages',
				'enabled'     => true,
			],
			'learndash_lesson_video' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'Video CSS; not used on marketing pages',
				'enabled'     => true,
			],
			'learndash-admin-bar' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page_and_not_logged_in',
				'risk'        => 'low',
				'evidence'    => 'Admin bar CSS; not needed for anonymous visitors',
				'enabled'     => true,
			],
			'learndash-course-grid-skin-grid' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'Course grid CSS; not used on marketing pages',
				'enabled'     => true,
			],
			'learndash-course-grid-pagination' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'Pagination CSS; not used on marketing pages',
				'enabled'     => true,
			],
			'learndash-course-grid-filter' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'Filter CSS; not used on marketing pages',
				'enabled'     => true,
			],
			'learndash-course-grid-card-grid-1' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'Grid card CSS; not used on marketing pages',
				'enabled'     => true,
			],
			'uncannyowl-learndash-toolkit-free' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'Uncanny Toolkit CSS; not needed on marketing pages',
				'enabled'     => true,
			],
			'oceanwp-learndash' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'OceanWP LearnDash integration CSS; not needed on marketing pages',
				'enabled'     => true,
			],

			// ----------------------------------------------------------------
			// SUMO SUBSCRIPTIONS — Loaded on every page
			// Safe to dequeue on non-subscription public pages
			// ----------------------------------------------------------------
			'sumosubs-blocks-integration' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'Subscription blocks; not needed on public marketing pages',
				'enabled'     => true,
			],
			'sumosubs-frontend' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'Subscription frontend; not needed on public marketing pages',
				'enabled'     => true,
			],
			'jquery-ui-style' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_woo_page_and_not_mec_page',
				'risk'        => 'medium',
				'evidence'    => 'jQuery UI; only needed on datepicker pages (MEC)',
				'enabled'     => false, // disabled by default; MEC uses datepicker
			],

			// ----------------------------------------------------------------
			// ULTIMATE AFFILIATE — Loaded on every page
			// ----------------------------------------------------------------
			'uap_public_style' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_affiliate_page',
				'risk'        => 'low',
				'evidence'    => 'Affiliate CSS; not needed on non-affiliate pages',
				'enabled'     => true,
			],
			'uap_templates' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_affiliate_page',
				'risk'        => 'low',
				'evidence'    => 'Affiliate template CSS; not needed on public marketing pages',
				'enabled'     => true,
			],

			// ----------------------------------------------------------------
			// WORDPRESS CORE — Dashicons for anonymous visitors
			// ----------------------------------------------------------------
			'dashicons' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_logged_in',
				'risk'        => 'low',
				'evidence'    => 'WordPress core icon font; not needed for anonymous visitors (saves ~59KB)',
				'enabled'     => true,
			],

			// ----------------------------------------------------------------
			// OCEANWP WOO — Woo-specific CSS on non-shop pages
			// ----------------------------------------------------------------
			'oceanwp-woocommerce' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'OceanWP Woo CSS; not needed on non-shop pages',
				'enabled'     => true,
			],
			'oceanwp-woo-star-font' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'Star font; not needed on non-shop pages',
				'enabled'     => true,
			],
			'oceanwp-woo-quick-view' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'Quick view modal; not needed on non-shop pages',
				'enabled'     => true,
			],
			'oceanwp-woo-mini-cart' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'WooCommerce mini cart styles not needed on non-shop pages',
				'enabled'     => true,
			],
			'woocommerce-layout' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'WooCommerce layout CSS not needed on non-shop pages',
				'enabled'     => true,
			],
			'woocommerce-smallscreen' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'WooCommerce smallscreen styles not needed on non-shop pages',
				'enabled'     => true,
			],
			'woocommerce-general' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'WooCommerce general frontend CSS not needed on non-shop pages',
				'enabled'     => true,
			],

			// ----------------------------------------------------------------
			// SCRIPTS
			// ----------------------------------------------------------------
			'learndash' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'LearnDash JS; not needed on marketing pages',
				'enabled'     => true,
			],
			'uncannyowl-learndash-toolkit-free' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'Uncanny Toolkit JS; not needed on marketing pages',
				'enabled'     => true,
			],
			'uap-public-functions' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_affiliate_page',
				'risk'        => 'low',
				'evidence'    => 'Affiliate JS; not needed on marketing pages without affiliate dashboards',
				'enabled'     => true,
			],
			'sumosubs-single-product' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'SUMO single product JS; not needed on marketing pages',
				'enabled'     => true,
			],
			'sumosubs-checkout' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'SUMO checkout JS; not needed on marketing pages',
				'enabled'     => true,
			],
			'wc-order-attribution' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'WooCommerce order attribution tracking not needed on non-shop marketing pages',
				'enabled'     => true,
			],
			'sourcebuster-js' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'Sourcebuster JS tracking not needed on non-shop marketing pages',
				'enabled'     => true,
			],
			'oceanwp-woo-mini-cart' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'WooCommerce mini cart JS not needed on non-shop pages',
				'enabled'     => true,
			],
			'oceanwp-woo-quick-view' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'WooCommerce quick view JS not needed on non-shop pages',
				'enabled'     => true,
			],
			'oceanwp-woocommerce-custom-features' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'WooCommerce custom features JS not needed on non-shop pages',
				'enabled'     => true,
			],
			'woocommerce' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'Core WooCommerce frontend script not needed on non-shop pages',
				'enabled'     => true,
			],
			'wc-cart-fragments' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'WooCommerce cart fragments AJAX not needed on non-shop pages',
				'enabled'     => true,
			],
			'wc-add-to-cart' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'WooCommerce add to cart JS not needed on non-shop pages',
				'enabled'     => true,
			],
			'wc-add-to-cart-variation' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'WooCommerce variation script not needed on marketing pages',
				'enabled'     => true,
			],
			'widget-woocommerce-menu-cart' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_woo_page',
				'risk'        => 'low',
				'evidence'    => 'Elementor menu cart styles not needed on non-shop pages',
				'enabled'     => true,
			],
			'learndash-course-grid-skin-grid' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'LearnDash course grid script not needed on marketing pages',
				'enabled'     => true,
			],
			'learndash-course-grid-elementor-compatibility' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_learndash_page',
				'risk'        => 'low',
				'evidence'    => 'LearnDash Elementor compatibility script not needed on marketing pages',
				'enabled'     => true,
			],
			// Modern Events Calendar (MEC) assets on non-MEC pages
			'mec-frontend-style' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_mec_page',
				'risk'        => 'medium',
				'evidence'    => 'MEC frontend style; not needed on pages without events calendar (saves 75KB)',
				'enabled'     => true,
			],
			'mec-select2-style' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_mec_page',
				'risk'        => 'medium',
				'evidence'    => 'MEC select2 style; not needed on non-event pages',
				'enabled'     => true,
			],
			'mec-font-icons' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_mec_page',
				'risk'        => 'medium',
				'evidence'    => 'MEC icon font; not needed on non-event pages',
				'enabled'     => true,
			],
			'mec-tooltip-style' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_mec_page',
				'risk'        => 'medium',
				'evidence'    => 'MEC tooltip CSS; not needed on non-event pages',
				'enabled'     => true,
			],
			'mec-general-calendar-style' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_mec_page',
				'risk'        => 'medium',
				'evidence'    => 'MEC general calendar style; not needed on non-event pages',
				'enabled'     => true,
			],
			'featherlight' => [
				'action'      => 'dequeue',
				'type'        => 'style',
				'condition'   => 'not_mec_page',
				'risk'        => 'medium',
				'evidence'    => 'MEC lightbox CSS; not needed on non-event pages',
				'enabled'     => true,
			],
			'mec-frontend-script' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_mec_page',
				'risk'        => 'medium',
				'evidence'    => 'MEC frontend JS; not needed on non-event pages',
				'enabled'     => true,
			],
			'mec-events-script' => [
				'action'      => 'dequeue',
				'type'        => 'script',
				'condition'   => 'not_mec_page',
				'risk'        => 'medium',
				'evidence'    => 'MEC events JS; not needed on non-event pages',
				'enabled'     => true,
			],
		];
	}
}
}

if ( ! class_exists( 'Edt_Performnce_Optimizer_Activator' ) && class_exists( 'EDT_Performance_Optimizer_Activator' ) ) {
	class_alias( 'EDT_Performance_Optimizer_Activator', 'Edt_Performnce_Optimizer_Activator' );
}
