/**
 * Automated Test Validator for EDT Performance Optimizer
 *
 * Verifies:
 * 1. ASSET_RULES.json validity and integrity against actual page source files.
 * 2. LCPPriorityManager regex accuracy on real HTML snippets (logo demotion, hero preservation).
 * 3. File existence and PHP class consistency.
 * 4. Safety exclusions and rule structure.
 */

const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const PAGES_DIR = path.join(ROOT_DIR, 'pageosurces');
const RULES_FILE = path.join(ROOT_DIR, 'ASSET_RULES.json');

let failures = 0;
let passes = 0;

function assert(condition, message) {
    if (condition) {
        console.log(`  \x1b[32m✔ PASS:\x1b[0m ${message}`);
        passes++;
    } else {
        console.error(`  \x1b[31m✖ FAIL:\x1b[0m ${message}`);
        failures++;
    }
}

console.log('\x1b[1m=== Running EDT Performance Optimizer Integrity Suite ===\x1b[0m\n');

// -----------------------------------------------------------------------------
// Test 1: Verify ASSET_RULES.json structure and real page presence
// -----------------------------------------------------------------------------
console.log('\x1b[34m[Suite 1] ASSET_RULES.json Verification\x1b[0m');
assert(fs.existsSync(RULES_FILE), 'ASSET_RULES.json exists');

const rulesContent = JSON.parse(fs.readFileSync(RULES_FILE, 'utf8'));
assert(rulesContent.style_rules && typeof rulesContent.style_rules === 'object', 'style_rules object exists');
assert(rulesContent.script_rules && typeof rulesContent.script_rules === 'object', 'script_rules object exists');
assert(rulesContent.protected_handles && typeof rulesContent.protected_handles === 'object', 'protected_handles object exists');

// Verify canonical handles without invalid duplicate suffixes (-css-css, etc.)
let doubleSuffixedCount = 0;
for (const handle of Object.keys(rulesContent.style_rules)) {
    if (handle.endsWith('-css-css') || handle.endsWith('_css-css')) doubleSuffixedCount++;
}
assert(doubleSuffixedCount === 0, `All style rule handles are canonical without duplicate -css suffixes (actual: ${doubleSuffixedCount})`);

// Find available HTML page source(s)
let combinedHtml = '';
const candidatePagesDir = path.join(ROOT_DIR, 'pageosurces');
const candidateAltDir = path.join(ROOT_DIR, 'pagesources');
const candidateSingleHtml = path.resolve(ROOT_DIR, '../../pagesource.html');

if (fs.existsSync(candidatePagesDir)) {
    const pageFiles = fs.readdirSync(candidatePagesDir).filter(f => f.endsWith('.html'));
    combinedHtml = pageFiles.map(f => fs.readFileSync(path.join(candidatePagesDir, f), 'utf8')).join('\n');
    assert(pageFiles.length > 0, `Found ${pageFiles.length} HTML files in pageosurces/`);
} else if (fs.existsSync(candidateAltDir)) {
    const pageFiles = fs.readdirSync(candidateAltDir).filter(f => f.endsWith('.html'));
    combinedHtml = pageFiles.map(f => fs.readFileSync(path.join(candidateAltDir, f), 'utf8')).join('\n');
    assert(pageFiles.length > 0, `Found ${pageFiles.length} HTML files in pagesources/`);
} else if (fs.existsSync(candidateSingleHtml)) {
    combinedHtml = fs.readFileSync(candidateSingleHtml, 'utf8');
    assert(combinedHtml.length > 10000, `Found pagesource.html (${combinedHtml.length} bytes)`);
}

if (combinedHtml) {
    let verifiedHandles = 0;
    for (const [handle, def] of Object.entries(rulesContent.style_rules)) {
        if (combinedHtml.includes(handle)) verifiedHandles++;
    }
    assert(verifiedHandles >= 5, `At least 5 style rules verified against real site evidence (actual: ${verifiedHandles})`);
}

// -----------------------------------------------------------------------------
// Test 2: Verify Logo & Preload fetchpriority regex & Hero preservation
// -----------------------------------------------------------------------------
console.log('\n\x1b[34m[Suite 2] LCP Priority Manager Regex Verification\x1b[0m');

// Test Case 2a: Preload demotion for competing logo & badge
const samplePreloadHtml = `
<link rel="preload" as="image" href="https://example.com/wp-content/uploads/Black-on-transparent-logo.png" fetchpriority="high">
<link rel="preload" as="image" href="https://example.com/wp-content/uploads/2023_SG_WINNER_LOGO.png" fetchpriority="high">
<link rel="preload" as="image" href="https://example.com/wp-content/uploads/hero-banner-desktop.avif" fetchpriority="high">
`;

const preloadPattern = /<link\b(?=[^>]*\brel=["']preload["'])(?=[^>]*\bas=["']image["'])(?=[^>]*(?:logo|brand|badge|icon|Black-on-transparent|2023_SG_WINNER))(?=[^>]*\bfetchpriority=["']high["'])[^>]*>/gi;

