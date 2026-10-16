<?php
/**
 * Mock WordPress Environment to simulate activation and lifecycle.
 */

// Define WordPress constants
define( 'WPINC', 'wp-includes' );
define( 'ABSPATH', __DIR__ . '/../' );
define( 'WP_PLUGIN_DIR', __DIR__ . '/../../' );
define( 'WP_CONTENT_DIR', __DIR__ . '/../../' );

$wp_options = [];
$wp_actions = [];
$wp_filters = [];

function get_option( $name, $default = false ) {
    global $wp_options;
    return $wp_options[$name] ?? $default;
}

function add_option( $name, $val, $dep = '', $auto = 'yes' ) {
    global $wp_options;
    if ( ! isset( $wp_options[$name] ) ) {
        $wp_options[$name] = $val;
        return true;
    }
    return false;
}

function update_option( $name, $val, $auto = 'yes' ) {
    global $wp_options;
    $wp_options[$name] = $val;
    return true;
}

function delete_option( $name ) {
    global $wp_options;
    unset( $wp_options[$name] );
    return true;
}

function get_transient( $name ) { return false; }
function set_transient( $name, $val, $exp = 0 ) { return true; }
function delete_transient( $name ) { return true; }

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
    global $wp_actions;
    $wp_actions[$tag][] = [ 'cb' => $callback, 'args' => $accepted_args ];
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
    global $wp_filters;
    $wp_filters[$tag][] = [ 'cb' => $callback, 'args' => $accepted_args ];
}

function register_activation_hook( $file, $callback ) {
    global $activation_hook;
    $activation_hook = $callback;
}

function register_deactivation_hook( $file, $callback ) {}

function plugin_dir_path( $file ) {
    return dirname( dirname( __FILE__ ) ) . '/';
}

function plugin_dir_url( $file ) {
    return 'http://example.com/wp-content/plugins/edt-performnce-optimizer/';
}

function plugin_basename( $file ) {
    return 'edt-performnce-optimizer/edt-performnce-optimizer.php';
}

function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
    return true;
}

function flush_rewrite_rules() { return true; }
$is_admin_flag = true;
function is_admin() { global $is_admin_flag; return $is_admin_flag; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function is_user_logged_in() { return false; }
function get_queried_object_id() { return 0; }
function get_queried_object() { return null; }
function get_page_template_slug( $post_id = 0 ) { return ''; }
function is_post_type_archive( $post_types = '' ) { return false; }
function get_post_type() { return false; }
function is_front_page() { return true; }
function is_home() { return true; }
function is_singular() { return false; }
function is_page() { return false; }
function is_single() { return false; }
function is_archive() { return false; }
function is_search() { return false; }
function is_404() { return false; }
function is_feed() { return false; }
function is_robots() { return false; }
function is_customize_preview() { return false; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function current_time( $type ) { return date( 'Y-m-d H:i:s' ); }
function get_bloginfo( $show = '' ) { return '7.1.3'; }
function wp_kses_post( $string ) { return $string; }
function esc_html( $string ) { return htmlspecialchars( (string)$string, ENT_QUOTES ); }
function esc_attr( $string ) { return htmlspecialchars( (string)$string, ENT_QUOTES ); }
function esc_url( $string ) { return $string; }
function esc_textarea( $string ) { return htmlspecialchars( (string)$string, ENT_QUOTES ); }
function esc_html_e( $text, $domain = 'default' ) { echo htmlspecialchars( (string)$text, ENT_QUOTES ); }
function esc_attr_e( $text, $domain = 'default' ) { echo htmlspecialchars( (string)$text, ENT_QUOTES ); }
function esc_html__( $text, $domain = 'default' ) { return htmlspecialchars( (string)$text, ENT_QUOTES ); }
function esc_attr__( $text, $domain = 'default' ) { return htmlspecialchars( (string)$text, ENT_QUOTES ); }
function __( $text, $domain = 'default' ) { return $text; }
function _e( $text, $domain = 'default' ) { echo $text; }
function checked( $checked, $current = true, $echo = true ) {
    $res = ((string)$checked === (string)$current) ? "checked='checked'" : '';
    if ($echo) echo $res;
    return $res;
}
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function register_setting( $group, $name, $args = [] ) {}
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {}
function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {}
function wp_enqueue_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ) {}
function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $in_footer = false ) {}
function wp_style_is( $handle, $list = 'enqueued' ) { return false; }
function wp_script_is( $handle, $list = 'enqueued' ) { return false; }
function wp_dequeue_style( $handle ) {}
function wp_dequeue_script( $handle ) {}
function wp_deregister_style( $handle ) {}
function wp_deregister_script( $handle ) {}
function sanitize_text_field( $str ) { return trim( strip_tags( (string)$str ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string)$key ) ); }
function absint( $maybeint ) { return abs( intval( $maybeint ) ); }
function current_user_can( $cap ) { return true; }
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
    if ($echo) echo '<input type="hidden" name="' . esc_attr($name) . '" value="mock_nonce" />';
    return 'mock_nonce';
}
function admin_url( $path = '', $scheme = 'admin' ) { return 'http://example.com/wp-admin/' . $path; }
function wp_json_encode( $data, $options = 0, $depth = 512 ) { return json_encode( $data, $options, $depth ); }

