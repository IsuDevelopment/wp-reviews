<?php
/**
 * Front-end markup for review lists.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the wrapper, summary and one card per review through the chosen template.
 * Card templates call the static helpers below; every helper returns escaped HTML.
 */
final class Renderer {

	/**
	 * Normalized block attributes.
	 *
	 * @param array<string, mixed> $attributes Raw attributes.
	 * @return array{source_ids: int[], template: string, layout: string, columns: int, limit: int, min_rating: int, text_lines: int, show_summary: bool, with_text: bool}
	 */
	public static function normalize( array $attributes ): array {
		return array(
			'source_ids'   => \array_values( \array_filter( \array_map( 'absint', (array) ( $attributes['sourceIds'] ?? array() ) ) ) ),
			'template'     => \sanitize_key( (string) ( $attributes['template'] ?? '' ) ),
			'layout'       => 'slider' === ( $attributes['layout'] ?? '' ) ? 'slider' : 'grid',
			'columns'      => \max( 1, \min( 6, (int) ( $attributes['columns'] ?? 3 ) ) ),
			'limit'        => \max( 1, \min( 100, (int) ( $attributes['limit'] ?? 9 ) ) ),
			'min_rating'   => \max( 0, \min( 5, (int) ( $attributes['minRating'] ?? 0 ) ) ),
			'text_lines'   => \max( 2, \min( 20, (int) ( $attributes['textLines'] ?? 5 ) ) ),
			'show_summary' => (bool) ( $attributes['showSummary'] ?? true ),
			'with_text'    => (bool) ( $attributes['withText'] ?? true ),
		);
	}

	/**
	 * Block output.
	 *
	 * @param array<string, mixed>                  $attributes Block attributes.
	 * @param string                                $wrapper    Output of get_block_wrapper_attributes() or ''.
	 * @param array<int, array<string, mixed>>|null $reviews    Override (admin preview with sample data).
	 * @param array<string, mixed>|null             $summary    Override for the summary.
	 * @param bool                                  $preview    Admin preview: render locked templates too.
	 */
	public static function render( array $attributes, string $wrapper = '', ?array $reviews = null, ?array $summary = null, bool $preview = false ): string {
		$args     = self::normalize( $attributes );
		$template = $preview && isset( Templates::all()[ $args['template'] ] )
			? Templates::all()[ $args['template'] ]
			: Templates::resolve( $args['template'] );

		if ( null === $reviews ) {
			$reviews = ( new Review_Repository() )->query(
				array(
					'source_ids' => $args['source_ids'],
					'limit'      => $args['limit'],
					'min_rating' => $args['min_rating'],
					'with_text'  => $args['with_text'],
				)
			);
		}

		if ( ! $reviews ) {
			return '';
		}

		\wp_enqueue_style( Templates::style_handle( $template['slug'] ) );

		$cards = array();
		foreach ( $reviews as $review ) {
			$cards[] = self::card( $template, $review, $args );
		}

		$is_slider = 'slider' === $args['layout'];
		$classes   = \sprintf( 'isurev isurev--tpl-%s isurev--%s', \sanitize_html_class( $template['slug'] ), $is_slider ? 'slider' : 'grid' );
		$style     = \sprintf( '--isurev-columns:%d;--isurev-lines:%d', $args['columns'], $args['text_lines'] );

		if ( '' === $wrapper ) {
			$wrapper = \sprintf( 'class="%s" style="%s"', \esc_attr( $classes ), \esc_attr( $style ) );
		} else {
			// Merge our class and custom properties into the block wrapper attributes.
			$wrapper = \preg_replace( '/class="/', 'class="' . \esc_attr( $classes ) . ' ', $wrapper, 1 );
			$wrapper = \str_contains( (string) $wrapper, 'style="' )
				? \preg_replace( '/style="/', 'style="' . \esc_attr( $style ) . ';', (string) $wrapper, 1 )
				: $wrapper . ' style="' . \esc_attr( $style ) . '"';
		}

		$list = '<ul class="isurev__list" role="list"><li class="isurev__item">' . \implode( '</li><li class="isurev__item">', $cards ) . '</li></ul>';

		if ( $is_slider ) {
			$list = \sprintf(
				'<div class="isurev__viewport" tabindex="0" role="region" aria-roledescription="%1$s" aria-label="%2$s">%3$s</div>
				<div class="isurev__controls">
					<button type="button" class="isurev__nav isurev__nav--prev" data-isurev-nav="-1" aria-label="%4$s">‹</button>
					<button type="button" class="isurev__nav isurev__nav--next" data-isurev-nav="1" aria-label="%5$s">›</button>
				</div>',
				\esc_attr__( 'carousel', 'isudev-reviews' ),
				\esc_attr__( 'Customer reviews', 'isudev-reviews' ),
				$list,
				\esc_attr__( 'Previous reviews', 'isudev-reviews' ),
				\esc_attr__( 'Next reviews', 'isudev-reviews' )
			);
		}

		$header = $args['show_summary'] ? self::summary( $summary ?? self::summary_data( $args['source_ids'] ) ) : '';

		// Every part is escaped where it is built.
		return '<section ' . $wrapper . '>' . $header . $list . '</section>';
	}

