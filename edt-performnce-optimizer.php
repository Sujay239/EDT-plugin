<?php
/**
 * The plugin bootstrap file
 *
 * @link              https://sujay-kumar-kotal.vercel.app
 * @since             1.0.0
 * @package           EDT_Performance_Optimizer
 *
 * @wordpress-plugin
 * Plugin Name:       EDT Performance Optimizer
 * Plugin URI:        https://autocomputation.com
 * Description:       LCP-first performance optimizer for EazyDogTraining.com. Safely dequeues unused assets on public marketing pages, detects conflicts with AirLift/AC Smart Performance Guard, and provides a full admin dashboard with rollback.
 * Version:           2.4.0
 * Author:            Sujay Kumar Kotal
 * Author URI:        https://sujay-kumar-kotal.vercel.app/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       edt-performnce-optimizer
 * Domain Path:       /languages
 * Requires PHP:      8.2
 * Requires at least: 6.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// -----------------------------------------------------------------------
// Emergency kill switch: define EDT_PERF_DISABLE=true in wp-config.php
// to disable ALL transformations before any hooks fire.
// -----------------------------------------------------------------------
if ( defined( 'EDT_PERF_DISABLE' ) && EDT_PERF_DISABLE ) {
	return;
}

if ( ! defined( 'EDT_PERF_VERSION' ) ) {
	define( 'EDT_PERF_VERSION', '2.4.0' );
}
if ( ! defined( 'EDT_PERF_PLUGIN_FILE' ) ) {
	define( 'EDT_PERF_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'EDT_PERF_PLUGIN_DIR' ) ) {
	define( 'EDT_PERF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'EDT_PERF_PLUGIN_URL' ) ) {
	define( 'EDT_PERF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'EDT_PERF_SLUG' ) ) {
	define( 'EDT_PERF_SLUG', 'edt-performnce-optimizer' );
}
if ( ! defined( 'EDT_PERF_OPTIONS_KEY' ) ) {
	define( 'EDT_PERF_OPTIONS_KEY', 'edt_perf_options' );
}
if ( ! defined( 'EDT_PERF_LOG_KEY' ) ) {
	define( 'EDT_PERF_LOG_KEY', 'edt_perf_log' );
}

/**
 * Activation hook
 */
if ( ! function_exists( 'activate_edt_performnce_optimizer' ) ) {
	function activate_edt_performnce_optimizer(): void {
		require_once EDT_PERF_PLUGIN_DIR . 'includes/class-edt-performnce-optimizer-activator.php';
		if ( class_exists( 'EDT_Performance_Optimizer_Activator' ) ) {
			EDT_Performance_Optimizer_Activator::activate();
		} elseif ( class_exists( 'Edt_Performnce_Optimizer_Activator' ) ) {
			Edt_Performnce_Optimizer_Activator::activate();
		}
	}
}

/**
 * Deactivation hook
 */
if ( ! function_exists( 'deactivate_edt_performnce_optimizer' ) ) {
	function deactivate_edt_performnce_optimizer(): void {
		require_once EDT_PERF_PLUGIN_DIR . 'includes/class-edt-performnce-optimizer-deactivator.php';
		if ( class_exists( 'EDT_Performance_Optimizer_Deactivator' ) ) {
			EDT_Performance_Optimizer_Deactivator::deactivate();
		} elseif ( class_exists( 'Edt_Performnce_Optimizer_Deactivator' ) ) {
			Edt_Performnce_Optimizer_Deactivator::deactivate();
		}
	}
}

register_activation_hook( __FILE__, 'activate_edt_performnce_optimizer' );
register_deactivation_hook( __FILE__, 'deactivate_edt_performnce_optimizer' );

/**
 * Autoloader for plugin classes
 */
spl_autoload_register( function ( string $class ): void {
	// Only handle our namespace.
	$prefixes = [
		'EDT_Performance_Optimizer_' => EDT_PERF_PLUGIN_DIR . 'includes/',
		'EDT_Perf_Admin_'            => EDT_PERF_PLUGIN_DIR . 'admin/',
		'EDT_Perf_Public_'           => EDT_PERF_PLUGIN_DIR . 'public/',
		'EDT_Perf_Modules_'          => EDT_PERF_PLUGIN_DIR . 'includes/modules/',
	];

	foreach ( $prefixes as $prefix => $base_dir ) {
		if ( strpos( $class, $prefix ) !== 0 ) {
			continue;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = $base_dir . 'class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
		if ( file_exists( $file ) ) {
			require $file;
			return;
		}
	}
} );

/**
 * Load the core plugin class.
 */
require_once EDT_PERF_PLUGIN_DIR . 'includes/class-edt-performnce-optimizer.php';

/**
 * Begins execution of the plugin.
 */
if ( ! function_exists( 'run_edt_performnce_optimizer' ) ) {
	function run_edt_performnce_optimizer(): void {
		if ( class_exists( 'Edt_Performnce_Optimizer' ) ) {
			$plugin = new Edt_Performnce_Optimizer();
			$plugin->run();
		}

		// Load WP-CLI commands if running in CLI environment.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once EDT_PERF_PLUGIN_DIR . 'includes/class-wp-cli.php';
		}
	}
}
run_edt_performnce_optimizer();
