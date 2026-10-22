<?php
/**
 * Sources table access.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; all SQL lives here.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table names come from Schema, values are prepared.

/**
 * CRUD for review sources (one Google profile or Facebook page each).
 */
final class Source_Repository {

	const INTERVALS = array( 'off', 'daily', 'weekly' );

	/**
	 * All sources, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;

		return (array) $wpdb->get_results( 'SELECT * FROM ' . Schema::sources_table() . ' ORDER BY id DESC', ARRAY_A );
	}

	/**
	 * One source.
	 *
	 * @param int $id Source ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::sources_table() . ' WHERE id = %d', $id ), ARRAY_A );

		return $row ? $row : null;
	}

	/**
	 * Inserts a source.
	 *
	 * @param array<string, mixed> $data platform, provider, external_id, name, sync_interval.
	 * @return int|\WP_Error New ID.
	 */
	public function create( array $data ) {
		global $wpdb;

		$now = \current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			Schema::sources_table(),
			array(
				'platform'      => (string) $data['platform'],
				'provider'      => (string) $data['provider'],
				'external_id'   => (string) $data['external_id'],
				'name'          => (string) ( $data['name'] ?? '' ),
				'sync_interval' => \in_array( $data['sync_interval'] ?? 'off', self::INTERVALS, true ) ? $data['sync_interval'] : 'off',
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);

		if ( ! $ok ) {
			return new \WP_Error( 'isurev_source_exists', \__( 'This source already exists.', 'isudev-reviews' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates columns of a source.
	 *
	 * @param int                  $id   Source ID.
	 * @param array<string, mixed> $data Column => value.
	 */
	public function update( int $id, array $data ): void {
		global $wpdb;

		$data['updated_at'] = \current_time( 'mysql', true );
		$wpdb->update( Schema::sources_table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Deletes a source and its reviews.
	 *
	 * @param int $id Source ID.
	 */
	public function delete( int $id ): void {
		global $wpdb;

		$wpdb->delete( Schema::reviews_table(), array( 'source_id' => $id ), array( '%d' ) );
		$wpdb->delete( Schema::sources_table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Sources whose auto-sync interval has passed.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function due_for_sync(): array {
		global $wpdb;

		$table = Schema::sources_table();
		$now   = \gmdate( 'Y-m-d H:i:s' );

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE ( sync_interval = 'daily' AND ( last_synced_at IS NULL OR last_synced_at < DATE_SUB( %s, INTERVAL 1 DAY ) ) )
				OR ( sync_interval = 'weekly' AND ( last_synced_at IS NULL OR last_synced_at < DATE_SUB( %s, INTERVAL 7 DAY ) ) )",
				$now,
				$now
			),
			ARRAY_A
		);
	}
}
