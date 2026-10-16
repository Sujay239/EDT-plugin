<?php
/**
 * ConditionalAssetManager — evaluates per-page, per-handle asset rules
 * and dequeues CSS/JS that is confirmed unused on the current page.
 *
 * Safety guarantees:
 * - Only runs when PageClassifier::is_safe_to_optimize() returns true
 * - Each rule requires an explicit 'condition' key evaluated by PageClassifier
 * - Each rule has a risk level; high-risk rules default to disabled
 * - Emergency rollback option disables all dequeues
 * - Logs every action for admin review
 *
 * @package    EDT_Performance_Optimizer
 * @subpackage EDT_Performance_Optimizer/includes/modules
 */

class EDT_Perf_Modules_Conditional_Asset_Manager {

	private EDT_Perf_Modules_Page_Classifier $classifier;
	private array $options;
	private array $action_log = [];
	private array $processed_handles = [];
	private bool $persisted = false;

	public function __construct( EDT_Perf_Modules_Page_Classifier $classifier ) {
		$this->classifier = $classifier;
		$this->options    = get_option( EDT_PERF_OPTIONS_KEY, [] );
	}

	/**
	 * Register hooks. Called by the core plugin at the right priority.
	 */
	public function init(): void {
		// Hook late enough that all plugins have registered their assets (wp_enqueue_scripts priority 99999).
		add_action( 'wp_enqueue_scripts', [ $this, 'apply_rules' ], 99999 );

		// Also hook late into print actions to catch any assets registered during wp_head/wp_footer.
		add_action( 'wp_print_styles', [ $this, 'apply_rules' ], 99999 );
		add_action( 'wp_print_scripts', [ $this, 'apply_rules' ], 99999 );

		// Hook into style_loader_tag and script_loader_tag to eliminate render-blocking tags natively.
		add_filter( 'style_loader_tag', [ $this, 'filter_style_loader_tag' ], 999, 4 );
		add_filter( 'script_loader_tag', [ $this, 'filter_script_loader_tag' ], 999, 3 );

		// Persist the log on shutdown (and wp_footer as fallback) so admin always has real data.
		add_action( 'shutdown', [ $this, 'persist_log' ] );
		add_action( 'wp_footer', [ $this, 'persist_log' ], 999 );

		// Add HTML comment diagnostic log if enabled.
		if ( $this->options['enable_html_comment_log'] ?? false ) {
			add_action( 'wp_footer', [ $this, 'output_diagnostic_comment' ], 9999 );
		}
	}

	/**
	 * Main entry point — evaluate all rules and apply dequeues.
	 */
	public function apply_rules(): void {
		// Emergency rollback — skip everything.
		if ( $this->options['emergency_rollback'] ?? false ) {
			if ( ! isset( $this->processed_handles['__emergency_rollback'] ) ) {
				$this->processed_handles['__emergency_rollback'] = true;
				$this->log( 'SKIP', 'all', 'Emergency rollback active — no dequeues applied' );
			}
			return;
		}

		// Master switch.
		if ( ! ( $this->options['master_enabled'] ?? true ) ) {
			if ( ! isset( $this->processed_handles['__master_disabled'] ) ) {
				$this->processed_handles['__master_disabled'] = true;
				$this->log( 'SKIP', 'all', 'Master switch disabled' );
			}
			return;
		}

		// Module switch.
		if ( ! ( $this->options['module_asset_manager'] ?? true ) ) {
			if ( ! isset( $this->processed_handles['__module_disabled'] ) ) {
				$this->processed_handles['__module_disabled'] = true;
				$this->log( 'SKIP', 'all', 'Asset manager module disabled' );
			}
			return;
		}

		// Safety check — never optimize sensitive pages.
		if ( ! $this->classifier->is_safe_to_optimize() ) {
			if ( ! isset( $this->processed_handles['__not_safe'] ) ) {
				$this->processed_handles['__not_safe'] = true;
				$this->log( 'SKIP', 'all', 'Page classifier: not safe to optimize (admin/checkout/edit/etc.)' );
			}
			return;
		}

		// Direct core optimizations for anonymous marketing visitors.
		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
			wp_deregister_style( 'dashicons' );
		}

