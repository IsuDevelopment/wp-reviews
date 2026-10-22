<?php
/**
 * View: templates gallery with live previews on sample data.
 *
 * @package IsuDev\Reviews
 *
 * @var array<string, array<string, mixed>> $templates
 * @var string                              $current
 */

use IsuDev\Reviews\Renderer;
use IsuDev\Reviews\Templates;

defined( 'ABSPATH' ) || exit;

$isurev_now     = time();
$isurev_sample  = array(
	array(
		'id'             => 9001,
		'platform'       => 'google',
		'author_name'    => 'Anna Kowalska',
		'author_avatar'  => '',
		'rating'         => 5,
		'recommendation' => '',
		'content'        => __( 'Fast, friendly and fair. They fixed my laptop the same day and explained exactly what was wrong. Highly recommended!', 'isudev-reviews' ),
		'reply_content'  => '',
		'published_at'   => gmdate( 'Y-m-d H:i:s', $isurev_now - 12 * DAY_IN_SECONDS ),
	),
	array(
		'id'             => 9002,
		'platform'       => 'facebook',
		'author_name'    => 'Marek Nowak',
		'author_avatar'  => '',
		'rating'         => null,
		'recommendation' => 'positive',
		'content'        => __( 'Great service and good prices. My PC runs like new after the upgrade.', 'isudev-reviews' ),
		'reply_content'  => __( 'Thank you, Marek!', 'isudev-reviews' ),
		'published_at'   => gmdate( 'Y-m-d H:i:s', $isurev_now - 40 * DAY_IN_SECONDS ),
	),
	array(
		'id'             => 9003,
		'platform'       => 'google',
		'author_name'    => 'Zofia Wiśniewska',
		'author_avatar'  => '',
		'rating'         => 4,
		'recommendation' => '',
		'content'        => __( 'Professional data recovery. A bit pricey, but they saved all my photos.', 'isudev-reviews' ),
		'reply_content'  => '',
		'published_at'   => gmdate( 'Y-m-d H:i:s', $isurev_now - 90 * DAY_IN_SECONDS ),
	),
);
$isurev_summary = array(
	'rating'    => 4.8,
	'count'     => 132,
	'platforms' => array( 'google', 'facebook' ),
);
?>
<div class="wrap isurev-admin">
	<h1><?php esc_html_e( 'Templates', 'isudev-reviews' ); ?></h1>
	<p><?php esc_html_e( 'The default template is used by every Reviews block set to "Site default". Each block can still pick its own.', 'isudev-reviews' ); ?></p>

	<div class="isurev-templates">
		<?php foreach ( $templates as $isurev_template ) : ?>
			<?php $isurev_locked = Templates::is_locked( $isurev_template ); ?>
			<section class="isurev-template<?php echo $current === $isurev_template['slug'] ? ' is-current' : ''; ?><?php echo $isurev_locked ? ' is-locked' : ''; ?>">
				<header class="isurev-template__head">
					<h2>
						<?php echo esc_html( $isurev_template['title'] ); ?>
						<?php if ( 'pro' === $isurev_template['tier'] ) : ?>
							<span class="isurev-badge isurev-badge--pro">Pro</span>
						<?php endif; ?>
					</h2>
					<p><?php echo esc_html( $isurev_template['description'] ); ?></p>
				</header>

				<div class="isurev-template__preview" inert>
					<?php
					// Locked templates still preview, so users see what Pro adds.
					$isurev_html = Renderer::render(
						array(
							'template' => $isurev_template['slug'],
							'columns'  => 3,
						),
						'',
						$isurev_sample,
						$isurev_summary,
						true
					);
					echo $isurev_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer.
					?>
				</div>

				<footer class="isurev-template__foot">
					<?php if ( $current === $isurev_template['slug'] ) : ?>
						<span class="isurev-status isurev-status--ok"><?php esc_html_e( 'Default', 'isudev-reviews' ); ?></span>
					<?php elseif ( $isurev_locked ) : ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=isudev-reviews-settings' ) ); ?>"><?php esc_html_e( 'Unlock with Pro', 'isudev-reviews' ); ?></a>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'isurev_set_template' ); ?>
							<input type="hidden" name="action" value="isurev_set_template">
							<input type="hidden" name="template" value="<?php echo esc_attr( $isurev_template['slug'] ); ?>">
							<button class="button button-primary"><?php esc_html_e( 'Set as default', 'isudev-reviews' ); ?></button>
						</form>
					<?php endif; ?>
				</footer>
			</section>
		<?php endforeach; ?>
	</div>
</div>
