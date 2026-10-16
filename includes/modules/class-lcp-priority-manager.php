<?php
/**
 * LCPCandidateResolver — identifies the actual LCP candidate within the
 * .LCP parent container and provides safe server-side guards.
 *
 * IMPORTANT: AirLift already handles:
 *   - hero image preload (<link class="bv-preload">)
 *   - fetchpriority="high" on hero
 *   - loading="eager" on hero
 *   - WebP/AVIF conversion
 *
 * This module's role is to:
 *   1. Remove duplicated fetchpriority="high" from the site LOGO image
 *      (which competes with the true hero LCP image)
 *   2. Verify the LCP element has no content-visibility:auto applied
 *   3. Add structured data / HTML comment for diagnostic output
 *   4. Provide JavaScript diagnostic snippet (diagnostic mode only)
 *
 * @package    EDT_Performance_Optimizer
 * @subpackage EDT_Performance_Optimizer/includes/modules
 */

class EDT_Perf_Modules_Lcp_Priority_Manager {

	private EDT_Perf_Modules_Page_Classifier $classifier;
	private array $options;

	public function __construct( EDT_Perf_Modules_Page_Classifier $classifier ) {
		$this->classifier = $classifier;
		$this->options    = get_option( EDT_PERF_OPTIONS_KEY, [] );
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		if ( ! ( $this->options['module_lcp_guard'] ?? true ) ) {
			return;
		}

		if ( $this->options['emergency_rollback'] ?? false ) {
			return;
		}

		if ( ! $this->classifier->is_safe_to_optimize() ) {
			return;
		}

		// Fix logo and non-hero fetchpriority competition.
		// AirLift preloads the logo, badge, and hero image all with fetchpriority="high".
		// We hook into output buffering at high priority on template_redirect
		// to ensure only the true hero LCP image retains maximum priority.
		add_action( 'template_redirect', [ $this, 'start_output_buffer' ], 1 );

		// Clean up WordPress core emoji scripts & styles if safe.
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		// Output diagnostic JS snippet on staging/debug only.
		if ( $this->options['enable_html_comment_log'] ?? false ) {
			add_action( 'wp_footer', [ $this, 'output_lcp_diagnostic_js' ], 998 );
		}
	}

	/**
	 * Start output buffering to post-process the HTML response.
	 */
	public function start_output_buffer(): void {
		// Only process public, anonymous, non-admin pages.
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		ob_start( [ $this, 'process_html_response' ] );
	}

	/**
	 * Process the full HTML response to fix priority signals and ensure optimal Core Web Vitals.
	 */
	public function process_html_response( string $html ): string {
		if ( empty( $html ) || strlen( $html ) < 100 ) {
			return $html;
		}

		// Only process HTML responses.
		if ( ! str_contains( $html, '<html' ) ) {
			return $html;
		}

		$html = $this->fix_logo_fetchpriority( $html );
		$html = $this->fix_preload_priorities( $html );
		$html = $this->fix_image_dimensions( $html );
		$html = $this->inject_hero_preload( $html );
		$html = $this->ensure_hero_high_priority( $html );
		$html = $this->deduplicate_recaptcha( $html );
		$html = $this->inject_font_display_swap( $html );
		$html = $this->clean_orphaned_assets( $html );
		$html = $this->optimize_render_blocking_resources( $html );

		return $html;
	}

	/**
	 * Remove fetchpriority="high" from the site logo image.
	 */
	private function fix_logo_fetchpriority( string $html ): string {
		$count   = 0;
		$pattern = '/<img\b(?=[^>]*(?:custom-logo|site-logo-img|wp-image-61476|Black-on-transparent-logo))(?=[^>]*\bfetchpriority=["\']high["\'])[^>]*>/i';

		$replaced = preg_replace_callback( $pattern, function ( array $matches ) use ( &$count ) {
			$count++;
			return preg_replace( '/\bfetchpriority=["\']high["\']/i', 'fetchpriority="auto"', $matches[0] );
		}, $html );

		if ( $count > 0 && $replaced !== null ) {
			if ( $this->options['enable_html_comment_log'] ?? false ) {
				$replaced .= sprintf(
					"\n<!-- EDT Perf: Fixed logo fetchpriority from high->auto (%d occurrence(s)) -->",
					$count
				);
			}
			return $replaced;
		}

		return $html;
	}

