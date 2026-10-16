# AUDIT.md — Eazy Dog Training LCP Performance Analysis & Baseline

## 1. Environment & Site Baseline (From Page Source & Configuration Evidence)
- **Production Domain**: `https://www.eazydogtraining.com/`
- **Staging Test Domain**: `https://stg-wwweazydogtrainingcom-staging.kinsta.cloud/`
- **Hosting**: Kinsta Hosting (Nginx 1.31.3 + MariaDB 11.4.12, PHP 8.2.29 FPM, `WP_CACHE=true`, `advanced-cache.php` active, Kinsta MU Plugin 3.7.0, OPcache enabled).
- **Core Versions**: WordPress 7.1.3, WooCommerce 11.2.1, Elementor 4.3.5 / Elementor Pro 4.3.2.
- **Theme**: OceanWP 4.2.7 parent theme with OceanWP Child 1.1.
- **Existing Overlapping Optimizers**:
  - **AirLift 6.77**: AirCDN (`d-0bb27392-azpvkm.ps1.aircdn.net`), critical CSS generator (`bv-critical-css`), JavaScript delay (`type="bv_inline_delayed_js"`), deferred CSS loader (`bv-dl-styles-list`), WebP/AVIF generation (`.bv.webp`), hero image preloader (`<link class="bv-preload">`), balanced fonts (`bv-balanced-font-css`), and image lazyloader runtime (`bv-image-optimization-runtime`).
  - **AC Smart Performance Guard 6.0.0**: Inlines instant render overrides for `.ac-lcp, .ac-lcp-priority, [data-ac-lcp="1"], .LCP, .LCP img, .lcp, .lcp img` (`content-visibility: visible !important; contain: none !important; opacity: 1 !important; visibility: visible !important;`) and top-bar animation (`acsp-composited-anim`).
  - **Kinsta Edge / Server Cache**: Owns full-page caching and edge static caching.

---

## 2. Active Optimizer Ownership Matrix

| Optimization Feature | Active Owner | Implementation Detail & Marker | EDT Plugin Stance |
|---|---|---|---|
| **Critical CSS Inlining** | AirLift | Inlines `<style class="bv-critical-css">` in `<head>` | **Do NOT duplicate**. Preserve AirLift critical CSS blocks. |
| **CSS Deferral (Lazy Styles)** | AirLift | Stores array in `var linkStyleAttrs = [...]` inside `<script id="bv-dl-styles-list">` | **Filter at registration**. Dequeue unneeded styles in WordPress before AirLift bundles them into `bv-dl-styles-list`. |
| **JavaScript Delay** | AirLift | Alters tag to `<script type="bv_inline_delayed_js">` with `bv_unique_id` | **Do NOT compete**. Only dequeue known unnecessary scripts conditionally via `wp_dequeue_script()`. |
| **Hero Image Preload** | AirLift | Inlines `<link rel="preload" as="image" class="bv-preload">` with `imagesrcset` and `media` | **Do NOT duplicate**. AirLift already preloads responsive hero candidates. |
| **Hero Fetchpriority / Eager** | AirLift / WP Core | Sets `fetchpriority="high"` and `loading="eager"` on hero `<img>` | **Correct priority conflict**. OceanWP site logo also has `fetchpriority="high"`, diluting hero priority. |
| **Image Compression / WebP / AVIF** | AirLift / AirCDN | Serves AVIF/WebP and rewrites sources via AirCDN CDN | **Do NOT alter**. Let AirLift handle format conversion. |
| **LCP Visibility Guarantee** | AC Smart Performance Guard | Inlines `#acsp-lcp-instant-render` with `content-visibility: visible !important; contain: none !important;` | **Preserve & Coordinate**. Avoid overriding `.LCP` rules. |
| **Full-Page Caching** | Kinsta | `advanced-cache.php`, Nginx microcaching, CDN | **Strictly respect**. Never add duplicate PHP caching or send conflicting cache headers. |

---

## 3. Forensic Analysis Across Priority URLs

### A. Homepage (`/`, `home.html`)
- **Container Structure**:
  - Has parent container with class `.LCP`.
  - Identified 2 hero candidates:
    1. Primary Hero: `sdsdfsf.avif` (600×621), alt="Home Page LCP", wrapped in `.LCP` container, responsive srcset with 290w variant for mobile. Preloaded by AirLift.
    2. Secondary Hero: `1.avif` (249×258), alt="Main LCP", `fetchpriority="high"` and `loading="eager"`.
- **Preload Profile**:
  - `https://stg-wwweazydogtrainingcom-staging.kinsta.cloud/wp-content/uploads/2026/09/sdsdfsf.avif` preloaded for `min-width: 481px`.
  - `https://stg-wwweazydogtrainingcom-staging.kinsta.cloud/wp-content/uploads/2026/10/1.avif` preloaded for `max-width: 480px`.