		if ( $this->classifier->passes_condition( 'not_woo_page' ) ) {
			wp_dequeue_script( 'wc-add-to-cart-variation' );
			wp_dequeue_script( 'wc-cart-fragments' );
			wp_dequeue_script( 'woocommerce' );
			wp_dequeue_script( 'wc-add-to-cart' );
			wp_dequeue_style( 'widget-woocommerce-menu-cart' );
			wp_dequeue_script( 'underscore' );
			wp_dequeue_script( 'wp-util' );
		}

		if ( $this->classifier->passes_condition( 'not_learndash_page' ) ) {
			wp_dequeue_script( 'learndash-course-grid-skin-grid' );
			wp_dequeue_script( 'learndash-course-grid-elementor-compatibility' );
		}

		if ( $this->classifier->passes_condition( 'not_mec_page' ) ) {
			$mec_styles = [ 'mec-select2-style', 'mec-font-icons', 'mec-frontend-style', 'mec-tooltip-style', 'mec-tooltip-shadow-style', 'featherlight', 'mec-lity-style', 'mec-general-calendar-style' ];
			foreach ( $mec_styles as $ms ) {
				wp_dequeue_style( $ms );
			}
			$mec_scripts = [ 'mec-typekit-script', 'featherlight', 'mec-select2-script', 'mec-general-calendar-script', 'mec-tooltip-script', 'mec-frontend-script', 'mec-tracking-script', 'mec-events-script', 'mec-lity-script', 'mec-colorbrightness-script', 'mec-owl-carousel-script' ];
			foreach ( $mec_scripts as $mjs ) {
				wp_dequeue_script( $mjs );
			}
		}

		$rules = $this->options['asset_rules'] ?? [];

