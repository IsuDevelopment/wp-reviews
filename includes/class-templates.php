<?php
/**
 * Review templates registry.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

/**
 * A template is a folder with template.json, card.php and style.css.
 * Themes or add-ons can register more folders through the isurev_template_dirs filter.
 */
final class Templates {

	const FALLBACK = 'default';

	/**
	 * Cache for the request.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $templates = null;

	/**
	 * All templates keyed by slug.
	 *
	 * @return array<string, array{slug: string, title: string, description: string, tier: string, layouts: string[], dir: string, url: string, version: string}>
	 */
	public static function all(): array {
		if ( null !== self::$templates ) {
			return self::$templates;
		}

		/**
		 * Filters folders scanned for templates. Each subfolder with a template.json is one template.
		 *
		 * @param array<string, string> $dirs Absolute path => URL.
		 */
		$dirs = (array) \apply_filters( 'isurev_template_dirs', array( DIR . '/templates' => \plugins_url( 'templates', FILE ) ) );

		self::$templates = array();
		foreach ( $dirs as $dir => $url ) {
			foreach ( (array) \glob( \trailingslashit( (string) $dir ) . '*/template.json' ) as $json_file ) {
				$meta = \json_decode( (string) \file_get_contents( (string) $json_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
				if ( ! \is_array( $meta ) ) {
					continue;
				}

				$slug                     = \sanitize_key( \basename( \dirname( (string) $json_file ) ) );
				self::$templates[ $slug ] = array(
					'slug'        => $slug,
					'title'       => (string) ( $meta['title'] ?? $slug ),
					'description' => (string) ( $meta['description'] ?? '' ),
					'tier'        => 'pro' === ( $meta['tier'] ?? '' ) ? 'pro' : 'free',
					'layouts'     => (array) ( $meta['layouts'] ?? array( 'grid', 'slider' ) ),
					'dir'         => \dirname( (string) $json_file ),
					'url'         => \trailingslashit( (string) $url ) . $slug,
					'version'     => (string) ( $meta['version'] ?? VERSION ),
				);
			}
		}

		return self::$templates;
	}

	/**
	 * Pro template without a license.
	 *
	 * @param array<string, mixed> $template Template data.
	 */
	public static function is_locked( array $template ): bool {
		return 'pro' === $template['tier'] && ! Plugin::license()->has( 'pro_templates' );
	}

	/**
	 * The template to actually render: requested → site default → fallback, skipping locked ones.
	 *
	 * @param string $slug Requested slug; empty uses the site default.
	 * @return array<string, mixed>
	 */
	public static function resolve( string $slug ): array {
		$all = self::all();

		foreach ( array( $slug, Settings::get( 'default_template' ), self::FALLBACK ) as $candidate ) {
			if ( isset( $all[ $candidate ] ) && ! self::is_locked( $all[ $candidate ] ) ) {
				return $all[ $candidate ];
			}
		}

		return $all[ self::FALLBACK ];
	}

	/**
	 * Style handle of a template.
	 *
	 * @param string $slug Template slug.
	 */
	public static function style_handle( string $slug ): string {
		return 'isurev-template-' . $slug;
	}

	/**
	 * Registers one style per template. Rendering enqueues only the one in use.
	 */
	public static function register_styles(): void {
		foreach ( self::all() as $template ) {
			\wp_register_style( self::style_handle( $template['slug'] ), $template['url'] . '/style.css', array(), $template['version'] );
		}
	}

	/**
	 * Every template style, for places that preview all of them (editor iframe, Templates screen).
	 */
	public static function enqueue_all_styles(): void {
		foreach ( self::all() as $template ) {
			\wp_enqueue_style( self::style_handle( $template['slug'] ) );
		}
	}
}
