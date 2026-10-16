<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    EDT_Performance_Optimizer
 * @subpackage EDT_Performance_Optimizer/includes
 */

if ( ! class_exists( 'EDT_Performance_Optimizer_Deactivator' ) ) {
	class EDT_Performance_Optimizer_Deactivator {

		/**
		 * Cleans up transients on deactivation. Does NOT delete plugin options
		 * (those are preserved so settings survive deactivate/reactivate cycles).
		 * Options are only removed on uninstall via uninstall.php.
		 */
		public static function deactivate(): void {
			// Remove any cached state transients.
			delete_transient( 'edt_perf_page_classifier_cache' );
			delete_transient( 'edt_perf_compatibility_scan' );

			if ( function_exists( 'flush_rewrite_rules' ) ) {
				flush_rewrite_rules();
			}
		}
	}
}

if ( ! class_exists( 'Edt_Performnce_Optimizer_Deactivator' ) && class_exists( 'EDT_Performance_Optimizer_Deactivator' ) ) {
	class_alias( 'EDT_Performance_Optimizer_Deactivator', 'Edt_Performnce_Optimizer_Deactivator' );
}