		foreach ( $rules as $handle => $rule ) {
			$this->evaluate_rule( (string) $handle, (array) $rule );
		}
	}

	/**
	 * Evaluate a single rule and apply if conditions are met.
	 */
	private function evaluate_rule( string $handle, array $rule ): void {
		// Rule must be enabled.
		if ( ! ( $rule['enabled'] ?? false ) ) {
			return;
		}

		// Action must be defined.
		$action = $rule['action'] ?? '';
		if ( ! in_array( $action, [ 'dequeue', 'deregister' ], true ) ) {
			return;
		}

		$type = $rule['type'] ?? 'style';

		// Resolve possible handle variations (e.g. learndash_quiz_front_css vs learndash_quiz_front_css-css).
		$candidates = [ $handle ];
		if ( $type === 'style' ) {
			if ( str_ends_with( $handle, '-css' ) ) {
				$candidates[] = substr( $handle, 0, -4 );
			} else {
				$candidates[] = $handle . '-css';
			}
		} elseif ( $type === 'script' ) {
			if ( str_ends_with( $handle, '-js' ) ) {
				$candidates[] = substr( $handle, 0, -3 );
			} else {
				$candidates[] = $handle . '-js';
			}
		}
		$candidates = array_unique( $candidates );

		// Check if any candidate is currently enqueued or registered.
		$active_handle = null;
		foreach ( $candidates as $cand ) {
			if ( $type === 'style' ) {
				if ( wp_style_is( $cand, 'enqueued' ) || wp_style_is( $cand, 'registered' ) ) {
					$active_handle = $cand;
					break;
				}
			} elseif ( $type === 'script' ) {
				if ( wp_script_is( $cand, 'enqueued' ) || wp_script_is( $cand, 'registered' ) ) {
					$active_handle = $cand;
					break;
				}
			}
		}

		// If already processed in this request, skip duplicate action.
		if ( isset( $this->processed_handles[ $handle ] ) ) {
			return;
		}

		// Evaluate condition.
		$condition = $rule['condition'] ?? 'never';
		if ( ! $this->classifier->passes_condition( $condition ) ) {
			// Only log KEPT if the asset was actually enqueued on this page.
			if ( $active_handle !== null ) {
				$this->processed_handles[ $handle ] = true;
				$this->log(
					'KEPT',
					$active_handle,
					sprintf( 'Condition "%s" not met — asset kept', $condition )
				);
			}
			return;
		}

		// Condition passed. If asset was found or queued, dequeue all variants.
		if ( $active_handle !== null ) {
			$this->processed_handles[ $handle ] = true;

			foreach ( $candidates as $cand ) {
				if ( $type === 'style' ) {
					wp_dequeue_style( $cand );
					if ( $action === 'deregister' ) {
						wp_deregister_style( $cand );
					}
				} elseif ( $type === 'script' ) {
					wp_dequeue_script( $cand );
					if ( $action === 'deregister' ) {
						wp_deregister_script( $cand );
					}
				}
			}

			$this->log(
				$type === 'style' ? 'DEQUEUED_STYLE' : 'DEQUEUED_SCRIPT',
				$active_handle,
				sprintf( 'Condition "%s" met. Evidence: %s', $condition, $rule['evidence'] ?? '' )
			);
		}
	}

	/**
	 * Record an action to the in-request log.
	 */
	private function log( string $action, string $handle, string $reason ): void {
		$time = function_exists( 'current_time' ) ? current_time( 'mysql' ) : date( 'Y-m-d H:i:s' );
		$this->action_log[] = [
			'time'   => $time,
			'action' => $action,
			'handle' => $handle,
			'reason' => $reason,
			'url'    => $_SERVER['REQUEST_URI'] ?? '',
		];
	}

	/**
	 * Outputs the action log as an HTML comment in footer.
	 * Only enabled when 'enable_html_comment_log' = true in options.
	 */
	public function output_diagnostic_comment(): void {
		if ( empty( $this->action_log ) ) {
			return;
		}
		echo "\n<!-- EDT Performance Optimizer — Asset Manager Log\n";
		foreach ( $this->action_log as $entry ) {
			echo esc_html( sprintf(
				"  [%s] %s — %s\n",
				$entry['action'],
				$entry['handle'],
				$entry['reason']
			) );
		}
		echo "-->\n";
	}

	/**
	 * Returns the current request log (used by Diagnostics module).
	 */
	public function get_action_log(): array {
		return $this->action_log;
	}

	/**
	 * Persist the log entry to the WP option log for admin review.
	 */
	public function persist_log(): void {
		if ( $this->persisted || empty( $this->action_log ) ) {
			return;
		}
		$this->persisted = true;

		$stored  = get_option( EDT_PERF_LOG_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		$max     = (int) ( $this->options['log_max_entries'] ?? 100 );
		$new_log = array_merge( $stored, $this->action_log );

		// Keep only the most recent $max entries.
		if ( count( $new_log ) > $max ) {
			$new_log = array_slice( $new_log, -$max );
		}

		update_option( EDT_PERF_LOG_KEY, $new_log, 'no' );
	}

	/**
	 * Filters stylesheet tags to remove unused styles (like dashicons for guests)
	 * and convert below-the-fold non-critical styles into non-render-blocking links.
	 */
	public function filter_style_loader_tag( string $tag, string $handle, string $href, string $media ): string {
		if ( $this->options['emergency_rollback'] ?? false ) {
			return $tag;
		}
		if ( ! ( $this->options['master_enabled'] ?? true ) ) {
			return $tag;
		}
		if ( ! ( $this->options['module_asset_manager'] ?? true ) ) {
			return $tag;
		}
		if ( ! $this->classifier->is_safe_to_optimize() ) {
			return $tag;
		}

		$canonical = preg_replace( '/-css$/', '', $handle );

		// 1. Dashicons: never load for anonymous public visitors.
		if ( ( $canonical === 'dashicons' || $handle === 'dashicons' ) && ! is_user_logged_in() ) {
			return '';
		}

		// 2. Elementor WooCommerce Menu Cart on non-Woo pages.
		if ( $canonical === 'widget-woocommerce-menu-cart' && $this->classifier->passes_condition( 'not_woo_page' ) ) {
			return '';
		}

		// 3. Modern Events Calendar (MEC) styles when not an MEC page.
		$mec_handles = [
			'mec-select2-style',
			'mec-font-icons',
			'mec-frontend-style',
			'mec-tooltip-style',
			'mec-tooltip-shadow-style',
			'featherlight',
			'mec-lity-style',
			'mec-general-calendar-style',
		];
		if ( in_array( $canonical, $mec_handles, true ) && $this->classifier->passes_condition( 'not_mec_page' ) ) {
			return '';
		}

		// 4. Convert below-the-fold and non-critical stylesheets to non-render-blocking.
		$async_handles = [
			// Modern Events Calendar (when on MEC page, calendar is below fold)
			'mec-select2-style',
			'mec-font-icons',
			'mec-frontend-style',
			'mec-tooltip-style',
			'mec-tooltip-shadow-style',
			'featherlight',
			'mec-lity-style',
			'mec-general-calendar-style',
			// Business Reviews Bundle
			'brb-public-main-css',
			// Quform
			'quform',
			// Below-fold Elementor widgets & icons
			'widget-animated-headline',
			'widget-nested-accordion',
			'widget-media-carousel',
			'widget-carousel-module-base',
			'swiper',
			'e-swiper',
			'widget-social-icons',
			'oe-widgets-style',
			'simple-line-icons',
			'elementor-icons-fa-brands',
		];

		if ( in_array( $canonical, $async_handles, true ) || in_array( $handle, $async_handles, true ) ) {
			// If already async, skip
			if ( str_contains( $tag, 'media="print"' ) ) {
				return $tag;
			}
			$new_tag = preg_replace( '/\bmedia=[\'"][^\'"]*[\'"]/i', 'media="print" onload="this.media=\'all\'"', $tag );
			if ( $new_tag && $new_tag !== $tag ) {
				$new_tag .= sprintf( '<noscript><link rel="stylesheet" id="%s-noscript" href="%s" media="all"></noscript>', esc_attr( $handle ), esc_url( $href ) );
				return $new_tag;
			}
		}

		return $tag;
	}

	/**
	 * Filters script tags to defer render-blocking scripts in head and drop unused scripts.
	 */
	public function filter_script_loader_tag( string $tag, string $handle, string $src ): string {
		if ( $this->options['emergency_rollback'] ?? false ) {
			return $tag;
		}
		if ( ! ( $this->options['master_enabled'] ?? true ) ) {
			return $tag;
		}
		if ( ! ( $this->options['module_asset_manager'] ?? true ) ) {
			return $tag;
		}
		if ( ! $this->classifier->is_safe_to_optimize() ) {
			return $tag;
		}

		$canonical = preg_replace( '/-js$/', '', $handle );

		// 1. LearnDash Course Grid scripts on non-LearnDash pages.
		if ( in_array( $canonical, [ 'learndash-course-grid-skin-grid', 'learndash-course-grid-elementor-compatibility' ], true ) && $this->classifier->passes_condition( 'not_learndash_page' ) ) {
			return '';
		}

		// 2. WooCommerce scripts on non-Woo pages.
		if ( in_array( $canonical, [ 'wc-add-to-cart-variation', 'wc-cart-fragments', 'sourcebuster-js', 'wc-order-attribution' ], true ) && $this->classifier->passes_condition( 'not_woo_page' ) ) {
			return '';
		}

		// 3. Underscore & wp-util on non-Woo marketing pages.
		if ( in_array( $canonical, [ 'underscore', 'wp-util' ], true ) && $this->classifier->passes_condition( 'not_woo_page' ) ) {
			return '';
		}

		// 4. MEC scripts on non-MEC pages.
		$mec_scripts = [
			'mec-typekit-script',
			'featherlight',
			'mec-select2-script',
			'mec-general-calendar-script',
			'mec-tooltip-script',
			'mec-frontend-script',
			'mec-tracking-script',
			'mec-events-script',
			'mec-lity-script',
			'mec-colorbrightness-script',
			'mec-owl-carousel-script',
		];
		if ( in_array( $canonical, $mec_scripts, true ) && $this->classifier->passes_condition( 'not_mec_page' ) ) {
			return '';
		}

		// 5. Defer jQuery and jQuery-Migrate in head so they don't block initial rendering.
		if ( in_array( $canonical, [ 'jquery-core', 'jquery-migrate', 'jquery' ], true ) ) {
			if ( ! str_contains( $tag, 'defer' ) && ! str_contains( $tag, 'async' ) ) {
				return str_replace( ' src=', ' defer="defer" src=', $tag );
			}
		}

		return $tag;
	}
}

