<?php
/**
 * WP-CLI subcommands shared by every isuDev plugin.
 *
 * @package IsuDev\PluginSdk
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Vendor\IsuDev\PluginSdk;

defined( 'ABSPATH' ) || exit;

/**
 * `wp <prefix> register` and `wp <prefix> license`, added next to the plugin's own subcommands.
 */
final class Cli_Command {

	/**
	 * Constructor.
	 *
	 * @param Sdk $sdk SDK instance.
	 */
	public function __construct( private readonly Sdk $sdk ) {}

	/**
	 * Adds the subcommands under the plugin's parent command. Call after the plugin
	 * registered its own command, so the parent exists.
	 */
	public function register(): void {
		$parent = $this->sdk->config()->cli_command();

		\WP_CLI::add_command( $parent . ' register', array( $this, 'register_site' ) );
		\WP_CLI::add_command( $parent . ' license', array( $this, 'license' ) );
	}

	/**
	 * Registers this site with the cloud API (normally automatic on first use).
	 */
	public function register_site(): void {
		$sites = $this->sdk->sites();
		$sites->forget();
		$token = $sites->register();

		if ( \is_wp_error( $token ) ) {
			\WP_CLI::error( $token->get_error_message() );
		}

		\WP_CLI::success( 'Site registered with ' . $this->sdk->config()->api_url() . '.' );
	}

	/**
	 * Shows (and refreshes) the license state.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function license( array $args, array $assoc_args ): void {
		$state             = $this->sdk->license()->refresh() + array( 'api_url' => $this->sdk->config()->api_url() );
		$state['features'] = \implode( ', ', (array) $state['features'] );
		unset( $state['checked_at'] );

		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', array( $state ), \array_keys( $state ) );
	}
}