echo "Step 1: Loading plugin bootstrap...\n";
require_once dirname( __DIR__ ) . '/edt-performnce-optimizer.php';
echo "  [OK] Bootstrap loaded successfully.\n";

echo "Step 2: Triggering activation callback...\n";
activate_edt_performnce_optimizer();
echo "  [OK] activate_edt_performnce_optimizer() executed successfully.\n";

echo "Step 3: Triggering admin_init hooks...\n";
if ( isset( $wp_actions['admin_init'] ) ) {
    foreach ( $wp_actions['admin_init'] as $act ) {
        call_user_func( $act['cb'] );
    }
}
echo "  [OK] admin_init executed successfully.\n";

echo "Step 4: Triggering admin_menu hooks...\n";
if ( isset( $wp_actions['admin_menu'] ) ) {
    foreach ( $wp_actions['admin_menu'] as $act ) {
        call_user_func( $act['cb'] );
    }
}
echo "  [OK] admin_menu executed successfully.\n";

echo "Step 5: Triggering admin_notices hooks...\n";
if ( isset( $wp_actions['admin_notices'] ) ) {
    foreach ( $wp_actions['admin_notices'] as $act ) {
        call_user_func( $act['cb'] );
    }
}
echo "  [OK] admin_notices executed successfully.\n";

echo "Step 6: Triggering admin_enqueue_scripts...\n";
if ( isset( $wp_actions['admin_enqueue_scripts'] ) ) {
    foreach ( $wp_actions['admin_enqueue_scripts'] as $act ) {
        call_user_func( $act['cb'], 'toplevel_page_edt-perf-dashboard' );
    }
}
echo "  [OK] admin_enqueue_scripts executed successfully.\n";

echo "Step 7: Rendering Dashboard display partial...\n";
ob_start();
require dirname( __DIR__ ) . '/admin/partials/edt-performnce-optimizer-admin-display.php';
$dashHtml = ob_get_clean();
echo "  [OK] Dashboard rendered " . strlen( $dashHtml ) . " bytes.\n";

echo "Step 8: Rendering Asset Rules display partial...\n";
ob_start();
require dirname( __DIR__ ) . '/admin/partials/edt-perf-asset-rules.php';
$rulesHtml = ob_get_clean();
echo "  [OK] Asset Rules rendered " . strlen( $rulesHtml ) . " bytes.\n";

echo "Step 9: Rendering Settings display partial...\n";
ob_start();
require dirname( __DIR__ ) . '/admin/partials/edt-perf-settings.php';
$settingsHtml = ob_get_clean();
echo "  [OK] Settings rendered " . strlen( $settingsHtml ) . " bytes.\n";

echo "Step 10: Rendering Diagnostics display partial...\n";
ob_start();
require dirname( __DIR__ ) . '/admin/partials/edt-perf-diagnostics.php';
$diagHtml = ob_get_clean();
echo "  [OK] Diagnostics rendered " . strlen( $diagHtml ) . " bytes.\n";

