<?php
/**
 * Provider registry.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Lists providers. Add more (Trustpilot, Booking…) with the isurev_providers filter.
 */
final class Provider_Registry {

	/**
	 * Providers keyed by id.
	 *
	 * @var array<string, Provider>|null
	 */
	private ?array $providers = null;

	/**
	 * All providers.
	 *
	 * @return array<string, Provider>
	 */
	public function all(): array {
		if ( null === $this->providers ) {
			/**
			 * Filters the available review providers.
			 *
			 * @param Provider[] $providers Provider instances.
			 */
			$list = (array) \apply_filters( 'isurev_providers', array( new Cloud_Provider(), new Google_Places_Provider(), new Demo_Provider() ) );

			$this->providers = array();
			foreach ( $list as $provider ) {
				if ( $provider instanceof Provider ) {
					$this->providers[ $provider->id() ] = $provider;
				}
			}
		}

		return $this->providers;
	}

	/**
	 * One provider.
	 *
	 * @param string $id Provider id.
	 */
	public function get( string $id ): ?Provider {
		return $this->all()[ $id ] ?? null;
	}
}
