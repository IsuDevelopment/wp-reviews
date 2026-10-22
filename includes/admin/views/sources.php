<?php
/**
 * View: sources.
 *
 * @package IsuDev\Reviews
 *
 * @var array<int, array<string, mixed>>                      $sources
 * @var array<string, \IsuDev\Reviews\Providers\Provider>     $providers
 * @var \IsuDev\Reviews\Review_Repository                     $reviews
 * @var bool                                                  $is_pro
 */

use IsuDev\Reviews\Plugin;
use IsuDev\Reviews\Renderer;

defined( 'ABSPATH' ) || exit;

$isurev_intervals = array(
	'off'    => __( 'Manual only', 'isudev-reviews' ),
	'daily'  => __( 'Daily', 'isudev-reviews' ),
	'weekly' => __( 'Weekly', 'isudev-reviews' ),
);
?>
<div class="wrap isurev-admin">
	<h1><?php esc_html_e( 'Review sources', 'isudev-reviews' ); ?></h1>

	<?php if ( $sources ) : ?>
		<table class="widefat striped isurev-sources">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Source', 'isudev-reviews' ); ?></th>
					<th><?php esc_html_e( 'Rating', 'isudev-reviews' ); ?></th>
					<th><?php esc_html_e( 'Stored', 'isudev-reviews' ); ?></th>
					<th><?php esc_html_e( 'Provider', 'isudev-reviews' ); ?></th>
					<th><?php esc_html_e( 'Auto-sync', 'isudev-reviews' ); ?></th>
					<th><?php esc_html_e( 'Last sync', 'isudev-reviews' ); ?></th>
					<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'isudev-reviews' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $sources as $isurev_source ) : ?>
				<tr>
					<td>
						<?php echo Renderer::platform_icon( (string) $isurev_source['platform'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
						<strong><?php echo esc_html( '' !== (string) $isurev_source['name'] ? (string) $isurev_source['name'] : (string) $isurev_source['external_id'] ); ?></strong>
						<?php if ( '' !== (string) $isurev_source['url'] ) : ?>
							<a href="<?php echo esc_url( (string) $isurev_source['url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Open profile', 'isudev-reviews' ); ?>">↗</a>
						<?php endif; ?>
						<br><code><?php echo esc_html( (string) $isurev_source['external_id'] ); ?></code>
					</td>
					<td>
						<?php if ( $isurev_source['rating_avg'] ) : ?>
							<?php echo Renderer::stars( (float) $isurev_source['rating_avg'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
							<?php echo esc_html( number_format_i18n( (float) $isurev_source['rating_avg'], 1 ) . ' (' . number_format_i18n( (int) $isurev_source['rating_count'] ) . ')' ); ?>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td>
					<?php
					echo esc_html(
						number_format_i18n(
							$reviews->count(
								array(
									'source_ids'     => array( (int) $isurev_source['id'] ),
									'include_hidden' => true,
								)
							)
						)
					);
					?>
						</td>
					<td><?php echo esc_html( isset( $providers[ $isurev_source['provider'] ] ) ? $providers[ $isurev_source['provider'] ]->label() : (string) $isurev_source['provider'] ); ?></td>
					<td><?php echo esc_html( $isurev_intervals[ $isurev_source['sync_interval'] ] ?? (string) $isurev_source['sync_interval'] ); ?></td>
					<td>
						<?php if ( 'error' === $isurev_source['last_status'] ) : ?>
							<span class="isurev-status isurev-status--error" title="<?php echo esc_attr( (string) $isurev_source['last_error'] ); ?>"><?php esc_html_e( 'Error', 'isudev-reviews' ); ?></span>
							<br><small><?php echo esc_html( (string) $isurev_source['last_error'] ); ?></small>
						<?php elseif ( $isurev_source['last_synced_at'] ) : ?>
							<?php
							/* translators: %s: human time difference. */
							echo esc_html( sprintf( __( '%s ago', 'isudev-reviews' ), human_time_diff( (int) strtotime( $isurev_source['last_synced_at'] . ' UTC' ) ) ) );
							?>
						<?php else : ?>
							<?php esc_html_e( 'Never', 'isudev-reviews' ); ?>
						<?php endif; ?>
					</td>
					<td class="isurev-sources__actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'isurev_sync_source' ); ?>
							<input type="hidden" name="action" value="isurev_sync_source">
							<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $isurev_source['id'] ); ?>">
							<button class="button button-primary"><?php esc_html_e( 'Sync now', 'isudev-reviews' ); ?></button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this source and all its reviews?', 'isudev-reviews' ) ); ?>');">
							<?php wp_nonce_field( 'isurev_delete_source' ); ?>
							<input type="hidden" name="action" value="isurev_delete_source">
							<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $isurev_source['id'] ); ?>">
							<button class="button-link button-link-delete"><?php esc_html_e( 'Delete', 'isudev-reviews' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( ! $is_pro && Plugin::link( 'pricing' ) ) : ?>
		<p class="isurev-upsell">
			<?php esc_html_e( 'The free version imports the 8 newest reviews on demand. Pro keeps them in sync automatically, with full history, Facebook and more sources.', 'isudev-reviews' ); ?>
			<a href="<?php echo esc_url( Plugin::link( 'pricing' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get Pro', 'isudev-reviews' ); ?></a>
		</p>
	<?php endif; ?>

	<div class="isurev-panel">
		<h2><?php esc_html_e( 'Try it with demo data', 'isudev-reviews' ); ?></h2>
		<p><?php esc_html_e( 'Imports 30 sample reviews of a fictional coffee roaster. No API or license needed, so it also works on local sites. Delete the demo source when you are done.', 'isudev-reviews' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'isurev_add_demo' ); ?>
			<input type="hidden" name="action" value="isurev_add_demo">
			<button class="button"><?php esc_html_e( 'Import demo reviews', 'isudev-reviews' ); ?></button>
		</form>
	</div>

	<div class="isurev-panel">
		<h2><?php esc_html_e( 'Add a source', 'isudev-reviews' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'isurev_add_source' ); ?>
			<input type="hidden" name="action" value="isurev_add_source">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Platform', 'isudev-reviews' ); ?></th>
					<td>
						<fieldset class="isurev-choices">
							<label><input type="radio" name="platform" value="google" checked> <?php echo Renderer::platform_icon( 'google' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?> Google</label>
							<label>
								<input type="radio" name="platform" value="facebook" <?php disabled( ! Plugin::license()->has( 'facebook' ) ); ?>>
								<?php echo Renderer::platform_icon( 'facebook' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?> Facebook
								<?php if ( ! Plugin::license()->has( 'facebook' ) ) : ?>
									<span class="isurev-badge isurev-badge--pro">Pro</span>
								<?php endif; ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="isurev-external-id"><?php esc_html_e( 'Place ID / Page ID', 'isudev-reviews' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="isurev-external-id" name="external_id" required placeholder="ChIJN1t_tDeuEmsRUsoyG83frY4">
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to Google's Place ID finder. */
								esc_html__( 'Google: find the Place ID with the %s. Facebook: the numeric page ID.', 'isudev-reviews' ),
								'<a href="https://developers.google.com/maps/documentation/places/web-service/place-id#find-id" target="_blank" rel="noopener">' . esc_html__( 'Place ID Finder', 'isudev-reviews' ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="isurev-name"><?php esc_html_e( 'Name (optional)', 'isudev-reviews' ); ?></label></th>
					<td><input type="text" class="regular-text" id="isurev-name" name="name"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'How to fetch', 'isudev-reviews' ); ?></th>
					<td>
						<fieldset class="isurev-choices isurev-choices--stacked">
							<?php foreach ( $providers as $isurev_provider ) : ?>
								<label>
									<input type="radio" name="provider" value="<?php echo esc_attr( $isurev_provider->id() ); ?>" <?php checked( 'cloud', $isurev_provider->id() ); ?>>
									<?php echo esc_html( $isurev_provider->label() ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="isurev-interval"><?php esc_html_e( 'Auto-sync', 'isudev-reviews' ); ?></label></th>
					<td>
						<select id="isurev-interval" name="sync_interval" <?php disabled( ! Plugin::license()->has( 'auto_sync' ) ); ?>>
							<?php foreach ( $isurev_intervals as $isurev_value => $isurev_label ) : ?>
								<option value="<?php echo esc_attr( $isurev_value ); ?>"><?php echo esc_html( $isurev_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( ! Plugin::license()->has( 'auto_sync' ) ) : ?>
							<span class="isurev-badge isurev-badge--pro">Pro</span>
							<p class="description"><?php esc_html_e( 'Automatic syncing of new reviews is part of Pro.', 'isudev-reviews' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Add and fetch reviews', 'isudev-reviews' ) ); ?>
		</form>
	</div>
</div>