echo "Step 11: Testing Scanner execution directly (is_plugin_active undefined)...\n";
$scanner = new EDT_Perf_Modules_Compatibility_Scanner();
$scan_res = $scanner->scan();
if ( empty( $scan_res['inventory'] ) ) {
    throw new Exception( "Scanner failed to produce inventory" );
}
echo "  [OK] Scanner produced " . count( $scan_res['inventory'] ) . " inventory items without fatal error.\n";

echo "Step 12: Testing 0-argument admin_enqueue_scripts hook...\n";
if ( isset( $wp_actions['admin_enqueue_scripts'] ) ) {
    foreach ( $wp_actions['admin_enqueue_scripts'] as $act ) {
        // Call with 0 arguments to verify PHP 8.2 nullable default
        call_user_func( $act['cb'] );
    }
}
echo "  [OK] 0-argument enqueue executed safely without TypeError.\n";

echo "Step 13: Testing Elementor integration safety...\n";
// Define Elementor dummy class to test null instance protection
eval('namespace Elementor { class Plugin { public static $instance = null; } }');
define( 'ELEMENTOR_VERSION', '4.3.5' );
$scanner_elementor = new EDT_Perf_Modules_Compatibility_Scanner();
delete_transient( 'edt_perf_compatibility_scan' );
$scan_elem = $scanner_elementor->scan();
echo "  [OK] Scanner handled Elementor with null \$instance cleanly.\n";

$classifier = new EDT_Perf_Modules_Page_Classifier();
$classification = $classifier->classify();
echo "  [OK] Classifier handled Elementor with null \$instance cleanly (is_elementor_edit=" . ($classification['is_elementor_edit'] ? 'true' : 'false') . ").\n";

echo "Step 14: Triggering deactivation callback...\n";
deactivate_edt_performnce_optimizer();
echo "  [OK] deactivate_edt_performnce_optimizer() executed successfully.\n";

echo "Step 15: Verifying class aliases...\n";
if ( ! class_exists( 'Edt_Performnce_Optimizer_Activator' ) || ! class_exists( 'EDT_Performance_Optimizer_Activator' ) ) {
    throw new Exception( "Activator class alias failed" );
}
if ( ! class_exists( 'Edt_Performnce_Optimizer_Deactivator' ) || ! class_exists( 'EDT_Performance_Optimizer_Deactivator' ) ) {
    throw new Exception( "Deactivator class alias failed" );
}
echo "  [OK] Class aliases verified.\n";

echo "Step 16: Verifying AirLift detection with real AirLift constant/class...\n";
define( 'AIRLIFT_PLUGIN_LOADED', true );
class ALWPSettings {}
delete_transient( 'edt_perf_compatibility_scan' );
$airlift_scan = ( new EDT_Perf_Modules_Compatibility_Scanner() )->scan();
if ( empty( $airlift_scan['inventory']['airlift']['active'] ) ) {
    throw new Exception( "AirLift detection failed with AIRLIFT_PLUGIN_LOADED and ALWPSettings defined" );
}
echo "  [OK] AirLift successfully detected as Active.\n";

echo "Step 17: Verifying ACSPG detection with ACSP_VERSION...\n";
define( 'ACSP_VERSION', '7.0.0' );
delete_transient( 'edt_perf_compatibility_scan' );
$acspg_scan = ( new EDT_Perf_Modules_Compatibility_Scanner() )->scan();
if ( empty( $acspg_scan['inventory']['ac_smart_performance_guard']['active'] ) ) {
    throw new Exception( "ACSPG detection failed with ACSP_VERSION defined" );
}
echo "  [OK] ACSPG successfully detected as Active.\n";

