<?php
/**
 * Fires when the plugin is uninstalled.
 * Removes all plugin options and transients from the database.
 *
 * @package EDT_Performance_Optimizer
 */

// If uninstall not called from WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Remove plugin options.
delete_option( 'edt_perf_options' );
delete_option( 'edt_perf_log' );

// Remove transients.
delete_transient( 'edt_perf_compatibility_scan' );
delete_transient( 'edt_perf_page_classifier_cache' );
