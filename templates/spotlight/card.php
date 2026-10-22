<?php
/**
 * Template "spotlight" (Pro): quote-first card.
 *
 * @package IsuDev\Reviews
 *
 * @var array<string, mixed> $review Review row.
 * @var array<string, mixed> $args   Normalized block attributes.
 */

use IsuDev\Reviews\Renderer;

defined( 'ABSPATH' ) || exit;
?>
<figure class="isurev-spot">
	<?php echo Renderer::stars( $review['rating'], (string) $review['recommendation'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
	<blockquote class="isurev-spot__quote">
		<?php echo Renderer::text( $review ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
	</blockquote>
	<figcaption class="isurev-spot__author">
		<?php echo Renderer::avatar( $review ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		<span>
			<strong><?php echo esc_html( (string) $review['author_name'] ); ?></strong>
			<?php echo Renderer::date( $review ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		</span>
		<?php echo Renderer::platform_icon( (string) $review['platform'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
	</figcaption>
</figure>
