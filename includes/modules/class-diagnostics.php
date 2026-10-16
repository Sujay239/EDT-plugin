<?php
/**
 * Diagnostics — provides the Diagnostics module for admin inspection.
 *
 * @package    EDT_Performance_Optimizer
 * @subpackage EDT_Performance_Optimizer/includes/modules
 */

class EDT_Perf_Modules_Diagnostics {

	private EDT_Perf_Modules_Page_Classifier $classifier;
	private EDT_Perf_Modules_Compatibility_Scanner $scanner;
	private array $options;

	public function __construct(
		EDT_Perf_Modules_Page_Classifier $classifier,
		EDT_Perf_Modules_Compatibility_Scanner $scanner
	) {
		$this->classifier = $classifier;
		$this->scanner    = $scanner;
		$this->options    = get_option( EDT_PERF_OPTIONS_KEY, [] );
	}

	/**
	 * Returns a full diagnostic report for the admin.
	 */
	public function get_report(): array {
		return [
			'plugin_version'       => EDT_PERF_VERSION,
			'wp_version'           => get_bloginfo( 'version' ),
			'php_version'          => PHP_VERSION,
			'master_enabled'       => $this->options['master_enabled'] ?? true,
			'emergency_rollback'   => $this->options['emergency_rollback'] ?? false,
			'asset_rules_count'    => count( $this->options['asset_rules'] ?? [] ),
			'enabled_rules_count'  => count( array_filter(
				$this->options['asset_rules'] ?? [],
				fn( $r ) => $r['enabled'] ?? false
			) ),
			'compatibility'        => $this->scanner->scan(),
			'action_log'           => array_slice(
				get_option( EDT_PERF_LOG_KEY, [] ),
				-50
			),
			'excluded_page_ids'    => $this->options['excluded_page_ids'] ?? [],
			'excluded_patterns'    => $this->options['excluded_path_patterns'] ?? [],
		];
	}

	/**
	 * Clears the persistent action log.
	 */
	public function clear_log(): void {
		update_option( EDT_PERF_LOG_KEY, [], 'no' );
	}

	/**
	 * Invalidates the compatibility scan transient.
	 */
	public function invalidate_scan_cache(): void {
		delete_transient( 'edt_perf_compatibility_scan' );
	}
}

