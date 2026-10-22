<?php
/**
 * HTTP client for the isuDev cloud API.
 *
 * @package IsuDev\PluginSdk
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Vendor\IsuDev\PluginSdk;

defined( 'ABSPATH' ) || exit;

/**
 * JSON requests with site token + license headers. Registers the site on first use
 * and once more when the API forgets the token (invalid_site_token).
 */
final class Api_Client {

	/**
	 * Site registration that owns the token.
	 *
	 * @var Site_Registration
	 */
	private Site_Registration $sites;

	/**
	 * Constructor.
	 *
	 * @param Config $config SDK configuration.
	 */
	public function __construct( private readonly Config $config ) {
		$this->sites = new Site_Registration( $config, $this );
	}

	/**
	 * Site registration.
	 */
	public function sites(): Site_Registration {
		return $this->sites;
	}

	/**
	 * Sends a JSON request.
	 *
	 * @param string               $method GET|POST.
	 * @param string               $path   e.g. /reviews/v1/fetch.
	 * @param array<string, mixed> $body   JSON body for POST.
	 * @return array<string, mixed>|\WP_Error Decoded JSON.
	 */
	public function request( string $method, string $path, array $body = array() ) {
		$token = $this->sites->token();
		if ( \is_wp_error( $token ) ) {
			return $token;
		}

		$response = $this->send( $method, $path, $body, $token );

		if ( \is_wp_error( $response ) && $this->config->prefix . '_api_invalid_site_token' === $response->get_error_code() ) {
			$this->sites->forget();
			$token = $this->sites->token();
			if ( \is_wp_error( $token ) ) {
				return $token;
			}
			$response = $this->send( $method, $path, $body, $token );
		}

		return $response;
	}

	/**
	 * One HTTP round trip.
	 *
	 * @param string               $method     GET|POST.
	 * @param string               $path       Path.
	 * @param array<string, mixed> $body       JSON body.
	 * @param string               $site_token Site token ('' for registration).
	 * @return array<string, mixed>|\WP_Error
	 */
	public function send( string $method, string $path, array $body = array(), string $site_token = '' ) {
		// Header names are platform-wide, the same for every product.
		$headers = array(
			'Accept'         => 'application/json',
			'Content-Type'   => 'application/json',
			'X-Isurev-Site'  => \home_url( '/' ),
			'X-Isurev-Agent' => $this->config->agent,
		);

		if ( '' !== $site_token ) {
			$headers['X-Isurev-Site-Token'] = $site_token;
		}

		$license = $this->config->license_key();
		if ( '' !== $license ) {
			$headers['Authorization'] = 'Bearer ' . $license;
		}

		$args = array(
			'method'  => $method,
			'timeout' => 45,
			'headers' => $headers,
		);
		if ( 'GET' !== $method ) {
			$args['body'] = \wp_json_encode( $body );
		}

		$response = \wp_remote_request( $this->config->api_url() . $path, $args );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );
		$json = \json_decode( \wp_remote_retrieve_body( $response ), true );

		if ( ! \is_array( $json ) ) {
			/* translators: %d: HTTP status code. */
			return new \WP_Error( $this->config->prefix . '_api_invalid', \sprintf( \__( 'Unexpected response from the isuDev API (HTTP %d).', 'isudev-plugin-sdk' ), $code ) );
		}

		if ( $code >= 400 || isset( $json['error'] ) ) {
			return new \WP_Error(
				$this->config->prefix . '_api_' . \sanitize_key( (string) ( $json['error']['code'] ?? $code ) ),
				(string) ( $json['error']['message'] ?? \__( 'The isuDev API returned an error.', 'isudev-plugin-sdk' ) ),
				array( 'status' => $code )
			);
		}

		return $json;
	}
}
