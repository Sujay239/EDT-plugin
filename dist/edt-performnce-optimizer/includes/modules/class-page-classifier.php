<?php
/**
 * PageClassifier — resolves the current request's page type, template,
 * active features, user state, and cacheability. Used by all other modules
 * to make safe conditional decisions.
 *
 * @package    EDT_Performance_Optimizer
 * @subpackage EDT_Performance_Optimizer/includes/modules
 */

class EDT_Perf_Modules_Page_Classifier {

	/**
	 * Cached result of classify().
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $classification = null;

	/**
	 * Classify the current request.
	 * Runs once and is cached for the lifetime of the request.
	 *
	 * @return array<string,mixed>
	 */
	public function classify(): array {
		if ( $this->classification !== null ) {
			return $this->classification;
		}

		// Must run inside a WordPress context with query vars available.
		// Safe to call from wp hook onward.
		$this->classification = [
			// ---- Route type -----------------------------------------------
			'is_home'            => is_front_page() || is_home(),
			'is_singular'        => is_singular(),
			'is_page'            => is_page(),
			'is_single'          => is_single(),
			'is_archive'         => is_archive(),
			'is_search'          => is_search(),
			'is_404'             => is_404(),
			'is_feed'            => is_feed(),
			'is_robots'          => is_robots(),

			// ---- Admin / editing / REST -----------------------------------
			'is_admin'           => is_admin(),
			'is_rest'            => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'is_ajax'            => wp_doing_ajax(),
			'is_cron'            => wp_doing_cron(),
			'is_elementor_edit'  => $this->is_elementor_editing(),
			'is_customizer'      => is_customize_preview(),

			// ---- User state -----------------------------------------------
			'is_logged_in'       => is_user_logged_in(),

			// ---- WooCommerce ---------------------------------------------
			'is_woo_shop'        => $this->is_woo_shop(),
			'is_woo_product'     => $this->is_woo_product(),
			'is_woo_cart'        => $this->is_woo_cart(),
			'is_woo_checkout'    => $this->is_woo_checkout(),
			'is_woo_account'     => $this->is_woo_account(),
			'is_woo_order'       => $this->is_woo_order(),
			'is_woo_page'        => $this->is_any_woo_page(),

			// ---- LearnDash -----------------------------------------------
			'is_learndash_page'  => $this->is_learndash_page(),

			// ---- MEC ---------------------------------------------------------
			'is_mec_page'        => $this->is_mec_page(),

			// ---- Affiliate ---------------------------------------------------
			'is_affiliate_page'  => $this->is_affiliate_page(),

			// ---- Cacheability ------------------------------------------------
			'is_cacheable'       => $this->is_cacheable_page(),

			// ---- Misc ---------------------------------------------------------
			'page_id'            => get_queried_object_id(),
			'post_type'          => get_post_type() ?: '',
			'request_path'       => $this->get_clean_request_path(),
			'template'           => $this->get_page_template_name(),
		];

		return $this->classification;
	}

	/**
	 * Returns the classification value for a key.
	 */
	public function get( string $key, mixed $default = false ): mixed {
		$c = $this->classify();
		return $c[ $key ] ?? $default;
	}

	// -------------------------------------------------------------------------
	// Condition evaluators used by AssetManager rules
	// -------------------------------------------------------------------------

	public function passes_condition( string $condition ): bool {
		$c = $this->classify();

		return match ( $condition ) {
			'not_learndash_page'                    => ! $c['is_learndash_page'],
			'not_learndash_page_and_not_logged_in'  => ! $c['is_learndash_page'] && ! $c['is_logged_in'],
			'not_woo_page'                          => ! $c['is_woo_page'],
			'not_woo_page_and_not_mec_page'         => ! $c['is_woo_page'] && ! $c['is_mec_page'],
			'not_mec_page'                          => ! $c['is_mec_page'],
			'not_affiliate_page'                    => ! $c['is_affiliate_page'],
			'not_logged_in'                         => ! $c['is_logged_in'],
			'always'                                => true,
			'never'                                 => false,
			default                                 => false,
		};
	}

	// -------------------------------------------------------------------------
	// Safety: is the page safe to apply aggressive optimizations?
	// -------------------------------------------------------------------------