echo "Step 18: Verifying option upgrade routine...\n";
$legacy_options = [
    'options_version' => '2.0.0',
    'asset_rules' => [
        'learndash_quiz_front_css-css' => [
            'enabled' => true,
            'type'    => 'style',
        ],
        'learndash-js' => [
            'enabled' => false,
            'type'    => 'script',
        ]
    ]
];
update_option( EDT_PERF_OPTIONS_KEY, $legacy_options );
// Instantiate plugin to trigger upgrade
$upgraded_plugin = new Edt_Performnce_Optimizer();
$saved_opts = get_option( EDT_PERF_OPTIONS_KEY );
if ( ! isset( $saved_opts['asset_rules']['learndash_quiz_front_css'] ) ) {
    throw new Exception( "Migration failed to create canonical learndash_quiz_front_css handle" );
}
if ( $saved_opts['asset_rules']['learndash_quiz_front_css']['enabled'] !== true ) {
    throw new Exception( "Migration failed to preserve enabled status for learndash_quiz_front_css" );
}
if ( ! isset( $saved_opts['asset_rules']['dashicons'] ) ) {
    throw new Exception( "Migration failed to seed dashicons rule" );
}
if ( ! isset( $saved_opts['asset_rules']['mec-frontend-style'] ) ) {
    throw new Exception( "Migration failed to seed mec-frontend-style rule" );
}
if ( ! isset( $saved_opts['asset_rules']['wc-add-to-cart-variation'] ) ) {
    throw new Exception( "Migration failed to seed wc-add-to-cart-variation rule" );
}
if ( ! isset( $saved_opts['asset_rules']['learndash-course-grid-skin-grid'] ) ) {
    throw new Exception( "Migration failed to seed learndash-course-grid-skin-grid rule" );
}
echo "  [OK] Option upgrade successfully migrated legacy handles to canonical handles.\n";

echo "Step 19: Testing filter_style_loader_tag & filter_script_loader_tag...\n";
$is_admin_flag = false;
$classifier = new EDT_Perf_Modules_Page_Classifier();
$asset_mgr  = new EDT_Perf_Modules_Conditional_Asset_Manager( $classifier );

// Test dashicons filtered out for guest
$tag = "<link rel='stylesheet' id='dashicons-css' href='dashicons.min.css' media='all' />";
$filtered = $asset_mgr->filter_style_loader_tag( $tag, 'dashicons', 'dashicons.min.css', 'all' );
if ( $filtered !== '' ) {
    throw new Exception( "filter_style_loader_tag failed to strip dashicons for guest" );
}

// Test MEC style stripped on non-MEC page
$mec_tag = "<link rel='stylesheet' id='mec-frontend-style-css' href='frontend.min.css' media='all' />";
$filtered_mec = $asset_mgr->filter_style_loader_tag( $mec_tag, 'mec-frontend-style-css', 'frontend.min.css', 'all' );
if ( $filtered_mec !== '' ) {
    throw new Exception( "filter_style_loader_tag failed to strip mec-frontend-style on non-MEC page" );
}

// Test below-the-fold stylesheet converted to async (media=print onload)
$brb_tag = "<link rel='stylesheet' id='brb-public-main-css-css' href='public-main.css' media='all' />";
$filtered_brb = $asset_mgr->filter_style_loader_tag( $brb_tag, 'brb-public-main-css-css', 'public-main.css', 'all' );
if ( ! str_contains( $filtered_brb, 'media="print"' ) || ! str_contains( $filtered_brb, "this.media='all'" ) ) {
    throw new Exception( "filter_style_loader_tag failed to convert brb-public-main-css to async" );
}

// Test jquery script deferred
$jq_tag = "<script id='jquery-core-js' src='jquery.min.js'></script>";
$filtered_jq = $asset_mgr->filter_script_loader_tag( $jq_tag, 'jquery-core', 'jquery.min.js' );
if ( ! str_contains( $filtered_jq, 'defer="defer"' ) ) {
    throw new Exception( "filter_script_loader_tag failed to add defer to jquery-core" );
}
echo "  [OK] filter_style_loader_tag and filter_script_loader_tag verified successfully.\n";

echo "\n\x1b[32mALL 19 ACTIVATION AND LIFECYCLE STEPS PASSED WITHOUT ERRORS!\x1b[0m\n";

