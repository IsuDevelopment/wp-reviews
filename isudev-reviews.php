<?php
/**
 * Plugin Name:       IsuDev Reviews
 * Plugin URI:        https://isudev.com/plugins/reviews/
 * Description:       Google and Facebook reviews stored locally and shown with Gutenberg blocks. Free quick import, Pro auto-sync and templates.
 * Version:           0.2.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            IsuDev
 * Author URI:        https://isudev.com
 * License:           GPL-2.0-or-later
 * Text Domain:       isudev-reviews
 * Domain Path:       /languages
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.2.0';
const FILE    = __FILE__;
const DIR     = __DIR__;

// Cloud API base; override per environment with the ISUREV_API_URL constant.
if ( ! \defined( 'ISUREV_API_URL' ) ) {
	\define( 'ISUREV_API_URL', 'https://papi.isudev.com' );
}

// Shared isuDev plugin SDK, namespace-prefixed by Strauss (composer install).
require_once DIR . '/vendor-prefixed/autoload.php';

/*
 * Autoloader: IsuDev\Reviews\Providers\Cloud_Provider → includes/providers/class-cloud-provider.php.
 * Interfaces use the interface- prefix (WordPress file naming).
 */
\spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! \str_starts_with( $class_name, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$parts = \explode( '\\', \substr( $class_name, \strlen( __NAMESPACE__ ) + 1 ) );
		$name  = \strtolower( \str_replace( '_', '-', (string) \array_pop( $parts ) ) );
		$path  = DIR . '/includes/' . ( $parts ? \strtolower( \implode( '/', $parts ) ) . '/' : '' );

		foreach ( array( 'class-', 'interface-' ) as $prefix ) {
			if ( \is_readable( $path . $prefix . $name . '.php' ) ) {
				require_once $path . $prefix . $name . '.php';
				return;
			}
		}
	}
);

\register_activation_hook( FILE, array( Plugin::class, 'activate' ) );
\register_deactivation_hook( FILE, array( Plugin::class, 'deactivate' ) );

\add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