	/**
	 * One card through the template's card.php.
	 *
	 * @param array<string, mixed> $template Template data.
	 * @param array<string, mixed> $review   Review row.
	 * @param array<string, mixed> $args     Normalized attributes.
	 */
	private static function card( array $template, array $review, array $args ): string {
		\ob_start();
		// $review and $args are the card template's variables.
		( static function ( string $file, array $review, array $args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			include $file;
		} )( $template['dir'] . '/card.php', $review, $args );

		return (string) \ob_get_clean();
	}

	/**
	 * Average rating and count across sources, weighted by each source's total.
	 *
	 * @param int[] $source_ids Empty = all.
	 * @return array{rating: float, count: int, platforms: string[]}
	 */
	public static function summary_data( array $source_ids ): array {
		$sum       = 0.0;
		$count     = 0;
		$platforms = array();

		foreach ( ( new Source_Repository() )->all() as $source ) {
			if ( $source_ids && ! \in_array( (int) $source['id'], $source_ids, true ) ) {
				continue;
			}
			$platforms[] = (string) $source['platform'];
			if ( $source['rating_avg'] && $source['rating_count'] ) {
				$sum   += (float) $source['rating_avg'] * (int) $source['rating_count'];
				$count += (int) $source['rating_count'];
			}
		}

		return array(
			'rating'    => $count ? \round( $sum / $count, 1 ) : 0.0,
			'count'     => $count,
			'platforms' => \array_values( \array_unique( $platforms ) ),
		);
	}

	/**
	 * Summary header: platforms, average, stars, total.
	 *
	 * @param array<string, mixed> $data From summary_data().
	 */
	private static function summary( array $data ): string {
		if ( empty( $data['count'] ) ) {
			return '';
		}

		$icons = \implode( '', \array_map( array( self::class, 'platform_icon' ), (array) $data['platforms'] ) );

		return \sprintf(
			'<header class="isurev__summary"><span class="isurev__summary-icons">%1$s</span><strong class="isurev__summary-score">%2$s</strong>%3$s<span class="isurev__summary-count">%4$s</span></header>',
			$icons,
			\esc_html( \number_format_i18n( (float) $data['rating'], 1 ) ),
			self::stars( (float) $data['rating'] ),
			/* translators: %s: number of reviews. */
			\esc_html( \sprintf( \_n( 'Based on %s review', 'Based on %s reviews', (int) $data['count'], 'isudev-reviews' ), \number_format_i18n( (int) $data['count'] ) ) )
		);
	}

	/**
	 * Star rating. Facebook recommendations (no rating) render as a "recommends" badge.
	 *
	 * @param float|int|null $rating 0–5.
	 * @param string         $recommendation positive|negative|''.
	 */
	public static function stars( $rating, string $recommendation = '' ): string {
		if ( null === $rating || '' === $rating ) {
			if ( '' === $recommendation ) {
				return '';
			}
			return \sprintf(
				'<span class="isurev-recommends isurev-recommends--%1$s">%2$s</span>',
				\esc_attr( $recommendation ),
				'positive' === $recommendation ? \esc_html__( 'Recommends', 'isudev-reviews' ) : \esc_html__( 'Doesn\'t recommend', 'isudev-reviews' )
			);
		}

		$rating = \max( 0, \min( 5, (float) $rating ) );

		return \sprintf(
			'<span class="isurev-stars" role="img" aria-label="%1$s" style="--isurev-rating:%2$s"><span aria-hidden="true">★★★★★</span></span>',
			/* translators: %s: rating, e.g. 4.5. */
			\esc_attr( \sprintf( \__( 'Rated %s out of 5', 'isudev-reviews' ), \number_format_i18n( $rating, 1 ) ) ),
			\esc_attr( (string) $rating )
		);
	}

