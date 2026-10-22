<?php
/**
 * SDK entry point.
 *
 * @package IsuDev\PluginSdk
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Vendor\IsuDev\PluginSdk;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the client, registration and license for one plugin and hooks the
 * site-verify REST route and the shared WP-CLI subcommands.
 */
final class Sdk {

	/**
	 * API client.
	 *
	 * @var Api_Client
	 */
	private Api_Client $api;

	/**
	 * License state.
	 *
	 * @var License
	 */
	private License $license;

	/**
	 * Product links.
	 *
	 * @var Links
	 */
	private Links $links;

	/**
	 * Constructor.
	 *
	 * @param Config $config SDK configuration.
	 */
	public function __construct( private readonly Config $config ) {
		$this->api     = new Api_Client( $config );
		$this->links   = new Links( $config );
		$this->license = new License( $config, $this->api, $this->links );
	}

	/**
	 * Hooks the site-verify route and, under WP-CLI, the register/license subcommands.
	 * Call on plugins_loaded, after the plugin registered its own WP-CLI command.
	 */
	public function register(): void {
		\add_action( 'rest_api_init', array( $this, 'routes' ) );

		if ( \defined( 'WP_CLI' ) && \WP_CLI ) {
			( new Cli_Command( $this ) )->register();
		}
	}

	/**
	 * GET isudev-<product>/v1/site-verify, called by the API while registering this site.
	 */
	public function routes(): void {
		\register_rest_route(
			$this->config->rest_namespace(),
			'/site-verify',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this->sites(), 'verify' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'nonce' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Deletes the SDK's options. Call from the plugin's uninstall.
	 */
	public function uninstall(): void {
		\delete_option( $this->config->option( 'site_token' ) );
		\delete_option( $this->config->option( 'license' ) );
		\delete_option( $this->config->option( 'links' ) );
		\delete_transient( $this->config->option( 'verify_nonce' ) );
	}

	/**
	 * Product links (pricing page, account, docs, download) from the platform.
	 */
	public function links(): Links {
		return $this->links;
	}

	/**
	 * Configuration.
	 */
	public function config(): Config {
		return $this->config;
	}

	/**
	 * API client.
	 */
	public function api(): Api_Client {
		return $this->api;
	}

	/**
	 * Site registration.
	 */
	public function sites(): Site_Registration {
		return $this->api->sites();
	}

	/**
	 * License state.
	 */
	public function license(): License {
		return $this->license;
	}
}