	/**
	 * Demote non-hero image preloads that compete with the actual LCP hero image.
	 * AirLift generates fetchpriority="high" for the logo and trust badges,
	 * causing the browser to delay downloading the true hero LCP image.
	 */
	private function fix_preload_priorities( string $html ): string {
		$count   = 0;
		// Matches preload link tags for logo and badges that have fetchpriority="high"
		$pattern = '/<link\b(?=[^>]*\brel=["\']preload["\'])(?=[^>]*\bas=["\']image["\'])(?=[^>]*(?:Black-on-transparent-logo|site-logo|custom-logo|2023_SG_WINNER))(?=[^>]*\bfetchpriority=["\']high["\'])[^>]*>/i';

		$replaced = preg_replace_callback( $pattern, function ( array $matches ) use ( &$count ) {
			$count++;
			return preg_replace( '/\bfetchpriority=["\']high["\']/i', 'fetchpriority="low"', $matches[0] );
		}, $html );

		if ( $count > 0 && $replaced !== null ) {
			if ( $this->options['enable_html_comment_log'] ?? false ) {
				$replaced .= sprintf(
					"\n<!-- EDT Perf: Demoted non-hero preloads to fetchpriority=low (%d occurrence(s)) -->",
					$count
				);
			}
			return $replaced;
		}

		return $html;
	}

	/**
	 * Automatically identifies the above-the-fold hero image and injects a
	 * high-priority preload tag into <head> for optimal Core Web Vitals LCP.
	 */
	private function inject_hero_preload( string $html ): string {
		$body_pos = stripos( $html, '<body' );
		if ( $body_pos === false ) {
			return $html;
		}

		$head_end_pos = stripos( $html, '</head>' );
		if ( $head_end_pos === false ) {
			return $html;
		}

		$body = substr( $html, $body_pos );

		// Find images in body.
		if ( ! preg_match_all( '/<img\b([^>]+)>/i', $body, $matches ) ) {
			return $html;
		}

		$desktop_hero = null;
		$mobile_hero  = null;

		foreach ( $matches[1] as $attrs ) {
			if ( ! preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $attrs, $src_m ) ) {
				continue;
			}
			$src = $src_m[1];

			// Exclude logos, icons, avatars, badges, winners, and analytics pixels.
			if (
				stripos( $src, 'logo' ) !== false ||
				stripos( $src, 'Black-on-transparent' ) !== false ||
				stripos( $src, 'facebook' ) !== false ||
				stripos( $src, 'google' ) !== false ||
				stripos( $src, 'analytics' ) !== false ||
				stripos( $src, 'gravatar' ) !== false ||
				stripos( $src, 'winner' ) !== false ||
				stripos( $src, 'badge' ) !== false ||
				stripos( $src, 'award' ) !== false ||
				stripos( $attrs, 'custom-logo' ) !== false ||
				stripos( $attrs, 'site-logo' ) !== false ||
				stripos( $attrs, 'avatar' ) !== false
			) {
				continue;
			}

			// Dimensions filter.
			$width  = preg_match( '/\bwidth=["\']?(\d+)/i', $attrs, $wm ) ? (int) $wm[1] : 0;
			$height = preg_match( '/\bheight=["\']?(\d+)/i', $attrs, $hm ) ? (int) $hm[1] : 0;

			if ( ( $width > 0 && $width < 100 ) || ( $height > 0 && $height < 100 ) ) {
				continue;
			}

			$srcset = preg_match( '/\bsrcset=["\']([^"\']+)["\']/i', $attrs, $sm ) ? $sm[1] : null;
			$sizes  = preg_match( '/\bsizes=["\']([^"\']+)["\']/i', $attrs, $szm ) ? $szm[1] : null;

			$candidate = [
				'src'    => $src,
				'srcset' => $srcset,
				'sizes'  => $sizes,
				'width'  => $width,
				'height' => $height,
			];

			if ( stripos( $src, '-mobile' ) !== false || ( $width > 0 && $width <= 400 ) ) {
				if ( $mobile_hero === null ) {
					$mobile_hero = $candidate;
				}
			} else {
				if ( $desktop_hero === null ) {
					$desktop_hero = $candidate;
				}
			}

			if ( $desktop_hero !== null && $mobile_hero !== null ) {
				break;
			}
		}

