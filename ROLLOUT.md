# ROLLOUT.md — Safe Deployment, Verification, and Rollback Guide

## 1. Staging Deployment Checklist (Step-by-Step)

### Step 1: Upload and Activate
1. Navigate to **WordPress Admin > Plugins > Add New > Upload Plugin**.
2. Select the packaged `edt-performnce-optimizer.zip` and click **Install Now**.
3. Click **Activate Plugin**.
4. Verify that no PHP fatals or warnings appear in `debug.log`.

### Step 2: Confirm Optimizer Boundary Scan
1. In the admin sidebar, open **EDT Perf > Dashboard**.
2. Review the **Active Optimizer Inventory** table:
   - Verify AirLift is recognized as **Active** (owns critical CSS, JS delay, image formats).
   - Verify AC Smart Performance Guard is recognized as **Active** (owns `.LCP` visibility).
   - Verify Kinsta Cache is detected.
3. If necessary, click **Rescan Now** to refresh the detection transient.

### Step 3: Verify Asset Rules Configuration
1. Open **EDT Perf > Asset Rules**.
2. Verify that the 14 low-risk style rules are **Enabled** (LearnDash, SUMO Subscriptions, UAP Affiliate).
3. Confirm that medium-risk rules (`oceanwp-woocommerce-css`, `jquery-ui-style-css`) remain **Disabled** until individual page requirements are confirmed.
4. Save rules if any changes are made.

### Step 4: Clear Server & CDN Caches (Crucial)
1. **Clear Kinsta Cache**: Via WordPress admin bar (**Kinsta Cache > Clear All Caches**) or the MyKinsta dashboard.
2. **Clear AirLift Cache**: In AirLift settings, trigger a cache purge if available.
3. **Verify Response Headers**:
   ```bash
   curl -I https://stg-wwweazydogtrainingcom-staging.kinsta.cloud/dog-training-classes-sydney/puppy-school-in-mascot/
   ```
   Confirm `x-kinsta-cache: HIT` on subsequent requests.

### Step 5: Functional & Visual Regression Testing
Test the following user journeys in an incognito mobile browser window (375px width):
1. **Landing Page Hero**:
   - Inspect the Mascot, Zetland, and Newtown landing pages.
   - Verify the puppy school hero image appears crisp and immediately above the fold without layout shift.
   - Open Chrome DevTools Network panel: verify the hero `.avif` file has `Priority: High` and the site logo `Black-on-transparent-logo.avif` has `Priority: Low/Auto`.
2. **Modern Events Calendar (MEC) Booking**:
   - Navigate to `/dog-training-classes-sydney/puppy-school-st-peters/`.
   - Verify the event datepicker, calendar view, and "Book Now" registration modal function normally.
3. **WooCommerce Flow**:
   - Add a product to cart and visit `/cart/` and `/checkout/`.
   - Verify that all WooCommerce styles, payment methods (Stripe, BPOINT), and order attribution scripts load untouched.
4. **LearnDash Course Screen (Logged In)**:
   - Log in as an enrolled student and visit a course lesson.
   - Verify course navigation, quiz progression, and student admin bar styles are active.

---

## 2. AirLift & AC Smart Performance Guard Handoff Protocol

To avoid duplication or race conditions between plugins:
1. **AirLift Handoff**:
   - **Do not enable AirLift's CSS Unused Asset Removal** on top of EDT Performance Optimizer. EDT handles handle-level dequeuing before AirLift processes styles, ensuring clean inputs to AirLift.
   - Keep AirLift's **Critical CSS** and **Image Optimization** turned ON.
2. **AC Smart Performance Guard Handoff**:
   - Keep AC Smart Performance Guard active. It provides `.ac-lcp-priority` and `.LCP` instant-render CSS. EDT coordinates with it and does not touch its injected styles.

---

## 3. Emergency Rollback Procedures

If any visual or functional issue is reported post-deployment, choose one of the three instant rollback methods:

### Method A: Admin Dashboard Toggle (Fastest)
1. Go to **EDT Perf > Dashboard**.
2. Under **Emergency Controls**, click the red **🚨 Enable Emergency Rollback** button.
3. All dequeuing and priority transformations are disabled instantly. The site reverts to stock WordPress output without deactivating the plugin.

### Method B: WP-CLI Command (Zero-Downtime Terminal Access)
From SSH terminal on Kinsta:
```bash
wp edt-perf rollback on
```
To resume normal optimization:
```bash
wp edt-perf rollback off
```

### Method C: wp-config.php Kill Switch (Emergency Server-Level Bypass)
If the WordPress admin is inaccessible or in case of a server crash, add this single line to `wp-config.php` above `/* That's all, stop editing! */`:
```php
define( 'EDT_PERF_DISABLE', true );
```
This halts all EDT plugin execution before any WordPress hooks or actions are registered.

---

## 4. Production Release Protocol
1. Schedule release during low-traffic window (e.g., 9:00 PM - 11:00 PM AEST).
2. Push staging changes to production or upload `edt-performnce-optimizer.zip` to live site.
3. Clear Kinsta production edge cache.
4. Run Lighthouse mobile audit on primary Google Ads landing pages to confirm mobile LCP <= 2.5s.
5. Monitor orders, form submissions, and Google Analytics conversion events for 24 hours.