- **Identified Bottlenecks**:
  1. Competing `fetchpriority="high"` on header logo (`wp-image-61476`), causing mobile network contention during critical initial discovery.
  2. Heavy payload of deferred CSS (over 60 stylesheets) in `bv-dl-styles-list`, including 12 LearnDash stylesheets, SUMO Subscriptions stylesheets, and Ultimate Affiliate stylesheets that are unused on the homepage.
  3. Excessive synchronous JS runtime (`bv-image-optimization-runtime`) in `<head>`.

### B. Priority Paid Landing Page: Mascot Puppy School (`/dog-training-classes-sydney/puppy-school-in-mascot/`, `mascot.html`)
- **LCP Candidates**:
  - **Desktop Viewport (>480px)**: `puppy-school-mascot-768x762-2_11zon-1.avif` (768×762) with responsive breakpoints (768w, 300w, 150w, 600w, 100w). Preloaded with `media="(min-width: 481px)"`.
  - **Mobile Viewport (<=480px)**: `puppy-school-mascot-mobile-1.avif` (300×298) with responsive breakpoints (300w, 150w, 100w). Preloaded with `media="(max-width: 480px)"`.
- **Preload Assessment**:
  - AirLift accurately injects separate `<link rel="preload" as="image" class="bv-preload">` tags with explicit media queries for mobile and desktop.
- **Identified Bottlenecks**:
  - **Logo priority collision**: Line 917 of `mascot.html` demonstrates `fetchpriority="high"` on the navigation logo image (`Black-on-transparent-logo.avif`).
  - **LearnDash Bloat**: 12 stylesheets registered and queued (`learndash_quiz_front_css-css`, `learndash_style-css`, `learndash-css`, `sfwd_front_css-css`, `jquery-dropdown-css-css`, `learndash_pager_css-css`, `learndash_template_style_css-css`, `learndash_lesson_video-css`, `learndash-admin-bar-css`, `learndash-course-grid-skin-grid-css`, `learndash-course-grid-pagination-css`, `learndash-course-grid-filter-css`, `learndash-course-grid-card-grid-1-css`) + `learndash-js`. This page has no courses, quizzes, or student interfaces.
  - **Subscription & Affiliate Bloat**: `sumosubs-blocks-integration-css`, `sumosubs-frontend-css`, `uap_public_style-css`, `uap_templates-css` are enqueued even though this is a puppy school informational/booking page.
  - **MEC Event Calendar Integration**: Page features Modern Events Calendar booking elements. MEC styles (`mec-frontend-style-css`, `mec-select2-style-css`) must be preserved to prevent layout shifts (CLS) and booking breakage.

### C. Suburb Landing Pages (Zetland, Marrickville, Newtown, Camperdown, Alexandria)
- Identical structural profile to Mascot:
  - Responsive hero images with `.LCP` parent selector.
  - Same pattern of 15+ completely unused plugin stylesheets queued into AirLift's deferred list.
  - Every unnecessary stylesheet in `bv-dl-styles-list` adds DOM parsing overhead, memory allocation, and late layout recalculation.

---

## 4. LCP Sub-Part Phase Decomposition

| LCP Phase | Typical Observation | Root Cause | Remediation in EDT Plugin |
|---|---|---|---|
| **TTFB (Time to First Byte)** | ~180ms - 450ms (Kinsta edge cache hit) / >1200ms (uncached) | Kinsta caching status | Ensure no plugin headers break Kinsta cache key; protect anonymous marketing pages. |
| **Resource Load Delay** | 350ms - 900ms | Competing priority on header logo + connection contention | Demote logo priority from `high` to `auto`, freeing up early HTTP/2 streams for the hero image. |
| **Resource Load Duration** | 250ms - 600ms | Transfer of AVIF hero image (300px mobile variant ~18KB) | AirLift already generates optimized AVIF; keep images preloaded with matched media queries. |
| **Element Render Delay** | 600ms - 1400ms | Large critical CSS block, forced reflows, long tasks from script execution, and deferred stylesheet burst | Dequeue 15+ unused stylesheets (eliminating 80KB+ CSS parsing and late reflows). |

---

## 5. Scope of Safe Optimizations

1. **Clean Handle Dequeuing**:
   - Prevent unused LearnDash, SUMO Subscriptions, and Ultimate Affiliate assets from being enqueued on marketing pages.
   - Dequeuing before AirLift processes styles reduces `bv-dl-styles-list` by ~25%, reducing browser main-thread parsing overhead.
2. **Priority Disambiguation**:
   - Remove `fetchpriority="high"` from `#site-header` logo (`wp-image-61476`), allowing Chrome and WebKit network allocators to prioritize the actual hero candidate without competition.
3. **Strict Conditional Protection**:
   - Zero changes on `/cart/`, `/checkout/`, `/my-account/`, LearnDash lessons/quizzes, and MEC active booking flows.
   - Immediate rollback capability via UI or `EDT_PERF_DISABLE` constant.

