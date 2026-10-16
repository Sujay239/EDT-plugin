<?php
/**
 * Admin dashboard — main overview page.
 *
 * @package EDT_Performance_Optimizer
 */

if ( ! defined( 'WPINC' ) ) die;
if ( ! current_user_can( 'manage_options' ) ) return;

$options     = get_option( EDT_PERF_OPTIONS_KEY, [] );
$rollback    = $options['emergency_rollback'] ?? false;
$master      = $options['master_enabled'] ?? true;
$rules       = $options['asset_rules'] ?? [];
$enabled_ct  = count( array_filter( $rules, fn($r) => $r['enabled'] ?? false ) );
$total_ct    = count( $rules );

// Compatibility scan
if ( ! isset( $scan ) || ! is_array( $scan ) ) {
	global $edt_perf_scanner_instance;
	$scan = isset( $edt_perf_scanner_instance ) && method_exists( $edt_perf_scanner_instance, 'scan' )
		? $edt_perf_scanner_instance->scan()
		: [ 'inventory' => [], 'warnings' => [], 'scanned_at' => '' ];
}
?>
<div class="wrap edt-perf-wrap">
	<h1><?php esc_html_e( 'EDT Performance Optimizer', 'edt-performnce-optimizer' ); ?>
		<span class="edt-version">v<?php echo esc_html( EDT_PERF_VERSION ); ?></span>
	</h1>

	<?php if ( isset( $_GET['rollback'] ) ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( '🚨 Emergency Rollback activated. All optimizations are now disabled.', 'edt-performnce-optimizer' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rescanned'] ) ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( '✅ Compatibility scan cache cleared. Next page load will rescan.', 'edt-performnce-optimizer' ); ?></p></div>
	<?php endif; ?>

	<!-- Status Cards -->
	<div class="edt-perf-cards">

		<div class="edt-card <?php echo $rollback ? 'edt-card-danger' : ( $master ? 'edt-card-success' : 'edt-card-warning' ); ?>">
			<h3><?php esc_html_e( 'Plugin Status', 'edt-performnce-optimizer' ); ?></h3>
			<?php if ( $rollback ) : ?>
				<p class="edt-status-badge danger"><?php esc_html_e( '🚨 Emergency Rollback ON', 'edt-performnce-optimizer' ); ?></p>
			<?php elseif ( $master ) : ?>
				<p class="edt-status-badge success"><?php esc_html_e( '✅ Active', 'edt-performnce-optimizer' ); ?></p>
			<?php else : ?>
				<p class="edt-status-badge warning"><?php esc_html_e( '⏸ Master Switch OFF', 'edt-performnce-optimizer' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="edt-card">
			<h3><?php esc_html_e( 'Asset Rules', 'edt-performnce-optimizer' ); ?></h3>
			<p><?php echo esc_html( sprintf( '%d / %d rules enabled', $enabled_ct, $total_ct ) ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=edt-perf-asset-rules' ) ); ?>" class="button button-small"><?php esc_html_e( 'Manage Rules', 'edt-performnce-optimizer' ); ?></a>
		</div>

		<div class="edt-card">
			<h3><?php esc_html_e( 'Scanned At', 'edt-performnce-optimizer' ); ?></h3>
			<p><?php echo esc_html( $scan['scanned_at'] ?: __( 'Not yet scanned', 'edt-performnce-optimizer' ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
				<?php wp_nonce_field( 'edt_perf_rescan' ); ?>
				<input type="hidden" name="action" value="edt_perf_rescan">
				<button type="submit" class="button button-small"><?php esc_html_e( 'Rescan Now', 'edt-performnce-optimizer' ); ?></button>
			</form>
		</div>

	</div><!-- .edt-perf-cards -->

	<!-- Emergency Controls -->
	<div class="edt-perf-section">
		<h2><?php esc_html_e( 'Emergency Controls', 'edt-performnce-optimizer' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Emergency rollback disables ALL plugin transformations instantly. Use if you encounter unexpected frontend issues. You can also define EDT_PERF_DISABLE=true in wp-config.php for a server-level disable.', 'edt-performnce-optimizer' ); ?></p>

		<?php if ( $rollback ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'edt_perf_save_options' ); ?>
				<input type="hidden" name="action" value="edt_perf_save_options">
				<input type="hidden" name="edt_perf[emergency_rollback]" value="0">
				<button type="submit" class="button button-primary"><?php esc_html_e( '✅ Disable Rollback (Resume Normal Operation)', 'edt-performnce-optimizer' ); ?></button>
			</form>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'edt_perf_rollback' ); ?>
				<input type="hidden" name="action" value="edt_perf_rollback">
				<button type="submit" class="button button-secondary edt-danger-btn"
					onclick="return confirm('<?php esc_attr_e( 'Enable emergency rollback? This will disable all optimizations immediately.', 'edt-performnce-optimizer' ); ?>')"
				><?php esc_html_e( '🚨 Enable Emergency Rollback', 'edt-performnce-optimizer' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

	<!-- Optimizer Inventory -->
	<div class="edt-perf-section">
		<h2><?php esc_html_e( 'Active Optimizer Inventory', 'edt-performnce-optimizer' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Detected optimizers and their ownership of features. Our plugin will NOT duplicate these.', 'edt-performnce-optimizer' ); ?></p>

		<?php if ( ! empty( $scan['warnings'] ) ) : ?>
			<div class="edt-notices">
				<?php foreach ( $scan['warnings'] as $warning ) : ?>
					<p><?php echo wp_kses_post( $warning ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Optimizer', 'edt-performnce-optimizer' ); ?></th>
					<th><?php esc_html_e( 'Status', 'edt-performnce-optimizer' ); ?></th>
					<th><?php esc_html_e( 'Owns (Not Duplicated)', 'edt-performnce-optimizer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $scan['inventory'] as $key => $optimizer ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $optimizer['name'] ); ?></strong></td>
						<td><?php echo $optimizer['active'] ? '<span class="edt-badge-green">Active</span>' : '<span class="edt-badge-gray">Inactive</span>'; ?></td>
						<td>
							<?php if ( ! empty( $optimizer['owns'] ) ) : ?>
								<ul class="edt-owns-list">
									<?php foreach ( $optimizer['owns'] as $feature => $desc ) : ?>
										<li><code><?php echo esc_html( $feature ); ?></code>: <?php echo esc_html( $desc ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<em><?php esc_html_e( '—', 'edt-performnce-optimizer' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Optimization Status Summary -->
	<div class="edt-perf-section">
		<h2><?php esc_html_e( 'Active Optimization Strategy', 'edt-performnce-optimizer' ); ?></h2>
		<p class="description"><?php esc_html_e( 'LCP-first conditional asset manager running alongside active caching and optimization engines.', 'edt-performnce-optimizer' ); ?></p>
		<div class="edt-perf-cards" style="margin-top:10px;">
			<div class="edt-card">
				<h4><?php esc_html_e( 'Asset Dequeue Engine', 'edt-performnce-optimizer' ); ?></h4>
				<p><?php echo esc_html( sprintf( __( '%d rules active', 'edt-performnce-optimizer' ), $enabled_ct ) ); ?></p>
				<small><?php esc_html_e( 'Removes unused LearnDash, SUMO, and affiliate assets on marketing pages before deferred queues build.', 'edt-performnce-optimizer' ); ?></small>
			</div>
			<div class="edt-card">
				<h4><?php esc_html_e( 'LCP Hero Prioritization', 'edt-performnce-optimizer' ); ?></h4>
				<p><?php echo ! empty( $options['module_lcp_guard'] ) ? '<span class="edt-badge-green">' . esc_html( __( 'Active', 'edt-performnce-optimizer' ) ) . '</span>' : '<span class="edt-badge-gray">' . esc_html( __( 'Disabled', 'edt-performnce-optimizer' ) ) . '</span>'; ?></p>
				<small><?php esc_html_e( 'Demotes header logo preloads/fetchpriority to ensure true hero image receives maximum network priority.', 'edt-performnce-optimizer' ); ?></small>
			</div>
			<div class="edt-card">
				<h4><?php esc_html_e( 'Diagnostics & Logging', 'edt-performnce-optimizer' ); ?></h4>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=edt-perf-diagnostics' ) ); ?>"><?php esc_html_e( 'View Diagnostics Log →', 'edt-performnce-optimizer' ); ?></a></p>
				<small><?php esc_html_e( 'Inspect real-time asset dequeue and keep decisions recorded across front-end page loads.', 'edt-performnce-optimizer' ); ?></small>
			</div>
		</div>
	</div>

</div><!-- .edt-perf-wrap -->