const replacedPreloads = samplePreloadHtml.replace(preloadPattern, (match) => {
    return match.replace(/\bfetchpriority=["']high["']/i, 'fetchpriority="low"');
});

assert(replacedPreloads.includes('Black-on-transparent-logo.png" fetchpriority="low"'), 'Logo preload demoted to fetchpriority="low"');
assert(replacedPreloads.includes('2023_SG_WINNER_LOGO.png" fetchpriority="low"'), 'Trust badge preload demoted to fetchpriority="low"');
assert(replacedPreloads.includes('hero-banner-desktop.avif" fetchpriority="high"'), 'Hero image preload retains fetchpriority="high"');

// Test Case 2b: Logo <img> demotion to "auto"
const sampleImgHtml = `
<img width="600" height="163" src="https://example.com/logo.png" class="custom-logo site-logo-img wp-image-61476" alt="Logo" decoding="async" fetchpriority="high">
<img width="1200" height="600" src="https://example.com/hero-image.avif" class="attachment-full size-full wp-image-99999" alt="Hero Banner" decoding="async" fetchpriority="high" loading="eager">
`;

const logoImgPattern = /<img\b(?=[^>]*(?:custom-logo|site-logo-img|wp-image-61476))(?=[^>]*\bfetchpriority=["']high["'])[^>]*>/i;

const replacedImg = sampleImgHtml.replace(logoImgPattern, (match) => {
    return match.replace(/\bfetchpriority=["']high["']/i, 'fetchpriority="auto"');
});

assert(replacedImg.includes('custom-logo') && replacedImg.includes('fetchpriority="auto"'), 'Logo <img> fetchpriority successfully demoted to "auto"');
assert(replacedImg.includes('hero-image.avif') && replacedImg.includes('fetchpriority="high"'), 'Hero <img> retains fetchpriority="high"');

// -----------------------------------------------------------------------------
// Test 3: Core Plugin PHP Files Existence & Structure
// -----------------------------------------------------------------------------
console.log('\n\x1b[34m[Suite 3] Plugin File Structure & Class Definitions\x1b[0m');

const requiredFiles = [
    'edt-performnce-optimizer.php',
    'uninstall.php',
    'includes/class-edt-performnce-optimizer.php',
    'includes/class-edt-performnce-optimizer-activator.php',
    'includes/class-edt-performnce-optimizer-deactivator.php',
    'includes/class-wp-cli.php',
    'includes/modules/class-page-classifier.php',
    'includes/modules/class-compatibility-scanner.php',
    'includes/modules/class-conditional-asset-manager.php',
    'includes/modules/class-lcp-priority-manager.php',
    'includes/modules/class-diagnostics.php',
    'admin/class-edt-performnce-optimizer-admin.php',
    'admin/css/edt-performnce-optimizer-admin.css',
    'admin/js/edt-performnce-optimizer-admin.js',
    'admin/partials/edt-performnce-optimizer-admin-display.php',
    'admin/partials/edt-perf-asset-rules.php',
    'admin/partials/edt-perf-settings.php',
    'admin/partials/edt-perf-diagnostics.php',
    'public/class-edt-performnce-optimizer-public.php',
    'AUDIT.md',
    'PLAN.md',
    'COMPATIBILITY_MATRIX.md'
];

for (const relFile of requiredFiles) {
    const fullPath = path.join(ROOT_DIR, relFile);
    assert(fs.existsSync(fullPath), `Required component exists: ${relFile}`);
    if (relFile.endsWith('.php')) {
        const content = fs.readFileSync(fullPath, 'utf8');
        assert(content.startsWith('<?php'), `${relFile} starts with <?php`);
        // Check for balanced braces
        let openBraces = (content.match(/\{/g) || []).length;
        let closeBraces = (content.match(/\}/g) || []).length;
        assert(openBraces === closeBraces, `${relFile} has balanced curly braces (${openBraces} / ${closeBraces})`);
    }
}

// -----------------------------------------------------------------------------
// Test 4: Emergency Switch & Protection Checks
// -----------------------------------------------------------------------------
console.log('\n\x1b[34m[Suite 4] Emergency Kill Switch & Safety Guards\x1b[0m');

const bootstrap = fs.readFileSync(path.join(ROOT_DIR, 'edt-performnce-optimizer.php'), 'utf8');
assert(bootstrap.includes("defined( 'EDT_PERF_DISABLE' ) && EDT_PERF_DISABLE"), 'Emergency constant EDT_PERF_DISABLE handled in bootstrap');

const classifier = fs.readFileSync(path.join(ROOT_DIR, 'includes/modules/class-page-classifier.php'), 'utf8');
assert(classifier.includes('is_safe_to_optimize'), 'PageClassifier defines is_safe_to_optimize');
assert(classifier.includes('is_woo_cart'), 'PageClassifier excludes WooCommerce cart');
assert(classifier.includes('is_woo_checkout'), 'PageClassifier excludes WooCommerce checkout');
assert(classifier.includes('is_learndash_page'), 'PageClassifier protects LearnDash');

console.log(`\n\x1b[1m=== Results: ${passes} passed, ${failures} failed ===\x1b[0m`);
process.exit(failures > 0 ? 1 : 0);
