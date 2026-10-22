<?php
/**
 * Site registration with the cloud API.
 *
 * @package IsuDev\PluginSdk
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Vendor\IsuDev\PluginSdk;

defined( 'ABSPATH' ) || exit;

/**
 * Every cloud call (free too) carries a site token. To get one the plugin stores a one-time
 * nonce, asks the API to register home_url(), and the API calls back verify() to prove that
 * this install answers for the domain.
 */
final class Site_Registration {

	/**
	 * Constructor.
	 *
	 * @param Config     $config SDK configuration.
	 * @param Api_Client $api    API client.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly Api_Client $api,
	) {}

	/**
	 * Stored token, registering first when there is none.
	 *
	 * @return string|\WP_Error
	 */
	public function token() {
		$token = (string) \get_option( $this->config->option( 'site_token' ), '' );

		return '' !== $token ? $token : $this->register();
	}

	/**
	 * Drops the token (API URL changed, or the API no longer knows it).
	 */
	public function forget(): void {
		\delete_option( $this->config->option( 'site_token' ) );
	}

	/**
	 * Registers this site.
	 *
	 * @return string|\WP_Error Site token.
	 */
	public function register() {
		$nonce = \wp_generate_password( 40, false );
		\set_transient( $this->config->option( 'verify_nonce' ), \wp_hash( $nonce ), 2 * MINUTE_IN_SECONDS );

		$response = $this->api->send(
			'POST',
			'/v1/sites/register',
			array(
				'site_url'     => \home_url( '/' ),
				'product'      => $this->config->product,
				'verify_nonce' => $nonce,
			)
		);

		\delete_transient( $this->config->option( 'verify_nonce' ) );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$token = \sanitize_text_field( (string) ( $response['site_token'] ?? '' ) );
		if ( '' === $token ) {
			return new \WP_Error( $this->config->prefix . '_register_failed', \__( 'The isuDev API did not return a site token.', 'isudev-plugin-sdk' ) );
		}

		\update_option( $this->config->option( 'site_token' ), $token, false );

		return $token;
	}

	/**
	 * REST callback for the API: confirms the nonce we are waiting for. Public by design;
	 * it only answers yes/no for a short-lived random value and reveals nothing else.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function verify( \WP_REST_Request $request ): \WP_REST_Response {
		$expected = (string) \get_transient( $this->config->option( 'verify_nonce' ) );
		$given    = (string) $request->get_param( 'nonce' );
		$ok       = '' !== $expected && '' !== $given && \hash_equals( $expected, \wp_hash( $given ) );

		return new \WP_REST_Response( array( 'ok' => $ok ), $ok ? 200 : 403 );
	}
}
