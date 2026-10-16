<?php
/**
 * Settings admin page.
 *
 * @package EDT_Performance_Optimizer
 */

if ( ! defined( 'WPINC' ) ) die;
if ( ! current_user_can( 'manage_options' ) ) return;

$options  = get_option( EDT_PERF_OPTIONS_KEY, [] );
$saved    = isset( $_GET['saved'] );
?>
<div class="wrap edt-perf-wrap">
	<h1><?php esc_html_e( 'EDT Performance Optimizer — Settings', 'edt-performnce-optimizer' ); ?></h1>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( '✅ Settings saved.', 'edt-performnce-optimizer' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'edt_perf_save_options' ); ?>
		<input type="hidden" name="action" value="edt_perf_save_options">
		<input type="hidden" name="edt_perf_settings_form" value="1">

		<table class="form-table" role="presentation">

			<!-- Master Switch -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Master Switch', 'edt-performnce-optimizer' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="edt_perf[master_enabled]" value="1" <?php checked( $options['master_enabled'] ?? true ); ?>>
						<?php esc_html_e( 'Enable all optimizations (uncheck to disable everything without deactivating the plugin)', 'edt-performnce-optimizer' ); ?>
					</label>
				</td>
			</tr>

			<!-- Emergency Rollback -->
			<tr>
				<th scope="row"><span style="color:#c0392b"><?php esc_html_e( 'Emergency Rollback', 'edt-performnce-optimizer' ); ?></span></th>
				<td>
					<label>
						<input type="checkbox" name="edt_perf[emergency_rollback]" value="1" <?php checked( $options['emergency_rollback'] ?? false ); ?>>
						<?php esc_html_e( '🚨 Enable emergency rollback — disables ALL plugin transformations immediately', 'edt-performnce-optimizer' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Alternatively, add define("EDT_PERF_DISABLE", true); to wp-config.php for a server-level kill switch.', 'edt-performnce-optimizer' ); ?></p>
				</td>
			</tr>

			<!-- Module: Asset Manager -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Asset Manager Module', 'edt-performnce-optimizer' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="edt_perf[module_asset_manager]" value="1" <?php checked( $options['module_asset_manager'] ?? true ); ?>>
						<?php esc_html_e( 'Enable conditional CSS/JS dequeue rules', 'edt-performnce-optimizer' ); ?>
					</label>
				</td>
			</tr>

			<!-- Module: LCP Guard -->
			<tr>
				<th scope="row"><?php esc_html_e( 'LCP Priority Module', 'edt-performnce-optimizer' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="edt_perf[module_lcp_guard]" value="1" <?php checked( $options['module_lcp_guard'] ?? true ); ?>>
						<?php esc_html_e( 'Fix logo fetchpriority="high" conflict with true hero LCP image', 'edt-performnce-optimizer' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Evidence: The OceanWP site logo has fetchpriority="high" set by AirLift, competing with the actual hero LCP image. This module changes the logo to fetchpriority="auto".', 'edt-performnce-optimizer' ); ?></p>
				</td>
			</tr>

			<!-- Module: Compatibility Scanner -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Compatibility Scanner', 'edt-performnce-optimizer' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="edt_perf[module_compatibility_scanner]" value="1" <?php checked( $options['module_compatibility_scanner'] ?? true ); ?>>
						<?php esc_html_e( 'Scan for conflicts with AirLift, AC Smart Performance Guard, Kinsta, Elementor', 'edt-performnce-optimizer' ); ?>
					</label>
				</td>
			</tr>

			<!-- HTML Comment Log (Diagnostic) -->
			<tr>
				<th scope="row"><?php esc_html_e( 'HTML Comment Log', 'edt-performnce-optimizer' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="edt_perf[enable_html_comment_log]" value="1" <?php checked( $options['enable_html_comment_log'] ?? false ); ?>>
						<?php esc_html_e( 'Add <!-- EDT Perf --> HTML comments to page source (staging use only)', 'edt-performnce-optimizer' ); ?>
					</label>
					<p class="description" style="color:#c0392b"><?php esc_html_e( '⚠️ Only enable on staging — this adds diagnostic data to public page source. Disable on production.', 'edt-performnce-optimizer' ); ?></p>
				</td>
			</tr>

			<!-- Log max entries -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Max Log Entries', 'edt-performnce-optimizer' ); ?></th>
				<td>
					<input type="number" name="edt_perf[log_max_entries]" value="<?php echo esc_attr( $options['log_max_entries'] ?? 100 ); ?>" min="10" max="1000" class="small-text">
				</td>
			</tr>

			<!-- Excluded Page IDs -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Excluded Page IDs', 'edt-performnce-optimizer' ); ?></th>
				<td>
					<input type="text"
						name="edt_perf[excluded_page_ids_raw]"
						value="<?php echo esc_attr( implode( ', ', $options['excluded_page_ids'] ?? [] ) ); ?>"
						class="regular-text"
						placeholder="123, 456, 789"
					>
					<p class="description"><?php esc_html_e( 'Comma-separated WordPress page IDs. Rules will never apply to these pages.', 'edt-performnce-optimizer' ); ?></p>
				</td>
			</tr>

		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'edt-performnce-optimizer' ); ?></button>
		</p>
	</form>

	<hr>
	<h2><?php esc_html_e( 'Configuration Export', 'edt-performnce-optimizer' ); ?></h2>
	<p><?php esc_html_e( 'Copy this JSON to document the current configuration for rollback reference.', 'edt-performnce-optimizer' ); ?></p>
	<textarea class="large-text code" rows="8" readonly><?php echo esc_textarea( wp_json_encode( $options, JSON_PRETTY_PRINT ) ); ?></textarea>
</div>

