<?php
/**
 * Block registration.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

/**
 * Registers isudev-reviews/reviews from build/ and the template styles.
 */
final class Block {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		\add_action( 'init', array( self::class, 'register_block' ) );

		// Editor canvas is an iframe: only enqueue_block_assets reaches it.
		\add_action(
			'enqueue_block_assets',
			static function (): void {
				if ( \is_admin() ) {
					Templates::enqueue_all_styles();
				}
			}
		);
	}

	/**
	 * Registers styles and the block.
	 */
	public static function register_block(): void {
		Templates::register_styles();

		$block_dir = DIR . '/build/blocks/reviews';
		if ( \is_readable( $block_dir . '/block.json' ) ) {
			\register_block_type( $block_dir );
		}
	}
}