	/**
	 * Returns true only when aggressive asset removal is safe.
	 * Any sensitive context returns false.
	 */
	public function is_safe_to_optimize(): bool {
		$c = $this->classify();

		// Hard exclusions — never optimize these.
		if (
			$c['is_admin']          ||
			$c['is_rest']           ||
			$c['is_ajax']           ||
			$c['is_cron']           ||
			$c['is_feed']           ||
			$c['is_elementor_edit'] ||
			$c['is_customizer']     ||
			$c['is_woo_cart']       ||
			$c['is_woo_checkout']   ||
			$c['is_woo_order']      ||
			$c['is_woo_account']
		) {
			return false;
		}

		// If the URL matches any excluded path patterns from settings, skip.
		$options  = get_option( EDT_PERF_OPTIONS_KEY, [] );
		$patterns = $options['excluded_path_patterns'] ?? [];
		$path     = $c['request_path'];

		foreach ( $patterns as $pattern ) {
			if ( $pattern && str_contains( $path, $pattern ) ) {
				return false;
			}
		}

		// Excluded page IDs.
		$excluded_ids = array_map( 'intval', (array) ( $options['excluded_page_ids'] ?? [] ) );
		if ( $c['page_id'] && in_array( (int) $c['page_id'], $excluded_ids, true ) ) {
			return false;
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function is_elementor_editing(): bool {
		if ( isset( $_GET['elementor-preview'] ) ) {
			return true;
		}
		if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) && is_object( \Elementor\Plugin::$instance ) && isset( \Elementor\Plugin::$instance->preview ) && is_object( \Elementor\Plugin::$instance->preview ) ) {
			return (bool) \Elementor\Plugin::$instance->preview->is_preview_mode();
		}
		return false;
	}

	private function is_woo_shop(): bool {
		return function_exists( 'is_shop' ) && is_shop();
	}

	private function is_woo_product(): bool {
		return function_exists( 'is_product' ) && is_product();
	}

	private function is_woo_cart(): bool {
		return function_exists( 'is_cart' ) && is_cart();
	}

	private function is_woo_checkout(): bool {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		// Also catch order-pay, order-received.
		$path = $this->get_clean_request_path();
		return str_contains( $path, '/order-pay/' ) ||
		       str_contains( $path, '/order-received/' );
	}

	private function is_woo_account(): bool {
		return function_exists( 'is_account_page' ) && is_account_page();
	}

	private function is_woo_order(): bool {
		$path = $this->get_clean_request_path();
		return str_contains( $path, '/order-received/' ) ||
		       str_contains( $path, '/order-pay/' );
	}

	private function is_any_woo_page(): bool {
		return $this->is_woo_shop() ||
		       $this->is_woo_product() ||
		       $this->is_woo_cart() ||
		       $this->is_woo_checkout() ||
		       $this->is_woo_account() ||
		       $this->is_woo_order();
	}

	private function is_learndash_page(): bool {
		// Check post type.
		$learndash_post_types = [
			'sfwd-courses',
			'sfwd-lessons',
			'sfwd-topic',
			'sfwd-quiz',
			'sfwd-certificates',
			'sfwd-essays',
			'sfwd-transactions',
			'sfwd-assignment',
			'groups',
			'ld-exam',
		];

		if ( in_array( get_post_type(), $learndash_post_types, true ) ) {
			return true;
		}

		// Check if current page is the LearnDash courses archive.
		if ( function_exists( 'learndash_get_post_type_slug' ) ) {
			$slug = learndash_get_post_type_slug( 'course' );
			if ( is_post_type_archive( $slug ) ) {
				return true;
			}
		}

		// Check for LearnDash shortcodes in page content (quick).
		$post = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
		if ( $post instanceof WP_Post ) {
			$ld_shortcodes = [
				'[ld_course_list',
				'[ld_lesson_list',
				'[ld-quiz',
				'[learndash_',
				'[course_',
			];
			foreach ( $ld_shortcodes as $sc ) {
				if ( str_contains( $post->post_content ?? '', $sc ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function is_mec_page(): bool {
		// MEC event post types.
		$mec_types = [ 'mec-events', 'mec_calendars' ];
		if ( in_array( get_post_type(), $mec_types, true ) ) {
			return true;
		}

		// MEC booking pages by URL pattern.
		$path = $this->get_clean_request_path();
		if ( str_contains( $path, '/events/' ) || str_contains( $path, '/event/' ) ) {
			return true;
		}

		// MEC widgets detected in Elementor-rendered page content or post meta.
		$post = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
		if ( $post instanceof WP_Post ) {
			$content = $post->post_content ?? '';
			if ( stripos( $content, 'mec' ) !== false || stripos( $content, 'modern-events-calendar' ) !== false ) {
				return true;
			}

			if ( function_exists( 'get_post_meta' ) ) {
				$elem_data = get_post_meta( $post->ID, '_elementor_data', true );
				if ( is_string( $elem_data ) && ( stripos( $elem_data, 'mec' ) !== false || stripos( $elem_data, 'modern-events-calendar' ) !== false ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function is_affiliate_page(): bool {
		// UAP affiliate pages.
		$path = $this->get_clean_request_path();
		return str_contains( $path, '/affiliate' ) ||
		       str_contains( $path, '/referral' ) ||
		       get_post_type() === 'uap_rank';
	}

	private function is_cacheable_page(): bool {
		return ! is_user_logged_in() &&
		       ! $this->is_any_woo_page() &&
		       ! wp_doing_ajax() &&
		       ! is_admin();
	}

	private function get_clean_request_path(): string {
		$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
		return is_string( $path ) ? $path : '/';
	}

	private function get_page_template_name(): string {
		$page_id = function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0;
		if ( ! $page_id || ! function_exists( 'get_page_template_slug' ) ) {
			return '';
		}
		return (string) get_page_template_slug( $page_id );
	}
}
