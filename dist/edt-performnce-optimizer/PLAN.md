# PLAN.md — Eazy Dog Training Performance Plugin Architecture & Execution Plan

## 1. Primary Objectives & Acceptance Criteria
- **Target**: Mobile LCP strictly below 2.5s on priority marketing and Google Ads landing pages (`/`, `/dog-training-classes-sydney/puppy-school-in-mascot/`, `/dog-training-classes-sydney/puppy-school-st-peters/`, `/dog-training-classes-sydney/puppy-school-zetland/`, etc.).
- **Fidelity**: 100% visual and functional regression protection for Elementor, OceanWP, Modern Events Calendar (MEC) booking widgets, WooCommerce checkout, LearnDash courses, Quform, tracking, and consent.
- **Safety**: Safe defaults, emergency rollback switch, per-rule toggles, and zero duplication with AirLift, AC Smart Performance Guard, and Kinsta.

---

## 2. Architectural Blueprint & Class Diagram

```
EDT Performance Optimizer (v2.0.0)
│
├── edt-performnce-optimizer.php (Bootstrap, autoloader, emergency switch EDT_PERF_DISABLE)
│
├── includes/
│   ├── class-edt-performnce-optimizer.php (Core lifecycle orchestrator)
│   ├── class-edt-performnce-optimizer-activator.php (Default options & rules seeding)
│   ├── class-edt-performnce-optimizer-deactivator.php (Transient cleanup)
│   ├── class-edt-performnce-optimizer-i18n.php (Internationalization)
│   ├── class-edt-performnce-optimizer-loader.php (Action & filter registry)
│   ├── class-wp-cli.php (WP-CLI commands for CLI operations)
│   │
│   └── modules/
│       ├── class-page-classifier.php (Context & route resolver, safety validator)
│       ├── class-compatibility-scanner.php (Multi-optimizer inventory & conflict detector)
│       ├── class-conditional-asset-manager.php (Late dequeue execution engine)
│       ├── class-lcp-priority-manager.php (Logo fetchpriority disambiguation & LCP buffer)
│       └── class-diagnostics.php (Health checks, audit logging, export/import)
│
├── admin/
│   ├── class-edt-performnce-optimizer-admin.php (Settings, nonces, permissions, routes)
│   ├── css/edt-performnce-optimizer-admin.css (Status cards, tables, badge styles)
│   ├── js/edt-performnce-optimizer-admin.js (UI confirmations, batch toggles)
│   └── partials/
│       ├── edt-performnce-optimizer-admin-display.php (Dashboard overview & inventory)
│       ├── edt-perf-asset-rules.php (Per-handle rule management table)
│       ├── edt-perf-settings.php (Module toggles, exclusions, config export)
│       └── edt-perf-diagnostics.php (Diagnostic event log & system info)
│
├── public/
│   ├── class-edt-performnce-optimizer-public.php (Frontend lifecycle coordinator)
│   ├── css/edt-performnce-optimizer-public.css (Zero added bloat)
│   └── js/edt-performnce-optimizer-public.js (Zero added bloat)
│
├── tests/
│   ├── test-validator.js (Node.js automated structural & syntax validator)
│   ├── audit-runner.js (Playwright/Chrome Lighthouse performance runner)
│   └── fixtures/ (Test assertions)
│
└── ASSET_RULES.json (Machine-readable audited rules manifest)
```

---

## 3. Ordered Implementation Stages

### Stage 1: Baseline Audit & Evidence Mapping (Completed)
- Forensic examination of page source files (`home.html`, `mascot.html`, `zetland.html`, etc.).
- Identification of active optimizers: AirLift 6.77, AC Smart Performance Guard 6.0.0, Kinsta Page Cache.
- Extracted exact CSS handles and scripts loaded across landing pages.
- Compiled `AUDIT.md` and initial `ASSET_RULES.json`.

### Stage 2: Core Engine & Context Classification (Completed)
- Implement `EDT_Perf_Modules_Page_Classifier`:
  - Identify route: front page, singular post, shop, product, cart, checkout, account, order.
  - Detect LearnDash post types and shortcodes.
  - Detect Modern Events Calendar (MEC) events and booking elements.
  - Detect Ultimate Affiliate Pro and SUMO Subscriptions contexts.
  - Enforce `is_safe_to_optimize()` hard-exclusions for sensitive user journeys and admin/edit states.