		if ( $desktop_hero === null && $mobile_hero === null ) {
			return $html;
		}

		// Check if preload tag for this hero image already exists in head.
		$head = substr( $html, 0, $head_end_pos );
		$target_src = ( $desktop_hero ?? $mobile_hero )['src'];
		$target_base = basename( parse_url( $target_src, PHP_URL_PATH ) ?? $target_src );
		if ( $target_base && stripos( $head, $target_base ) !== false && stripos( $head, 'rel="preload"' ) !== false ) {
			return $html;
		}

		$preload_tags = "\n<!-- EDT Perf: Injected LCP Hero Preload for Core Web Vitals -->\n";
		if ( $mobile_hero !== null && $desktop_hero !== null && $mobile_hero['src'] !== $desktop_hero['src'] ) {
			$preload_tags .= sprintf(
				'<link rel="preload" as="image" href="%s" media="(max-width: 767px)" fetchpriority="high">' . "\n",
				esc_url( $mobile_hero['src'] )
			);
			if ( ! empty( $desktop_hero['srcset'] ) && ! empty( $desktop_hero['sizes'] ) ) {
				$preload_tags .= sprintf(
					'<link rel="preload" as="image" href="%s" imagesrcset="%s" imagesizes="%s" media="(min-width: 768px)" fetchpriority="high">' . "\n",
					esc_url( $desktop_hero['src'] ),
					esc_attr( $desktop_hero['srcset'] ),
					esc_attr( $desktop_hero['sizes'] )
				);
			} else {
				$preload_tags .= sprintf(
					'<link rel="preload" as="image" href="%s" media="(min-width: 768px)" fetchpriority="high">' . "\n",
					esc_url( $desktop_hero['src'] )
				);
			}
		} else {
			$hero = $desktop_hero ?? $mobile_hero;
			if ( ! empty( $hero['srcset'] ) && ! empty( $hero['sizes'] ) ) {
				$preload_tags .= sprintf(
					'<link rel="preload" as="image" href="%s" imagesrcset="%s" imagesizes="%s" fetchpriority="high">' . "\n",
					esc_url( $hero['src'] ),
					esc_attr( $hero['srcset'] ),
					esc_attr( $hero['sizes'] )
				);
			} else {
				$preload_tags .= sprintf(
					'<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
					esc_url( $hero['src'] )
				);
			}
		}

		// Inject near the top of <head> right after viewport meta or <head> tag.
		if ( preg_match( '/<meta\b[^>]*name=["\']viewport["\'][^>]*>/i', $html, $vp_m ) ) {
			return str_replace( $vp_m[0], $vp_m[0] . $preload_tags, $html );
		} elseif ( preg_match( '/<head\b[^>]*>/i', $html, $hd_m ) ) {
			return str_replace( $hd_m[0], $hd_m[0] . $preload_tags, $html );
		}

