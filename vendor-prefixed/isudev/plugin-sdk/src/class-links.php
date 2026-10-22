<?php
/**
 * Product links from the platform.
 *
 * @package IsuDev\PluginSdk
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Vendor\IsuDev\PluginSdk;

defined( 'ABSPATH' ) || exit;

/**
 * Pricing, account, docs and download links of the product. The platform is the only source
 * (admin → Products), so a moved pricing page is fixed there and every installed plugin follows.
 * Read from the public catalog (no site token, so it also works for free sites that never
 * registered), cached a week; the plugin's fallbacks cover a site that never reached the API.
 */
final class Links {

	const TTL = WEEK_IN_SECONDS;

	/**
	 * After a failed refresh the cached links are kept and retried after this long.
	 */
	const RETRY = DAY_IN_SECONDS;

	const NAMES = array( 'pricing', 'buy', 'account', 'docs', 'download' );

	/**
	 * Constructor.
	 *
	 * @param Config $config SDK configuration.
	 */
	public function __construct( private readonly Config $config ) {}

	/**
	 * One link, '' when unknown.
	 *
	 * @param string $name pricing, buy, account, docs or download.
	 */
	public function get( string $name ): string {
		$links = $this->all();

		return (string) ( $links[ $name ] ?? '' );
	}

	/**
	 * All links: cached from the platform, refreshed when stale, falling back to Config::$links.
	 *
	 * @return array<string, string>
	 */
	public function all(): array {
		$state = (array) \get_option( $this->config->option( 'links' ), array() );

		if ( empty( $state['checked_at'] ) || ( \time() - (int) $state['checked_at'] ) > self::TTL ) {
			$state = $this->refresh( $state );
		}

		return \array_merge( $this->config->links, \array_filter( (array) ( $state['links'] ?? array() ) ) );
	}

	/**
	 * Stores links that came with another response (the license check carries them too).
	 *
	 * @param array<string, mixed> $links Links from the API.
	 */
	public function store( array $links ): void {
		\update_option(
			$this->config->option( 'links' ),
			array(
				'links'      => self::clean( $links ),
				'checked_at' => \time(),
			),
			false
		);
	}

	/**
	 * Fetches GET /v1/catalog?product=<slug>.
	 *
	 * @param array<string, mixed> $previous Cached state, kept on failure.
	 * @return array<string, mixed>
	 */
	private function refresh( array $previous ): array {
		$response = \wp_remote_get(
			$this->config->api_url() . '/v1/catalog?product=' . \rawurlencode( $this->config->product ),
			array(
				'timeout' => 5,
				'headers' => array( 'X-Isurev-Agent' => $this->config->agent ),
			)
		);
		$body     = \is_wp_error( $response ) ? null : \json_decode( (string) \wp_remote_retrieve_body( $response ), true );
		$links    = \is_array( $body ) ? ( $body['products'][0]['links'] ?? null ) : null;

		if ( ! \is_array( $links ) ) {
			$previous['checked_at'] = \time() - self::TTL + self::RETRY;
			\update_option( $this->config->option( 'links' ), $previous, false );

			return $previous;
		}

		$this->store( $links );

		return (array) \get_option( $this->config->option( 'links' ), array() );
	}

	/**
	 * Known names, http(s) URLs only.
	 *
	 * @param array<string, mixed> $links Raw links.
	 * @return array<string, string>
	 */
	private static function clean( array $links ): array {
		$clean = array();
		foreach ( self::NAMES as $name ) {
			$url = \esc_url_raw( (string) ( $links[ $name ] ?? '' ), array( 'http', 'https' ) );
			if ( '' !== $url ) {
				$clean[ $name ] = $url;
			}
		}

		return $clean;
	}
}