### Stage 3: Compatibility Scanner & Overlap Prevention (Completed)
- Implement `EDT_Perf_Modules_Compatibility_Scanner`:
  - Detect presence and active features of AirLift, ACSPG, Kinsta, Elementor, and WordPress native.
  - Establish strict boundaries: AirLift owns critical CSS, JS delay, and image format conversion. Kinsta owns page caching. ACSPG owns `.LCP` visibility.
  - Generate admin conflict warnings if any rule attempts to override or duplicate external functionality.

### Stage 4: Asset Manager & Conditional Dequeuing (Completed)
- Implement `EDT_Perf_Modules_Conditional_Asset_Manager`:
  - Hook into `wp_enqueue_scripts` at priority `999` (late enqueue).
  - Match registered styles and scripts against enabled rules in `ASSET_RULES.json`.
  - Check evaluation condition via `PageClassifier::passes_condition()`.
  - Dequeue confirmed unnecessary assets (e.g., 12 LearnDash CSS files on marketing pages).
  - Record execution audit log with action (`DEQUEUED_STYLE`, `DEQUEUED_SCRIPT`, `KEPT`, `SKIP`), handle, reason, and URL.

### Stage 5: LCP Priority Resolution & Network Stream Protection (Completed)
- Implement `EDT_Perf_Modules_Lcp_Priority_Manager`:
  - Target HTML output buffer on public anonymous pages.
  - Demote header logo `<img>` (`wp-image-61476`) from `fetchpriority="high"` to `fetchpriority="auto"`.
  - Preserve hero image priority (`fetchpriority="high"`, `loading="eager"`, `<link rel="preload">`).
  - Provide lightweight, non-blocking `PerformanceObserver` diagnostic snippet when enabled on staging.

### Stage 6: Admin Dashboard & Emergency Controls (Completed)
- Full admin UI with nonces, capability checks (`manage_options`), and data sanitization:
  - Dashboard: Status cards, active inventory, LCP candidates table.
  - Asset Rules: Granular toggle for each handle with evidence and risk ratings.
  - Settings: Master switch, emergency rollback, exclusions, export/import.
  - Diagnostics Log: Searchable history of dequeued/kept assets with environment details.

### Stage 7: WP-CLI Integration & Automation Companion Tools (Current)
- Add `EDT_Performance_Optimizer_CLI` commands:
  - `wp edt-perf status`
  - `wp edt-perf scan`
  - `wp edt-perf rules [list|enable|disable]`
  - `wp edt-perf rollback [on|off]`
  - `wp edt-perf clear-log`
- Implement automated test suite in `tests/`:
  - Syntax and integrity validation script.
  - Headless browser test runner for Lighthouse metrics extraction.

### Stage 8: Packaging & Documentation (Current)
- Generate verified installable zip package.
- Document `RESULTS.md`, `ROLLOUT.md`, and `LIMITATIONS.md`.

---

## 4. Risk Matrix & Mitigations

| Risk | Impact | Likelihood | Mitigation Strategy |
|---|---|---|---|
| Broken styling on LearnDash pages | High | Low | `PageClassifier` checks post type (`sfwd-courses`, etc.) and shortcodes. Never dequeues on LD pages. |
| Broken MEC booking calendar | Critical | Low | MEC styles (`mec-frontend-style-css`, `mec-select2-style-css`) are strictly protected. |
| Broken WooCommerce checkout | Critical | Extremely Low | Hard-exclusion for `/cart/`, `/checkout/`, `/my-account/`, and payment endpoints in `is_safe_to_optimize()`. |
| Conflict with AirLift image CDN | Medium | Low | We do not rewrite image URLs or add competing preloads; we allow AirLift's CDN pipeline to operate untouched. |
| Double optimization slowdown | Medium | Low | `CompatibilityScanner` warns of overlaps; our plugin does not perform runtime CSS parsing or full-page caching. |
| Unexpected visual glitch | Medium | Low | Instant rollback via UI toggle, WP-CLI command, or `define('EDT_PERF_DISABLE', true);` in `wp-config.php`. |

