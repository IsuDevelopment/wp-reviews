<?php
/**
 * Reviews table access.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; all SQL lives here.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- Table names come from Schema, values are prepared.

/**
 * Reads and upserts reviews.
 */
final class Review_Repository {

	const CACHE_GROUP = 'isurev';

	/**
	 * Inserts new reviews and refreshes existing ones. Admin flags (is_hidden, is_pinned) are never overwritten.
	 *
	 * @param int                              $source_id Source ID.
	 * @param string                           $platform  google|facebook.
	 * @param array<int, array<string, mixed>> $reviews   Normalized reviews (see .agents/plan.md contract).
	 * @return int Number of new reviews.
	 */
	public function upsert_many( int $source_id, string $platform, array $reviews ): int {
		global $wpdb;

		$table  = Schema::reviews_table();
		$now    = \current_time( 'mysql', true );
		$filter = array(
			'source_ids'     => array( $source_id ),
			'include_hidden' => true,
		);
		// Affected-row counts differ between MySQL and SQLite (Playground), so count rows instead.
		$before = $this->count( $filter );

		foreach ( $reviews as $review ) {
			$external_id = (string) ( $review['id'] ?? '' );
			if ( '' === $external_id ) {
				// Providers without IDs: author + time is stable enough to dedupe.
				$external_id = \md5( ( $review['author']['name'] ?? '' ) . '|' . ( $review['published_at'] ?? '' ) );
			}

			// Facebook recommendations carry no stars: rating stays NULL.
			$rating      = isset( $review['rating'] ) ? \max( 1, \min( 5, (int) $review['rating'] ) ) : null;
			$rating_sql  = null === $rating ? 'NULL' : (string) $rating;
			$recommended = \in_array( $review['recommendation'] ?? null, array( 'positive', 'negative' ), true ) ? $review['recommendation'] : '';

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table}
					( source_id, platform, external_id, author_name, author_url, author_avatar, rating, recommendation, content, language, review_url, reply_content, published_at, created_at, updated_at )
					VALUES ( %d, %s, %s, %s, %s, %s, {$rating_sql}, %s, %s, %s, %s, %s, %s, %s, %s )
					ON DUPLICATE KEY UPDATE
						author_name = VALUES(author_name), author_url = VALUES(author_url), author_avatar = VALUES(author_avatar),
						rating = VALUES(rating), recommendation = VALUES(recommendation), content = VALUES(content),
						language = VALUES(language), review_url = VALUES(review_url), reply_content = VALUES(reply_content),
						updated_at = VALUES(updated_at)",
					$source_id,
					$platform,
					\substr( $external_id, 0, 191 ),
					\sanitize_text_field( (string) ( $review['author']['name'] ?? '' ) ),
					\esc_url_raw( (string) ( $review['author']['url'] ?? '' ) ),
					\esc_url_raw( (string) ( $review['author']['avatar'] ?? '' ) ),
					$recommended,
					\sanitize_textarea_field( (string) ( $review['text'] ?? '' ) ),
					\substr( \sanitize_key( (string) ( $review['language'] ?? '' ) ), 0, 10 ),
					\esc_url_raw( (string) ( $review['url'] ?? '' ) ),
					\sanitize_textarea_field( (string) ( $review['reply']['text'] ?? '' ) ),
					self::to_mysql_date( (string) ( $review['published_at'] ?? '' ) ),
					$now,
					$now
				)
			);
		}

		\wp_cache_set_last_changed( self::CACHE_GROUP );

		return $this->count( $filter ) - $before;
	}

	/**
	 * Visible reviews for display. Pinned first, then newest.
	 *
	 * @param array{source_ids?: int[], limit?: int, min_rating?: int, with_text?: bool, include_hidden?: bool, offset?: int} $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$cache_key = 'query:' . \md5( (string) \wp_json_encode( $args ) ) . ':' . \wp_cache_get_last_changed( self::CACHE_GROUP );
		$cached    = \wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( \is_array( $cached ) ) {
			return $cached;
		}

		list( $where, $params ) = $this->where( $args );

		$params[] = \max( 1, \min( 200, (int) ( $args['limit'] ?? 10 ) ) );
		$params[] = \max( 0, (int) ( $args['offset'] ?? 0 ) );

		$reviews = Schema::reviews_table();
		$sources = Schema::sources_table();
		$rows    = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, s.name AS source_name, s.url AS source_url
				FROM {$reviews} r LEFT JOIN {$sources} s ON s.id = r.source_id
				WHERE {$where}
				ORDER BY r.is_pinned DESC, r.published_at DESC
				LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		\wp_cache_set( $cache_key, $rows, self::CACHE_GROUP );

		return $rows;
	}

	/**
	 * Count for the same filters as query().
	 *
	 * @param array<string, mixed> $args Query args.
	 */
	public function count( array $args = array() ): int {
		global $wpdb;

		list( $where, $params ) = $this->where( $args );
		$sql                    = 'SELECT COUNT(*) FROM ' . Schema::reviews_table() . " r WHERE {$where}";

		return (int) $wpdb->get_var( $params ? $wpdb->prepare( $sql, ...$params ) : $sql );
	}

	/**
	 * Shows or hides reviews.
	 *
	 * @param int[] $ids    Review IDs.
	 * @param bool  $hidden Hide when true.
	 */
	public function set_hidden( array $ids, bool $hidden ): void {
		global $wpdb;

		$ids = \array_filter( \array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::reviews_table() . ' SET is_hidden = %d WHERE id IN (' . \implode( ',', \array_fill( 0, \count( $ids ), '%d' ) ) . ')',
				(int) $hidden,
				...$ids
			)
		);

		\wp_cache_set_last_changed( self::CACHE_GROUP );
	}

	/**
	 * WHERE clause and params shared by query() and count().
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{0: string, 1: array<int, int|string>}
	 */
	private function where( array $args ): array {
		$where  = array( '1=1' );
		$params = array();

		if ( empty( $args['include_hidden'] ) ) {
			$where[] = 'r.is_hidden = 0';
		}

		$source_ids = \array_filter( \array_map( 'absint', (array) ( $args['source_ids'] ?? array() ) ) );
		if ( $source_ids ) {
			$where[] = 'r.source_id IN (' . \implode( ',', \array_fill( 0, \count( $source_ids ), '%d' ) ) . ')';
			$params  = \array_merge( $params, $source_ids );
		}

		if ( ! empty( $args['min_rating'] ) ) {
			// Facebook recommendations have no star rating; count positive ones as 5.
			$where[]  = "( r.rating >= %d OR ( r.rating IS NULL AND r.recommendation = 'positive' ) )";
			$params[] = (int) $args['min_rating'];
		}

		if ( ! empty( $args['with_text'] ) ) {
			$where[] = "r.content <> ''";
		}

		return array( \implode( ' AND ', $where ), $params );
	}

	/**
	 * ISO 8601 → MySQL UTC datetime.
	 *
	 * @param string $date Date string.
	 */
	private static function to_mysql_date( string $date ): string {
		$timestamp = \strtotime( $date );

		return \gmdate( 'Y-m-d H:i:s', $timestamp ? $timestamp : \time() );
	}
}
