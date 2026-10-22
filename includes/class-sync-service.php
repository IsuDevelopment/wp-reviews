<?php
/**
 * Pulls reviews from a provider into the local tables.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

use IsuDev\Reviews\Providers\Provider_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Provider → upsert → source meta and status.
 */
final class Sync_Service {

	// Pro first import; later runs are incremental through `since`.
	const PRO_LIMIT = 1000;

	/**
	 * Constructor.
	 *
	 * @param Source_Repository $sources   Sources.
	 * @param Review_Repository $reviews   Reviews.
	 * @param Provider_Registry $providers Providers.
	 */
	public function __construct(
		private Source_Repository $sources,
		private Review_Repository $reviews,
		private Provider_Registry $providers
	) {}

	/**
	 * Syncs one source.
	 *
	 * @param int $source_id Source ID.
	 * @return array{new: int, fetched: int}|\WP_Error
	 */
	public function sync( int $source_id ) {
		$source = $this->sources->find( $source_id );
		if ( ! $source ) {
			return new \WP_Error( 'isurev_no_source', \__( 'Source not found.', 'isudev-reviews' ) );
		}

		$provider = $this->providers->get( (string) $source['provider'] );
		if ( ! $provider ) {
			return $this->fail( $source_id, new \WP_Error( 'isurev_no_provider', \__( 'Unknown provider.', 'isudev-reviews' ) ) );
		}

		$available = $provider->is_available( (string) $source['platform'] );
		if ( \is_wp_error( $available ) ) {
			return $this->fail( $source_id, $available );
		}

		$is_unlimited = Plugin::license()->has( 'unlimited' );
		$result       = $provider->fetch(
			$source,
			array(
				'limit'    => $is_unlimited ? self::PRO_LIMIT : Providers\Cloud_Provider::FREE_LIMIT,
				// Incremental only after a full Pro import; free re-fetches the newest batch, and the first Pro run backfills history.
				'since'    => $is_unlimited && ! empty( $source['backfilled_at'] ) ? \gmdate( 'c', (int) \strtotime( $source['last_synced_at'] . ' UTC' ) ) : '',
				'language' => Settings::get( 'language' ),
			)
		);

		if ( \is_wp_error( $result ) ) {
			return $this->fail( $source_id, $result );
		}

		$new = $this->reviews->upsert_many( $source_id, (string) $source['platform'], $result->reviews );

		$meta = array(
			'last_synced_at' => \current_time( 'mysql', true ),
			'last_status'    => 'ok',
			'last_error'     => '',
		);
		if ( $is_unlimited && empty( $source['backfilled_at'] ) ) {
			$meta['backfilled_at'] = $meta['last_synced_at'];
		}
		if ( ! empty( $result->source['name'] ) && '' === (string) $source['name'] ) {
			$meta['name'] = \sanitize_text_field( (string) $result->source['name'] );
		}
		if ( ! empty( $result->source['url'] ) ) {
			$meta['url'] = \esc_url_raw( (string) $result->source['url'] );
		}
		if ( ! empty( $result->source['rating'] ) ) {
			$meta['rating_avg'] = \round( (float) $result->source['rating'], 2 );
		}
		if ( ! empty( $result->source['total'] ) ) {
			$meta['rating_count'] = (int) $result->source['total'];
		}
		$this->sources->update( $source_id, $meta );

		/**
		 * Fires after a successful sync.
		 *
		 * @param int $source_id Source ID.
		 * @param int $new       New reviews.
		 */
		\do_action( 'isurev_synced', $source_id, $new );

		return array(
			'new'     => $new,
			'fetched' => \count( $result->reviews ),
		);
	}

	/**
	 * Stores the error on the source and passes it on.
	 *
	 * @param int       $source_id Source ID.
	 * @param \WP_Error $error     Error.
	 */
	private function fail( int $source_id, \WP_Error $error ): \WP_Error {
		$this->sources->update(
			$source_id,
			array(
				'last_status' => 'error',
				'last_error'  => $error->get_error_message(),
			)
		);

		return $error;
	}
}