		return $html;
	}

	/**
	 * Ensures the above-the-fold hero <img> tag in the HTML body has
	 * fetchpriority="high", loading="eager", and no loading="lazy".
	 */
	private function ensure_hero_high_priority( string $html ): string {
		return preg_replace_callback(
			'/<img\b(?=[^>]*(?:attachment-medium_large|attachment-large|wp-image-61715|wp-image-61704|wp-image-61580|wp-image-61644|wp-image-61517|wp-image-61658|wp-image-61440))[^>]*>/i',
			function ( array $matches ) {
				$tag = $matches[0];
				// Remove loading="lazy".
				$tag = preg_replace( '/\bloading=["\']lazy["\']/i', 'loading="eager"', $tag );
				// Ensure fetchpriority="high".
				if ( ! preg_match( '/\bfetchpriority=/i', $tag ) ) {
					$tag = preg_replace( '/<img\b/i', '<img fetchpriority="high"', $tag );
				} else {
					$tag = preg_replace( '/\bfetchpriority=["\'][^"\']*["\']/i', 'fetchpriority="high"', $tag );
				}
				// Ensure loading="eager".
				if ( ! preg_match( '/\bloading=/i', $tag ) ) {
					$tag = preg_replace( '/<img\b/i', '<img loading="eager"', $tag );
				}
				return $tag;
			},
			$html
		);
	}

	/**
	 * Fix missing explicit width and height on key images (like footer logo) to prevent CLS.
	 */
	private function fix_image_dimensions( string $html ): string {
		// Fix Black-on-transparent-logo missing width & height attributes
		return preg_replace_callback(
			'/<img\b(?=[^>]*\b(?:Black-on-transparent-logo|wp-image-42963))(?![^>]*\bwidth=)[^>]*>/i',
			function( array $matches ) {
				$tag = $matches[0];
				// Inject width="180" height="49"
				return preg_replace( '/<img\b/i', '<img width="180" height="49"', $tag );
			},
			$html
		);
	}

	/**
	 * Deduplicate Google reCAPTCHA scripts to prevent duplicate api.js downloads and multiple recaptcha__en.js runtimes.
	 */
	private function deduplicate_recaptcha( string $html ): string {
		// If Quform reCAPTCHA is present on the page, remove other reCAPTCHA script instances (e.g. bare api.js and MEC api.js)
		if ( str_contains( $html, 'QuformRecaptchaLoaded' ) ) {
			return preg_replace_callback(
				'/<script\b(?=[^>]*\bsrc=["\'][^"\']*recaptcha\/api\.js)[^>]*>[\s\S]*?<\/script>\s*/i',
				function( array $matches ) {
					if ( str_contains( $matches[0], 'QuformRecaptchaLoaded' ) ) {
						return $matches[0];
					}
					return '';
				},
				$html
			);
		}

		// If not Quform, ensure only the first reCAPTCHA script tag is kept
		$count = 0;
		return preg_replace_callback(
			'/<script\b(?=[^>]*\bsrc=["\'][^"\']*recaptcha\/api\.js)[^>]*>[\s\S]*?<\/script>\s*/i',
			function( array $matches ) use ( &$count ) {
				$count++;
				return ( $count === 1 ) ? $matches[0] : '';
			},
			$html
		);
	}

	/**
	 * Injects font-display: swap for eicons, Font Awesome, and Simple Line Icons,
	 * plus forced reflow mitigation for review sliders.
	 */
	private function inject_font_display_swap( string $html ): string {
		if ( str_contains( $html, 'id="edt-perf-font-display-swap"' ) ) {
			return $html;
		}

		$style = "<style id=\"edt-perf-font-display-swap\">\n" .
			"@font-face{font-family:'eicons';font-display:swap;src:url('/wp-content/plugins/elementor/assets/lib/eicons/fonts/eicons.woff2?5.54.0') format('woff2');}\n" .
			"@font-face{font-family:'Font Awesome 5 Free';font-style:normal;font-weight:900;font-display:swap;src:url('/wp-content/plugins/elementor/assets/lib/font-awesome/webfonts/fa-solid-900.woff2') format('woff2');}\n" .
			"@font-face{font-family:'Font Awesome 5 Brands';font-style:normal;font-weight:400;font-display:swap;src:url('/wp-content/plugins/elementor/assets/lib/font-awesome/webfonts/fa-brands-400.woff2') format('woff2');}\n" .
			"@font-face{font-family:'simple-line-icons';font-display:swap;src:url('/wp-content/themes/oceanwp/assets/fonts/simple-line-icons/Simple-Line-Icons.woff2?v=2.4.0') format('woff2');}\n" .
			".rpi-reviews-slider,.rpi-dots,.rpi{contain:layout style;}\n" .
			"</style>\n";

		// Inject before </head> or after <head>
		if ( preg_match( '/<\/head>/i', $html, $matches ) ) {
			return str_replace( $matches[0], $style . $matches[0], $html );
		}

		return $html;
	}

	/**
	 * Buffer post-processor: cleans up orphaned stylesheets and render-blocking scripts
	 * that might have been enqueued by third-party plugins after normal dequeue actions.
	 */
	private function clean_orphaned_assets( string $html ): string {
		// 1. Remove LearnDash orphaned CSS & JS on marketing pages.
		if ( $this->classifier->passes_condition( 'not_learndash_page' ) ) {
			$html = preg_replace( '/<link\b(?=[^>]*\brel=["\']stylesheet["\'])(?=[^>]*(?:id=["\'](?:learndash|sfwd|uncannyowl|jquery-dropdown)[^"\']*["\']|href=["\'][^"\']*(?:sfwd-lms|uncanny-learndash-toolkit)[^"\']*["\']))[^>]*>\s*/i', '', $html );
			$html = preg_replace( '/<script\b(?=[^>]*(?:id=["\'](?:learndash|sfwd|uncannyowl)[^"\']*["\']|src=["\'][^"\']*(?:sfwd-lms|uncanny-learndash-toolkit|learndash)[^"\']*["\']))[^>]*>[\s\S]*?<\/script>\s*/i', '', $html );
		}

		// 2. Remove SUMO orphaned CSS & JS on non-shop/non-product pages.
		if ( $this->classifier->passes_condition( 'not_woo_page' ) ) {
			$html = preg_replace( '/<link\b(?=[^>]*\brel=["\']stylesheet["\'])(?=[^>]*(?:id=["\'](?:sumosubs|jquery-ui-style)[^"\']*["\']|href=["\'][^"\']*sumosubscriptions[^"\']*["\']))[^>]*>\s*/i', '', $html );
			$html = preg_replace( '/<script\b(?=[^>]*(?:id=["\']sumosubs[^"\']*["\']|src=["\'][^"\']*sumosubscriptions[^"\']*["\']))[^>]*>[\s\S]*?<\/script>\s*/i', '', $html );
		}

		// 3. Remove UAP orphaned CSS & JS on non-affiliate pages.
		if ( $this->classifier->passes_condition( 'not_affiliate_page' ) ) {
			$html = preg_replace( '/<link\b(?=[^>]*\brel=["\']stylesheet["\'])(?=[^>]*(?:id=["\']uap[^"\']*["\']|href=["\'][^"\']*ultimate-affiliate[^"\']*["\']))[^>]*>\s*/i', '', $html );
			$html = preg_replace( '/<script\b(?=[^>]*(?:id=["\']uap[^"\']*["\']|src=["\'][^"\']*ultimate-affiliate[^"\']*["\']))[^>]*>[\s\S]*?<\/script>\s*/i', '', $html );
		}

		// 4. Remove WooCommerce CSS & JS on non-shop pages.
		if ( $this->classifier->passes_condition( 'not_woo_page' ) ) {
			// OceanWP WooCommerce stylesheets
			$html = preg_replace( '/<link\b(?=[^>]*\brel=["\']stylesheet["\'])(?=[^>]*id=["\']oceanwp-woo(?:commerce|-star-font|-quick-view|-mini-cart)[^"\']*["\'])[^>]*>\s*/i', '', $html );
			// Core WooCommerce stylesheets
			$html = preg_replace( '/<link\b(?=[^>]*\brel=["\']stylesheet["\'])(?=[^>]*id=["\']woocommerce-(?:layout|smallscreen|general)[^"\']*["\'])[^>]*>\s*/i', '', $html );
			// WooCommerce Menu Cart widget stylesheet
			$html = preg_replace( '/<link\b(?=[^>]*\brel=["\']stylesheet["\'])(?=[^>]*id=["\']widget-woocommerce-menu-cart-css["\'])[^>]*>\s*/i', '', $html );
			// Variation templates in head/body
			$html = preg_replace( '/<script\b[^>]*id=["\']tmpl-(?:variation|unavailable-variation)-template["\'][^>]*>[\s\S]*?<\/script>\s*/i', '', $html );
			// WooCommerce scripts (order attribution, sourcebuster, mini-cart, quick view, variation script, etc.)
			$html = preg_replace( '/<script\b(?=[^>]*(?:id=["\'](?:wc-order-attribution|sourcebuster-js|oceanwp-woo|woocommerce|wc-add-to-cart-variation|wc-cart-fragments)[^"\']*["\']|src=["\'][^"\']*(?:woocommerce\/assets\/js|order-attribution|sourcebuster|woo-quick-view|woo-mini-cart|woo-custom-features)[^"\']*["\']))[^>]*>[\s\S]*?<\/script>\s*/i', '', $html );
			$html = preg_replace( '/<script\b(?=[^>]*id=["\']wc-add-to-cart-variation-js-extra["\'])[\s\S]*?<\/script>\s*/i', '', $html );
			$html = preg_replace( '/<script\b(?=[^>]*id=["\']wc-cart-fragments-js-extra["\'])[\s\S]*?<\/script>\s*/i', '', $html );
			// Clean underscore & wp-util when pulled in only for variations
			$html = preg_replace( '/<script\b(?=[^>]*\bid=["\'](?:underscore|wp-util)-js["\'])[^>]*>[\s\S]*?<\/script>\s*/i', '', $html );
			$html = preg_replace( '/<script\b(?=[^>]*id=["\']wp-util-js-extra["\'])[\s\S]*?<\/script>\s*/i', '', $html );
		}

		// 5. Remove Dashicons stylesheet for anonymous public visitors.
		if ( ! is_user_logged_in() ) {
			$html = preg_replace( '/<link\b(?=[^>]*\brel=["\']stylesheet["\'])(?=[^>]*dashicons(?:\.min)?\.css)[^>]*>\s*/i', '', $html );
		}

		// 6. Remove Modern Events Calendar CSS & JS when not an MEC page.
		if ( $this->classifier->passes_condition( 'not_mec_page' ) ) {
			$html = preg_replace( '/<link\b(?=[^>]*\brel=["\']stylesheet["\'])(?=[^>]*(?:id=["\'](?:mec-|featherlight)[^"\']*["\']|href=["\'][^"\']*modern-events-calendar[^"\']*["\']))[^>]*>\s*/i', '', $html );
			$html = preg_replace( '/<script\b(?=[^>]*(?:id=["\'](?:mec-|featherlight)[^"\']*["\']|src=["\'][^"\']*modern-events-calendar[^"\']*["\']))[^>]*>[\s\S]*?<\/script>\s*/i', '', $html );
		}

		return $html;
	}

	/**
	 * Defer render-blocking scripts in <head> and convert below-the-fold non-critical stylesheets
	 * to asynchronous loading to eliminate PageSpeed render-blocking resource penalties.
	 */
	private function optimize_render_blocking_resources( string $html ): string {
		$head_end_pos = stripos( $html, '</head>' );
		if ( $head_end_pos === false ) {
			return $html;
		}

		$head = substr( $html, 0, $head_end_pos );
		$rest = substr( $html, $head_end_pos );

		// 1. Convert below-the-fold stylesheets in <head> to non-render-blocking (media="print" onload="this.media='all'")
		$async_style_patterns = [
			'mec-select2-style-css',
			'mec-font-icons-css',
			'mec-frontend-style-css',
			'mec-tooltip-style-css',
			'mec-tooltip-shadow-style-css',
			'featherlight-css',
			'mec-lity-style-css',
			'mec-general-calendar-style-css',
			'brb-public-main-css-css',
			'quform-css',
			'widget-animated-headline-css',
			'widget-nested-accordion-css',
			'widget-media-carousel-css',
			'widget-carousel-module-base-css',
			'swiper-css',
			'e-swiper-css',
			'widget-social-icons-css',
			'oe-widgets-style-css',
			'simple-line-icons-css',
			'elementor-icons-fa-brands-css',
		];

		foreach ( $async_style_patterns as $style_id ) {
			$pattern = '/(<link\b(?=[^>]*id=[\'"]' . preg_quote( $style_id, '/' ) . '[\'"])(?=[^>]*\brel=[\'"]stylesheet[\'"])[^>]*)(media=[\'"][^\'"]*[\'"])([^>]*>)/i';
			$head = preg_replace_callback( $pattern, function( array $matches ) {
				if ( str_contains( $matches[0], 'media="print"' ) ) {
					return $matches[0];
				}
				return $matches[1] . 'media="print" onload="this.media=\'all\'"' . $matches[3];
			}, $head );
		}

		// 2. Ensure jQuery in <head> has defer so it never blocks initial render
		$head = preg_replace(
			'/(<script\b(?=[^>]*id=[\'"]jquery-(?:core|migrate)-js[\'"])(?![^>]*\bdefer\b)[^>]*)(>)/i',
			'$1 defer="defer"$2',
			$head
		);

		// 3. Inject lightweight jQuery stub queue in <head> to protect any early inline scripts
		if ( ! str_contains( $head, 'id="edt-perf-jquery-stub"' ) ) {
			$stub = "\n<script id=\"edt-perf-jquery-stub\">\n" .
				"window.jQuery=window.$=window.$||function(f){if(typeof f==='function'){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',f)}else{f()}}};\n" .
				"</script>\n";
			$head .= $stub;
		}

		return $head . $rest;
	}

	/**
	 * Outputs a lightweight LCP diagnostic script in the footer.
	 * Only enabled when 'enable_html_comment_log' is true.
	 * NEVER enabled in production — diagnostic tool only.
	 *
	 * This uses PerformanceObserver to log the actual LCP element to console.
	 * It does NOT block rendering, does NOT affect LCP scores.
	 */
	public function output_lcp_diagnostic_js(): void {
		// Double-check: only output on staging/debug.
		if ( ! ( $this->options['enable_html_comment_log'] ?? false ) ) {
			return;
		}

		?>
		<script id="edt-perf-lcp-diagnostic" defer>
		(function() {
			if (!('PerformanceObserver' in window)) return;
			try {
				var po = new PerformanceObserver(function(list) {
					var entries = list.getEntries();
					var last = entries[entries.length - 1];
					if (last && last.element) {
						console.group('[EDT Perf] LCP Candidate');
						console.log('Element:', last.element);
						console.log('Tag:', last.element.tagName);
						console.log('Src:', last.element.src || last.element.currentSrc || '(no src)');
						console.log('Classes:', last.element.className);
						console.log('LCP time:', Math.round(last.startTime) + 'ms');
						console.log('LCP sub-parts:', {
							ttfb: Math.round(last.resourceLoadDelay || 0) + 'ms',
							load_delay: Math.round(last.resourceLoadDuration || 0) + 'ms',
							render_delay: Math.round(last.elementRenderDelay || 0) + 'ms',
						});
						console.log('Is inside .LCP:', !!last.element.closest('.LCP'));
						console.groupEnd();
					}
				});
				po.observe({type: 'largest-contentful-paint', buffered: true});
			} catch(e) {}
		})();
		</script>
		<?php
	}
}

