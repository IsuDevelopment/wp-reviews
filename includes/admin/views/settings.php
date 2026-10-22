<?php
/**
 * View: settings and license.
 *
 * @package IsuDev\Reviews
 *
 * @var array<string, string> $settings
 * @var array<string, mixed>  $license
 */

use IsuDev\Reviews\Plugin;

defined( 'ABSPATH' ) || exit;

$isurev_status_labels = array(
	'none'             => __( 'Free version', 'isudev-reviews' ),
	'active'           => __( 'Active', 'isudev-reviews' ),
	'expired'          => __( 'Expired', 'isudev-reviews' ),
	'invalid'          => __( 'Invalid key', 'isudev-reviews' ),
	'error'            => __( 'Could not verify', 'isudev-reviews' ),
	'activation_limit' => __( 'Site limit reached: free a site in your isuDev account', 'isudev-reviews' ),
);
?>
<div class="wrap isurev-admin">
	<h1><?php esc_html_e( 'Reviews settings', 'isudev-reviews' ); ?></h1>

	<div class="isurev-panel isurev-license isurev-license--<?php echo esc_attr( (string) $license['tier'] ); ?>">
		<h2>
			<?php echo 'pro' === $license['tier'] ? esc_html__( 'Pro license', 'isudev-reviews' ) : esc_html__( 'Free version', 'isudev-reviews' ); ?>
			<span class="isurev-status isurev-status--<?php echo 'active' === $license['status'] ? 'ok' : 'muted'; ?>"><?php echo esc_html( $isurev_status_labels[ $license['status'] ] ?? (string) $license['status'] ); ?></span>
		</h2>
		<?php if ( 'pro' === $license['tier'] && $license['expires_at'] ) : ?>
			<p>
				<?php
				/* translators: %s: date. */
				echo esc_html( sprintf( __( 'Renews or expires on %s.', 'isudev-reviews' ), wp_date( (string) get_option( 'date_format' ), (int) strtotime( (string) $license['expires_at'] ) ) ) );
				?>
			</p>
		<?php elseif ( 'pro' !== $license['tier'] ) : ?>
			<p><?php esc_html_e( 'Pro adds Facebook reviews, full review history, automatic sync, multiple sources and premium templates.', 'isudev-reviews' ); ?></p>
		<?php endif; ?>
		<?php if ( 'pro' !== $license['tier'] && Plugin::link( 'pricing' ) ) : ?>
			<p><a class="button button-primary" href="<?php echo esc_url( Plugin::link( 'pricing' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get Pro', 'isudev-reviews' ); ?></a></p>
		<?php elseif ( 'pro' === $license['tier'] && Plugin::link( 'account' ) ) : ?>
			<p><a href="<?php echo esc_url( Plugin::link( 'account' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Manage your license, sites and billing', 'isudev-reviews' ); ?></a></p>
		<?php endif; ?>
		<?php if ( $license['message'] ) : ?>
			<p class="isurev-status--error"><?php echo esc_html( (string) $license['message'] ); ?></p>
		<?php endif; ?>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
		<?php wp_nonce_field( 'isurev_save_settings' ); ?>
		<input type="hidden" name="action" value="isurev_save_settings">
		<table class="form-table" role="presentation">
			<?php
			foreach (
				array(
					'license_key'    => array( __( 'License key', 'isudev-reviews' ), __( 'From your isuDev account. Leave empty for the free version.', 'isudev-reviews' ) ),
					'google_api_key' => array( __( 'Google API key', 'isudev-reviews' ), __( 'Only for the "Google Places API" provider. Restrict the key to the Places API (New).', 'isudev-reviews' ) ),
				) as $isurev_key => $isurev_field
			) :
				$isurev_has_value = '' !== $settings[ $isurev_key ];
				?>
				<tr>
					<th scope="row"><label for="isurev-<?php echo esc_attr( $isurev_key ); ?>"><?php echo esc_html( $isurev_field[0] ); ?></label></th>
					<td>
						<?php // Secrets are write-only: the stored value is never printed back. ?>
						<input type="password" class="regular-text" id="isurev-<?php echo esc_attr( $isurev_key ); ?>" name="isurev[<?php echo esc_attr( $isurev_key ); ?>]" value="" placeholder="<?php echo $isurev_has_value ? esc_attr__( 'Saved. Type to replace.', 'isudev-reviews' ) : ''; ?>" autocomplete="new-password" spellcheck="false">
						<?php if ( $isurev_has_value ) : ?>
							<label><input type="checkbox" name="isurev[<?php echo esc_attr( $isurev_key ); ?>_clear]" value="1"> <?php esc_html_e( 'Remove', 'isudev-reviews' ); ?></label>
						<?php endif; ?>
						<p class="description"><?php echo esc_html( $isurev_field[1] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><label for="isurev-language"><?php esc_html_e( 'Review language', 'isudev-reviews' ); ?></label></th>
				<td>
					<input type="text" class="small-text" id="isurev-language" name="isurev[language]" value="<?php echo esc_attr( $settings['language'] ); ?>" maxlength="5">
					<p class="description"><?php esc_html_e( 'Two-letter code, e.g. pl. Used when the source supports translations.', 'isudev-reviews' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="isurev-api-url"><?php esc_html_e( 'Cloud API URL', 'isudev-reviews' ); ?></label></th>
				<td>
					<input type="url" class="regular-text code" id="isurev-api-url" name="isurev[api_url]" value="<?php echo esc_attr( $settings['api_url'] ); ?>">
					<p class="description"><?php esc_html_e( 'Advanced. Change only for development.', 'isudev-reviews' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
