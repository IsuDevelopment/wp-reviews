<?php
/**
 * License state from the cloud API.
 *
 * @package IsuDev\PluginSdk
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Vendor\IsuDev\PluginSdk;

defined( 'ABSPATH' ) || exit;

/**
 * Tier and feature flags. The API enforces limits; this only shapes the UI and cron.
 */
final class License {

	const TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * After a failed refresh a known-good Pro state is kept and retried after this long.
	 */
	const GRACE_RETRY = HOUR_IN_SECONDS;

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
	 * Cached license state, refreshed when stale.
	 *
	 * @return array{tier: string, status: string, features: string[], expires_at: string, checked_at: int, message: string}
	 */
	public function state(): array {
		$state = (array) \get_option( $this->config->option( 'license' ), array() );

		if ( empty( $state['checked_at'] ) || ( \time() - (int) $state['checked_at'] ) > self::TTL ) {
			$state = $this->refresh();
		}

		return \array_merge( $this->free_state(), $state );
	}

	/**
	 * Free tier, also used when no key is set or the API is unreachable.
	 *
	 * @return array{tier: string, status: string, features: string[], expires_at: string, checked_at: int, message: string}
	 */
	private function free_state(): array {
		return array(
			'tier'       => 'free',
			'status'     => 'none',
			'features'   => array(),
			'expires_at' => '',
			'checked_at' => \time(),
			'message'    => '',
		);
	}

	/**
	 * Asks the API for the current license state and stores it.
	 *
	 * @return array<string, mixed>
	 */
	public function refresh(): array {
		$state  = $this->free_state();
		$option = $this->config->option( 'license' );

		if ( '' !== $this->config->license_key() ) {
			$response = $this->api->request( 'GET', '/v1/licenses/check?product=' . \rawurlencode( $this->config->product ) );

			if ( \is_wp_error( $response ) ) {
				// Keep a known-good Pro state through short API outages instead of downgrading the site.
				$previous = (array) \get_option( $option, array() );
				if ( 'pro' === ( $previous['tier'] ?? '' ) && 'active' === ( $previous['status'] ?? '' ) ) {
					$previous['checked_at'] = \time() - self::TTL + self::GRACE_RETRY;
					\update_option( $option, $previous, false );
					return $previous;
				}
				$state['status']  = 'error';
				$state['message'] = $response->get_error_message();
			} else {
				$state['tier']       = 'pro' === ( $response['tier'] ?? '' ) ? 'pro' : 'free';
				$state['status']     = \sanitize_key( (string) ( $response['status'] ?? 'invalid' ) );
				$state['features']   = \array_values( \array_intersect( $this->config->features, (array) ( $response['features'] ?? array() ) ) );
				$state['expires_at'] = \sanitize_text_field( (string) ( $response['expires_at'] ?? '' ) );
			}
		}

		\update_option( $option, $state, false );

		return $state;
	}

	/**
	 * Whether the active license includes a feature.
	 *
	 * @param string $feature One of Config::$features.
	 */
	public function has( string $feature ): bool {
		$state = $this->state();
		$has   = 'active' === $state['status'] && \in_array( $feature, $state['features'], true );

		/**
		 * Filters feature availability (e.g. for agency bundles or local testing).
		 * The hook name is "{$prefix}_has_feature", e.g. isurev_has_feature.
		 *
		 * @param bool   $has     Whether the feature is available.
		 * @param string $feature Feature slug.
		 */
		return (bool) \apply_filters( $this->config->prefix . '_has_feature', $has, $feature ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Prefixed per plugin by Config.
	}

	/**
	 * Pro tier with an active license.
	 */
	public function is_pro(): bool {
		$state = $this->state();
		return 'pro' === $state['tier'] && 'active' === $state['status'];
	}
}
