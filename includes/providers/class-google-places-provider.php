<?php
/**
 * Google Places API (New) provider.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Providers;

use IsuDev\Reviews\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Direct call with the site owner's own API key. Google returns at most 5 reviews per place.
 *
 * @link https://developers.google.com/maps/documentation/places/web-service/place-details
 */
final class Google_Places_Provider implements Provider {

	const ENDPOINT   = 'https://places.googleapis.com/v1/places/';
	const FIELD_MASK = 'id,displayName,rating,userRatingCount,googleMapsUri,reviews';

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'google_places';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return \__( 'Google Places API: your key, 5 newest reviews', 'isudev-reviews' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function platforms(): array {
		return array( 'google' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $platform Platform slug.
	 */
	public function is_available( string $platform ) {
		if ( '' === Settings::get( 'google_api_key' ) ) {
			return new \WP_Error( 'isurev_no_google_key', \__( 'Add a Google API key in Settings first.', 'isudev-reviews' ) );
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $source Source row.
	 * @param array<string, mixed> $args   language.
	 */
	public function fetch( array $source, array $args ) {
		$url = \add_query_arg(
			array_filter( array( 'languageCode' => (string) ( $args['language'] ?? '' ) ) ),
			self::ENDPOINT . \rawurlencode( (string) $source['external_id'] )
		);

		$response = \wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'X-Goog-Api-Key'   => Settings::get( 'google_api_key' ),
					'X-Goog-FieldMask' => self::FIELD_MASK,
				),
			)
		);

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$body = \json_decode( \wp_remote_retrieve_body( $response ), true );

		if ( ! \is_array( $body ) || isset( $body['error'] ) ) {
			return new \WP_Error( 'isurev_google_error', (string) ( $body['error']['message'] ?? \__( 'Google Places API error.', 'isudev-reviews' ) ) );
		}

		$reviews = array();
		foreach ( (array) ( $body['reviews'] ?? array() ) as $review ) {
			$reviews[] = array(
				// "places/{place}/reviews/{review}" is stable across requests.
				'id'           => (string) ( $review['name'] ?? '' ),
				'author'       => array(
					'name'   => (string) ( $review['authorAttribution']['displayName'] ?? '' ),
					'url'    => (string) ( $review['authorAttribution']['uri'] ?? '' ),
					'avatar' => (string) ( $review['authorAttribution']['photoUri'] ?? '' ),
				),
				'rating'       => isset( $review['rating'] ) ? (int) $review['rating'] : null,
				'text'         => (string) ( $review['originalText']['text'] ?? $review['text']['text'] ?? '' ),
				'language'     => (string) ( $review['originalText']['languageCode'] ?? $review['text']['languageCode'] ?? '' ),
				'published_at' => (string) ( $review['publishTime'] ?? '' ),
				'url'          => (string) ( $review['googleMapsUri'] ?? '' ),
			);
		}

		return new Fetch_Result(
			$reviews,
			array(
				'name'   => (string) ( $body['displayName']['text'] ?? '' ),
				'url'    => (string) ( $body['googleMapsUri'] ?? '' ),
				'rating' => (float) ( $body['rating'] ?? 0 ),
				'total'  => (int) ( $body['userRatingCount'] ?? 0 ),
			)
		);
	}
}
