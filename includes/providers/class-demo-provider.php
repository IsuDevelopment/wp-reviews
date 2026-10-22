<?php
/**
 * Demo provider.
 *
 * @package IsuDev\Reviews
 */

namespace IsuDev\Reviews\Providers;

use IsuDev\Reviews\Source_Repository;

use const IsuDev\Reviews\DIR;

defined( 'ABSPATH' ) || exit;

/**
 * Sample reviews from assets/demo/reviews.json, no API and no license. For local sites
 * (which cannot register with the cloud API) and for trying templates and layouts.
 * Always returns all sample reviews: the free limit belongs to the cloud API.
 */
final class Demo_Provider implements Provider {

	const FILE        = '/assets/demo/reviews.json';
	const EXTERNAL_ID = 'isurev-demo';

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'demo';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return \__( 'Demo data: 30 sample reviews, no API', 'isudev-reviews' );
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
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $source Source row.
	 * @param array<string, mixed> $args   Ignored.
	 */
	public function fetch( array $source, array $args ) {
		$data = \json_decode( (string) \file_get_contents( DIR . self::FILE ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		if ( ! \is_array( $data ) ) {
			return new \WP_Error( 'isurev_demo_missing', \__( 'Demo data file is missing.', 'isudev-reviews' ) );
		}

		$facebook = 'facebook' === $source['platform'];
		$reviews  = array();
		foreach ( (array) ( $data['reviews'] ?? array() ) as $review ) {
			$reviews[] = array(
				'id'             => (string) $review['id'],
				'author'         => array(
					'name'   => (string) $review['author']['name'],
					'url'    => '',
					'avatar' => '',
				),
				// Facebook has recommendations instead of stars.
				'rating'         => $facebook ? null : (int) $review['rating'],
				'recommendation' => $facebook ? ( (int) $review['rating'] >= 3 ? 'positive' : 'negative' ) : null,
				'text'           => (string) $review['text'],
				'language'       => (string) $review['language'],
				'published_at'   => self::date( (int) $review['days_ago'] ),
				'url'            => '',
				'reply'          => isset( $review['reply'] ) ? array(
					'text'         => (string) $review['reply']['text'],
					'published_at' => self::date( (int) $review['reply']['days_ago'] ),
				) : null,
			);
		}

		return new Fetch_Result( $reviews, (array) ( $data['source'] ?? array() ) );
	}

	/**
	 * The demo source (Google), created on first use. Allowed next to a real source on the free plan.
	 *
	 * @return int|\WP_Error Source ID.
	 */
	public static function ensure_source() {
		$sources = new Source_Repository();
		foreach ( $sources->all() as $source ) {
			if ( self::EXTERNAL_ID === $source['external_id'] ) {
				return (int) $source['id'];
			}
		}

		return $sources->create(
			array(
				'platform'      => 'google',
				'provider'      => 'demo',
				'external_id'   => self::EXTERNAL_ID,
				'name'          => '',
				'sync_interval' => 'off',
			)
		);
	}

	/**
	 * ISO date N days ago at 10:00 UTC (upserts keep the date of the first import).
	 *
	 * @param int $days_ago Days before today.
	 */
	private static function date( int $days_ago ): string {
		return \gmdate( 'Y-m-d\T10:00:00\Z', \strtotime( "-{$days_ago} days" ) );
	}
}