	/**
	 * Avatar image, or the author's initial when there is none.
	 *
	 * @param array<string, mixed> $review Review row.
	 */
	public static function avatar( array $review ): string {
		$name = (string) $review['author_name'];

		if ( '' !== (string) $review['author_avatar'] ) {
			// no-referrer: Google avatar CDN refuses some referrers.
			return \sprintf(
				'<img class="isurev-avatar" src="%s" alt="" width="48" height="48" loading="lazy" decoding="async" referrerpolicy="no-referrer">',
				\esc_url( (string) $review['author_avatar'] )
			);
		}

		return \sprintf(
			'<span class="isurev-avatar isurev-avatar--initial" aria-hidden="true" style="--isurev-hue:%d">%s</span>',
			\absint( \crc32( $name ) ) % 360,
			\esc_html( \mb_strtoupper( \mb_substr( $name, 0, 1 ) ) )
		);
	}

	/**
	 * Relative date ("3 months ago") with the full date in <time>.
	 *
	 * @param array<string, mixed> $review Review row.
	 */
	public static function date( array $review ): string {
		$timestamp = (int) \strtotime( $review['published_at'] . ' UTC' );

		return \sprintf(
			'<time class="isurev-date" datetime="%1$s" title="%2$s">%3$s</time>',
			\esc_attr( \gmdate( 'c', $timestamp ) ),
			\esc_attr( \wp_date( (string) \get_option( 'date_format' ), $timestamp ) ),
			/* translators: %s: human time difference, e.g. "3 months". */
			\esc_html( \sprintf( \__( '%s ago', 'isudev-reviews' ), \human_time_diff( $timestamp ) ) )
		);
	}

	/**
	 * Review text clamped by CSS to --isurev-lines, with a toggle the view script reveals when it overflows.
	 *
	 * @param array<string, mixed> $review Review row.
	 */
	public static function text( array $review ): string {
		$content = \trim( (string) $review['content'] );
		if ( '' === $content ) {
			return '';
		}

		$id = 'isurev-text-' . (int) ( $review['id'] ?? \wp_rand() );

		return \sprintf(
			'<div class="isurev-text" id="%1$s">%2$s</div><button type="button" class="isurev-more" aria-expanded="false" aria-controls="%1$s" data-label-less="%3$s" hidden>%4$s</button>',
			\esc_attr( $id ),
			\wp_kses_post( \wpautop( $content ) ),
			\esc_attr__( 'Show less', 'isudev-reviews' ),
			\esc_html__( 'Read more', 'isudev-reviews' )
		);
	}

	/**
	 * Owner reply.
	 *
	 * @param array<string, mixed> $review Review row.
	 */
	public static function reply( array $review ): string {
		if ( '' === \trim( (string) $review['reply_content'] ) ) {
			return '';
		}

		return \sprintf(
			'<div class="isurev-reply"><strong>%1$s</strong>%2$s</div>',
			\esc_html__( 'Response from the owner', 'isudev-reviews' ),
			\wp_kses_post( \wpautop( (string) $review['reply_content'] ) )
		);
	}

	/**
	 * Platform logo.
	 *
	 * @param string $platform google|facebook.
	 */
	public static function platform_icon( string $platform ): string {
		$icons = array(
			'google'   => '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.6 5.6 0 0 1-2.4 3.6v3h3.9c2.2-2.1 3.5-5.1 3.5-8.8z"/><path fill="#34A853" d="M12 24c3.2 0 6-1.1 8-2.9l-3.9-3c-1.1.7-2.5 1.2-4.1 1.2-3.1 0-5.8-2.1-6.7-5H1.3v3.1A12 12 0 0 0 12 24z"/><path fill="#FBBC05" d="M5.3 14.3a7.2 7.2 0 0 1 0-4.6V6.6h-4a12 12 0 0 0 0 10.8l4-3.1z"/><path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A12 12 0 0 0 1.3 6.6l4 3.1c.9-2.9 3.6-4.9 6.7-4.9z"/></svg>',
			'facebook' => '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="#1877F2" d="M24 12a12 12 0 1 0-13.9 11.9v-8.4H7.1V12h3V9.4c0-3 1.8-4.7 4.5-4.7 1.3 0 2.7.2 2.7.2v3h-1.5c-1.5 0-2 .9-2 1.9V12h3.4l-.5 3.5h-2.9v8.4A12 12 0 0 0 24 12z"/></svg>',
		);

		$label = 'facebook' === $platform ? 'Facebook' : 'Google';

		return \sprintf( '<span class="isurev-platform isurev-platform--%1$s" title="%2$s">%3$s<span class="screen-reader-text">%2$s</span></span>', \esc_attr( $platform ), \esc_attr( $label ), $icons[ $platform ] ?? '' );
	}
}
