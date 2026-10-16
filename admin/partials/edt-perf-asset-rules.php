<?php
/**
 * Asset Rules admin page.
 *
 * @package EDT_Performance_Optimizer
 */

if ( ! defined( 'WPINC' ) ) die;
if ( ! current_user_can( 'manage_options' ) ) return;

$options = get_option( EDT_PERF_OPTIONS_KEY, [] );
$rules   = $options['asset_rules'] ?? [];

$risk_colors = [
	'low'       => '#27ae60',
	'medium'    => '#e67e22',
	'high'      => '#e74c3c',
	'protected' => '#7f8c8d',
];
?>
<div class="wrap edt-perf-wrap">
	<h1><?php esc_html_e( 'Asset Dequeue Rules', 'edt-performnce-optimizer' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Each rule specifies a WordPress enqueue handle, its asset type, the condition under which it will be dequeued, the risk level, and the evidence for that decision. Toggle "Enabled" to activate/deactivate individual rules. Rules only fire on safe, anonymous, public pages — never on checkout, account, admin, or Elementor editor pages.', 'edt-performnce-optimizer' ); ?>
	</p>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( '✅ Rules saved.', 'edt-performnce-optimizer' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'edt_perf_save_options' ); ?>
		<input type="hidden" name="action" value="edt_perf_save_options">

		<table class="wp-list-table widefat fixed striped edt-rules-table">
			<thead>
				<tr>
					<th style="width:30px"><?php esc_html_e( 'On', 'edt-performnce-optimizer' ); ?></th>
					<th style="width:220px"><?php esc_html_e( 'Handle', 'edt-performnce-optimizer' ); ?></th>
					<th style="width:55px"><?php esc_html_e( 'Type', 'edt-performnce-optimizer' ); ?></th>
					<th style="width:75px"><?php esc_html_e( 'Action', 'edt-performnce-optimizer' ); ?></th>
					<th style="width:200px"><?php esc_html_e( 'Condition', 'edt-performnce-optimizer' ); ?></th>
					<th style="width:60px"><?php esc_html_e( 'Risk', 'edt-performnce-optimizer' ); ?></th>
					<th><?php esc_html_e( 'Evidence', 'edt-performnce-optimizer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rules as $handle => $rule ) :
					$enabled   = $rule['enabled'] ?? false;
					$risk      = $rule['risk'] ?? 'medium';
					$risk_color = $risk_colors[ $risk ] ?? '#999';
				?>
				<tr>
					<td>
						<label class="edt-toggle">
							<input type="hidden" name="edt_perf[asset_rules][<?php echo esc_attr( $handle ); ?>][enabled]" value="0">
							<input type="checkbox"
								name="edt_perf[asset_rules][<?php echo esc_attr( $handle ); ?>][enabled]"
								value="1"
								<?php checked( $enabled ); ?>
							>
						</label>
					</td>
					<td><code><?php echo esc_html( $handle ); ?></code></td>
					<td><span class="edt-type-badge edt-type-<?php echo esc_attr( $rule['type'] ?? 'style' ); ?>"><?php echo esc_html( $rule['type'] ?? 'style' ); ?></span></td>
					<td><?php echo esc_html( $rule['action'] ?? 'dequeue' ); ?></td>
					<td><code><?php echo esc_html( $rule['condition'] ?? '' ); ?></code></td>
					<td><span style="color:<?php echo esc_attr( $risk_color ); ?>;font-weight:bold"><?php echo esc_html( $risk ); ?></span></td>
					<td><?php echo esc_html( $rule['evidence'] ?? '' ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Rules', 'edt-performnce-optimizer' ); ?></button>
		</p>
	</form>

	<hr>
	<h2><?php esc_html_e( 'Condition Reference', 'edt-performnce-optimizer' ); ?></h2>
	<table class="widefat fixed striped">
		<thead><tr><th><?php esc_html_e( 'Condition Key', 'edt-performnce-optimizer' ); ?></th><th><?php esc_html_e( 'Dequeues when...', 'edt-performnce-optimizer' ); ?></th></tr></thead>
		<tbody>
			<tr><td><code>not_learndash_page</code></td><td><?php esc_html_e( 'Page is not a LearnDash course/lesson/topic/quiz/certificate', 'edt-performnce-optimizer' ); ?></td></tr>
			<tr><td><code>not_learndash_page_and_not_logged_in</code></td><td><?php esc_html_e( 'Not a LearnDash page AND user is not logged in', 'edt-performnce-optimizer' ); ?></td></tr>
			<tr><td><code>not_woo_page</code></td><td><?php esc_html_e( 'Not a WooCommerce shop/product/cart/checkout/account/order page', 'edt-performnce-optimizer' ); ?></td></tr>
			<tr><td><code>not_woo_page_and_not_mec_page</code></td><td><?php esc_html_e( 'Not a WooCommerce OR MEC event page (used for jQuery UI datepicker)', 'edt-performnce-optimizer' ); ?></td></tr>
			<tr><td><code>not_mec_page</code></td><td><?php esc_html_e( 'Not a Modern Events Calendar page', 'edt-performnce-optimizer' ); ?></td></tr>
			<tr><td><code>not_affiliate_page</code></td><td><?php esc_html_e( 'Not an affiliate/referral page', 'edt-performnce-optimizer' ); ?></td></tr>
			<tr><td><code>always</code></td><td><?php esc_html_e( 'Always dequeue (use with extreme caution)', 'edt-performnce-optimizer' ); ?></td></tr>
			<tr><td><code>never</code></td><td><?php esc_html_e( 'Never dequeue (disabled)', 'edt-performnce-optimizer' ); ?></td></tr>
		</tbody>
	</table>
</div>

