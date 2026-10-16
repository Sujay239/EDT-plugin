# COMPATIBILITY_MATRIX.md — EDT Performance Optimizer v2.0.0

## Legend
- ✅ Safe — tested, no conflict
- ⚠️ Careful — conditional, needs verification
- 🚫 Protected — never dequeued by this plugin
- 🔁 Delegated — owned by another optimizer

---

## CSS Asset Compatibility

| Handle | Source Plugin | Our Action | Condition | Protection Reason | Safe Pages |
|---|---|---|---|---|---|
| `learndash_quiz_front_css-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Quiz CSS not needed on marketing pages | Homepage, puppy school, landing pages |
| `learndash_style-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Core LD CSS not needed on marketing | Same |
| `learndash-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Same | Same |
| `sfwd_front_css-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Same | Same |
| `jquery-dropdown-css-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Same | Same |
| `learndash_pager_css-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Same | Same |
| `learndash_template_style_css-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Same | Same |
| `learndash_lesson_video-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Same | Same |
| `learndash-admin-bar-css` | LearnDash 5.2.2 | dequeue | not_learndash_page_and_not_logged_in | Admin bar needs login | Anon visitors only |
| `learndash-course-grid-skin-grid-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Grid skins not on marketing | Same |
| `learndash-course-grid-pagination-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Same | Same |
| `learndash-course-grid-filter-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Same | Same |
| `learndash-course-grid-card-grid-1-css` | LearnDash 5.2.2 | dequeue | not_learndash_page | Same | Same |
| `sumosubs-blocks-integration-css` | SUMO Subscriptions 17.7.0 | dequeue | not_woo_page | No subscription blocks on marketing | Homepage, landing pages |
| `sumosubs-frontend-css` | SUMO Subscriptions 17.7.0 | dequeue | not_woo_page | Same | Same |
| `uap_public_style-css` | Ultimate Affiliate Pro | dequeue | not_affiliate_page | No affiliate UI on marketing pages | Same |
| `uap_templates-css` | Ultimate Affiliate Pro | dequeue | not_affiliate_page | Same | Same |
| `uncannyowl-learndash-toolkit-free-css` | Uncanny Toolkit | dequeue | not_learndash_page | Same as LearnDash | Same |
| `oceanwp-woocommerce-css` | OceanWP 4.2.7 | dequeue (disabled) | not_woo_page | ⚠️ Disabled until verified no Woo widget in header | — |
| `oceanwp-woo-quick-view-css` | OceanWP 4.2.7 | dequeue (disabled) | not_woo_page | ⚠️ Disabled by default | — |

## Protected CSS (Never Dequeued)

| Handle | Reason |
|---|---|
| `elementor-frontend-css` | Core Elementor CSS — required on all Elementor pages |
| `elementor-pro-frontend` | Elementor Pro — required |
| `oceanwp-style-css` | OceanWP base theme CSS — required |
| `child-style-css` | Child theme CSS — required |
| `quform-css` | Form CSS — forms present on landing pages |
| `mec-frontend-style-css` | MEC calendar CSS — required on booking pages |
| `mec-select2-style-css` | MEC select2 — required on event pages |
| `brb-public-main-css-css` | Business Reviews Bundle — used on marketing pages |
| `mc4wp-form-basic-css` | Mailchimp form — may appear on marketing pages |

## Script Compatibility

| Handle | Source | Our Action | Condition |
|---|---|---|---|
| `learndash-js` | LearnDash | dequeue | not_learndash_page |
| `uncannyowl-learndash-toolkit-free-js` | Uncanny Toolkit | dequeue | not_learndash_page |

## AirLift Ownership (Never Competed With)

| Feature | AirLift Handles | Our Plugin |
|---|---|---|
| Hero image preload | `<link class="bv-preload">` | No action |
| `fetchpriority="high"` on hero | Set by AirLift | No action |
| `loading="eager"` on hero | Set by AirLift | No action |
| Critical CSS inline | `<style class="bv-critical-css">` | No action |
| JS deferral | `type="bv_inline_delayed_js"` | No competing delay |
| CSS deferral | `bv-dl-styles-list` JSON loader | No competing deferral |
| WebP/AVIF conversion | `.bv.webp` suffix | No recompression |
| Font delivery | `aircdn.net` CDN | No competing preloads |

## AC Smart Performance Guard Ownership

| Feature | ACSPG Handles | Our Plugin |
|---|---|---|
| `.LCP` visibility | `acsp-lcp-instant-render` style block | No action |
| `[data-ac-lcp="1"]` visibility | Same | No action |
| Top-bar animation | `acsp-composited-anim` | No action |

## Kinsta

| Feature | Kinsta Handles | Our Plugin |
|---|---|---|
| Full-page cache | Kinsta MU plugin, nginx | No PHP cache implemented |
| CDN | Kinsta CDN | No cache header modification |

## WooCommerce Journey Protection

| Page | Our Plugin Behavior |
|---|---|
| /cart/ | Excluded — no dequeues |
| /checkout/ | Excluded — no dequeues |
| /my-account/ | Excluded — no dequeues |
| /order-received/ | Excluded — no dequeues |
| /order-pay/ | Excluded — no dequeues |
| Product pages | Excluded via is_woo_product() |

## LearnDash Journey Protection

| Page Type | Our Plugin Behavior |
|---|---|
| sfwd-courses | Excluded — no dequeues |
| sfwd-lessons | Excluded — no dequeues |
| sfwd-topic | Excluded — no dequeues |
| sfwd-quiz | Excluded — no dequeues |
| sfwd-certificates | Excluded — no dequeues |
| /courses/ | Excluded |
| Logged-in student | admin-bar CSS kept |

## Elementor Editor Protection

All rules are bypassed when:
- `?elementor-preview=` query param is present
- `Elementor\Plugin::$instance->preview->is_preview_mode()` returns true
- WordPress Customizer is active

