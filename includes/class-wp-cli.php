<?php
/**
 * WP-CLI commands for EDT Performance Optimizer.
 *
 * @package    EDT_Performance_Optimizer
 * @subpackage EDT_Performance_Optimizer/includes
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class EDT_Performance_Optimizer_CLI {

	/**
	 * Displays current optimizer status and health.
	 *
	 * ## EXAMPLES
	 *
	 *     wp edt-perf status
	 *
	 * @when after_wp_load
	 */
	public function status( array $args, array $assoc_args ): void {
		$options  = get_option( EDT_PERF_OPTIONS_KEY, [] );
		$rules    = $options['asset_rules'] ?? [];
		$enabled  = count( array_filter( $rules, fn( $r ) => ! empty( $r['enabled'] ) ) );
		$total    = count( $rules );
		$rollback = ! empty( $options['emergency_rollback'] );
		$master   = ! empty( $options['master_enabled'] );

		WP_CLI::line( WP_CLI::colorize( '%G=== EDT Performance Optimizer Status ===%n' ) );
		WP_CLI::line( sprintf( 'Version:            %s', EDT_PERF_VERSION ) );
		WP_CLI::line( sprintf( 'Master Switch:      %s', $master ? WP_CLI::colorize( '%gEnabled%n' ) : WP_CLI::colorize( '%rDisabled%n' ) ) );
		WP_CLI::line( sprintf( 'Emergency Rollback: %s', $rollback ? WP_CLI::colorize( '%rACTIVE (All optimizations halted)%n' ) : WP_CLI::colorize( '%gInactive (Normal operation)%n' ) ) );
		WP_CLI::line( sprintf( 'Asset Rules:        %d active / %d configured', $enabled, $total ) );
		WP_CLI::line( sprintf( 'Kill Switch Const:  %s', defined( 'EDT_PERF_DISABLE' ) && EDT_PERF_DISABLE ? WP_CLI::colorize( '%rEDT_PERF_DISABLE is TRUE in wp-config.php%n' ) : 'Not set' ) );
	}

	/**
	 * Executes an optimizer compatibility scan and prints inventory.
	 *
	 * ## EXAMPLES
	 *
	 *     wp edt-perf scan
	 *
	 * @when after_wp_load
	 */
	public function scan( array $args, array $assoc_args ): void {
		require_once EDT_PERF_PLUGIN_DIR . 'includes/modules/class-compatibility-scanner.php';
		$scanner = new EDT_Perf_Modules_Compatibility_Scanner();
		delete_transient( 'edt_perf_compatibility_scan' );
		$result = $scanner->scan();

		WP_CLI::line( WP_CLI::colorize( '%G=== Active Optimizer Inventory ===%n' ) );

		$items = [];
		foreach ( $result['inventory'] as $key => $data ) {
			$items[] = [
				'Optimizer' => $data['name'],
				'Active'    => ! empty( $data['active'] ) ? 'Yes' : 'No',
				'Features'  => implode( ', ', array_keys( $data['owns'] ?? [] ) ),
			];
		}
		WP_CLI\Utils\format_items( 'table', $items, [ 'Optimizer', 'Active', 'Features' ] );

		if ( ! empty( $result['warnings'] ) ) {
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%YWarnings & Boundaries:%n' ) );
			foreach ( $result['warnings'] as $warning ) {
				WP_CLI::line( ' - ' . wp_strip_all_tags( $warning ) );
			}
		}
	}

	/**
	 * Manages asset dequeue rules.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : Action to perform: list, enable, disable.
	 *
	 * [<handle>]
	 * : The script or style handle to enable or disable.
	 *
	 * ## EXAMPLES
	 *
	 *     wp edt-perf rules list
	 *     wp edt-perf rules enable learndash-css
	 *     wp edt-perf rules disable sumosubs-frontend-css
	 *
	 * @when after_wp_load
	 */
	public function rules( array $args, array $assoc_args ): void {
		$action  = $args[0] ?? 'list';
		$handle  = $args[1] ?? '';
		$options = get_option( EDT_PERF_OPTIONS_KEY, [] );
		$rules   = $options['asset_rules'] ?? [];

		if ( $action === 'list' ) {
			$items = [];
			foreach ( $rules as $h => $r ) {
				$items[] = [
					'Handle'    => $h,
					'Type'      => $r['type'] ?? 'style',
					'Status'    => ! empty( $r['enabled'] ) ? 'Enabled' : 'Disabled',
					'Condition' => $r['condition'] ?? '',
					'Risk'      => $r['risk'] ?? 'medium',
				];
			}
			WP_CLI\Utils\format_items( 'table', $items, [ 'Handle', 'Type', 'Status', 'Condition', 'Risk' ] );
			return;
		}

		if ( ! $handle ) {
			WP_CLI::error( 'Please specify an asset handle.' );
		}

		if ( ! isset( $rules[ $handle ] ) ) {
			WP_CLI::error( sprintf( 'Rule for handle "%s" does not exist.', $handle ) );
		}

		if ( $action === 'enable' ) {
			$options['asset_rules'][ $handle ]['enabled'] = true;
			update_option( EDT_PERF_OPTIONS_KEY, $options );
			WP_CLI::success( sprintf( 'Rule "%s" enabled.', $handle ) );
		} elseif ( $action === 'disable' ) {
			$options['asset_rules'][ $handle ]['enabled'] = false;
			update_option( EDT_PERF_OPTIONS_KEY, $options );
			WP_CLI::success( sprintf( 'Rule "%s" disabled.', $handle ) );
		} else {
			WP_CLI::error( 'Invalid action. Use: list, enable, disable.' );
		}
	}

	/**
	 * Toggles emergency rollback.
	 *
	 * ## OPTIONS
	 *
	 * <state>
	 * : on or off.
	 *
	 * ## EXAMPLES
	 *
	 *     wp edt-perf rollback on
	 *     wp edt-perf rollback off
	 *
	 * @when after_wp_load
	 */
	public function rollback( array $args, array $assoc_args ): void {
		$state   = strtolower( $args[0] ?? '' );
		$options = get_option( EDT_PERF_OPTIONS_KEY, [] );

		if ( $state === 'on' || $state === 'enable' ) {
			$options['emergency_rollback'] = true;
			update_option( EDT_PERF_OPTIONS_KEY, $options );
			WP_CLI::warning( 'Emergency Rollback is now ON. All performance transformations are halted.' );
		} elseif ( $state === 'off' || $state === 'disable' ) {
			$options['emergency_rollback'] = false;
			update_option( EDT_PERF_OPTIONS_KEY, $options );
			WP_CLI::success( 'Emergency Rollback is now OFF. Normal optimization operations resumed.' );
		} else {
			WP_CLI::error( 'Specify "on" or "off". Example: wp edt-perf rollback on' );
		}
	}

	/**
	 * Clears the diagnostic log.
	 *
	 * ## EXAMPLES
	 *
	 *     wp edt-perf clear-log
	 *
	 * @when after_wp_load
	 */
	public function clear_log( array $args, array $assoc_args ): void {
		update_option( EDT_PERF_LOG_KEY, [], 'no' );
		WP_CLI::success( 'EDT Performance diagnostic log cleared.' );
	}
}

WP_CLI::add_command( 'edt-perf', 'EDT_Performance_Optimizer_CLI' );

