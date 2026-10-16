<?php
/**
 * Diagnostics admin page — shows action log and compatibility scan.
 *
 * @package EDT_Performance_Optimizer
 */

if ( ! defined( 'WPINC' ) ) die;
if ( ! current_user_can( 'manage_options' ) ) return;

if ( ! isset( $scan ) || ! is_array( $scan ) ) {
	$scanner = new EDT_Perf_Modules_Compatibility_Scanner();
	$scan    = $scanner->scan();
}

$log    = get_option( EDT_PERF_LOG_KEY, [] );
$recent = array_reverse( array_slice( $log, -100 ) );

$action_colors = [
	'DEQUEUED_STYLE'  => '#27ae60',
	'DEQUEUED_SCRIPT' => '#2980b9',
	'KEPT'            => '#7f8c8d',
	'SKIP'            => '#e67e22',
];
?>
<div class="wrap edt-perf-wrap">
	<h1><?php esc_html_e( 'Diagnostics Log', 'edt-performnce-optimizer' ); ?></h1>

	<?php if ( isset( $_GET['cleared'] ) ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( '✅ Log cleared.', 'edt-performnce-optimizer' ); ?></p></div>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'This log records every asset manager decision. DEQUEUED = rule applied. KEPT = condition not met. SKIP = module disabled/rollback active.', 'edt-performnce-optimizer' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:15px">
		<?php wp_nonce_field( 'edt_perf_clear_log' ); ?>
		<input type="hidden" name="action" value="edt_perf_clear_log">
		<button type="submit" class="button button-secondary"
			onclick="return confirm('<?php esc_attr_e( 'Clear the diagnostics log?', 'edt-performnce-optimizer' ); ?>')"
		><?php esc_html_e( 'Clear Log', 'edt-performnce-optimizer' ); ?></button>
		<span class="description" style="margin-left:10px"><?php echo esc_html( sprintf( '%d entries', count( $log ) ) ); ?></span>
	</form>

	<?php if ( empty( $recent ) ) : ?>
		<p><?php esc_html_e( 'No log entries yet. The log is populated as pages are served with optimizations applied.', 'edt-performnce-optimizer' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:130px"><?php esc_html_e( 'Action', 'edt-performnce-optimizer' ); ?></th>
					<th style="width:220px"><?php esc_html_e( 'Handle', 'edt-performnce-optimizer' ); ?></th>
					<th><?php esc_html_e( 'Reason / Evidence', 'edt-performnce-optimizer' ); ?></th>
					<th><?php esc_html_e( 'URL', 'edt-performnce-optimizer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent as $entry ) :
					$color = $action_colors[ $entry['action'] ?? '' ] ?? '#999';
				?>
				<tr>
					<td><span style="color:<?php echo esc_attr( $color ); ?>;font-weight:bold"><?php echo esc_html( $entry['action'] ?? '' ); ?></span></td>
					<td><code><?php echo esc_html( $entry['handle'] ?? '' ); ?></code></td>
					<td><?php echo esc_html( $entry['reason'] ?? '' ); ?></td>
					<td><small><?php echo esc_html( $entry['url'] ?? '' ); ?></small></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<hr>
	<h2><?php esc_html_e( 'Environment Info', 'edt-performnce-optimizer' ); ?></h2>
	<table class="widefat fixed striped">
		<tbody>
			<tr><th><?php esc_html_e( 'Plugin Version', 'edt-performnce-optimizer' ); ?></th><td><?php echo esc_html( EDT_PERF_VERSION ); ?></td></tr>
			<tr><th><?php esc_html_e( 'WordPress', 'edt-performnce-optimizer' ); ?></th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'PHP', 'edt-performnce-optimizer' ); ?></th><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
			<tr><th><?php esc_html_e( 'AirLift Active', 'edt-performnce-optimizer' ); ?></th><td><?php echo ! empty( $scan['inventory']['airlift']['active'] ) ? '✅ Yes' : '❌ No'; ?></td></tr>
			<tr><th><?php esc_html_e( 'ACSPG Active', 'edt-performnce-optimizer' ); ?></th><td><?php echo ! empty( $scan['inventory']['ac_smart_performance_guard']['active'] ) ? '✅ Yes' : '❌ No'; ?></td></tr>
			<tr><th><?php esc_html_e( 'Kinsta Cache', 'edt-performnce-optimizer' ); ?></th><td><?php echo ! empty( $scan['inventory']['kinsta']['active'] ) ? '✅ Yes' : '❌ No'; ?></td></tr>
			<tr><th><?php esc_html_e( 'EDT_PERF_DISABLE Constant', 'edt-performnce-optimizer' ); ?></th><td><?php echo defined( 'EDT_PERF_DISABLE' ) && EDT_PERF_DISABLE ? '⚠️ TRUE — plugin fully disabled' : 'Not set'; ?></td></tr>
		</tbody>
	</table>
</div>

