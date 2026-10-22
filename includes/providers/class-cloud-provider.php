<?php
/**
 * Cloud provider (isuDev Reviews API).
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Providers;

use IsuDev\Reviews\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Fetches through the isuDev Reviews API. Free tier: Google, 18 newest (server-side limit; the API caches a place for 72 h). Pro: full history, Facebook.
 */
final class Cloud_Provider implements Provider {

	const FREE_LIMIT = 18;

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'cloud';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return Plugin::license()->is_pro()
			? \__( 'isuDev Cloud (Pro)', 'isudev-reviews' )
			/* translators: %d: review limit. */
			: \sprintf( \__( 'isuDev Cloud: quick import, up to %d reviews', 'isudev-reviews' ), self::FREE_LIMIT );
	}

	/**
	 * {@inheritDoc}
	 */
	public function platforms(): array {
		return array( 'google', 'facebook' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $platform Platform slug.
	 */
	public function is_available( string $platform ) {
		if ( 'facebook' === $platform && ! Plugin::license()->has( 'facebook' ) ) {
			return new \WP_Error( 'isurev_pro_required', \__( 'Facebook reviews need a Pro license.', 'isudev-reviews' ) );
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $source Source row.
	 * @param array<string, mixed> $args   limit, since, language.
	 */
	public function fetch( array $source, array $args ) {
		$limit = (int) ( $args['limit'] ?? self::FREE_LIMIT );
		if ( ! Plugin::license()->has( 'unlimited' ) ) {
			$limit = \min( $limit, self::FREE_LIMIT );
		}

		$response = Plugin::cloud()->api()->request(
			'POST',
			'/reviews/v1/fetch',
			array(
				'platform'    => (string) $source['platform'],
				'external_id' => (string) $source['external_id'],
				'limit'       => $limit,
				'since'       => (string) ( $args['since'] ?? '' ),
				'language'    => (string) ( $args['language'] ?? '' ),
			)
		);

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		return new Fetch_Result( (array) ( $response['reviews'] ?? array() ), (array) ( $response['source'] ?? array() ) );
	}
}
