<?php
/**
 * Template "default": card.
 *
 * @package IsuDev\Reviews
 *
 * @var array<string, mixed> $review Review row.
 * @var array<string, mixed> $args   Normalized block attributes.
 */

use IsuDev\Reviews\Renderer;

defined( 'ABSPATH' ) || exit;
?>
<article class="isurev-card">
	<header class="isurev-card__head">
		<?php echo Renderer::avatar( $review ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		<div class="isurev-card__author">
			<strong class="isurev-card__name"><?php echo esc_html( (string) $review['author_name'] ); ?></strong>
			<?php echo Renderer::date( $review ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		</div>
		<?php echo Renderer::platform_icon( (string) $review['platform'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
	</header>
	<?php echo Renderer::stars( $review['rating'], (string) $review['recommendation'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
	<?php echo Renderer::text( $review ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
	<?php echo Renderer::reply( $review ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
</article>
