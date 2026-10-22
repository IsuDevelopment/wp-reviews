<?php
/**
 * Custom tables.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and migrates the plugin tables with dbDelta.
 */
final class Schema {

	const DB_VERSION = '2';
	const OPTION     = 'isurev_db_version';

	/**
	 * Sources table name.
	 */
	public static function sources_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'isurev_sources';
	}

	/**
	 * Reviews table name.
	 */
	public static function reviews_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'isurev_reviews';
	}

	/**
	 * Runs install when the stored schema version is behind.
	 */
	public static function maybe_upgrade(): void {
		if ( \get_option( self::OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Creates or updates tables. dbDelta needs one column per line and two spaces after PRIMARY KEY.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$sources = self::sources_table();
		$reviews = self::reviews_table();

		\dbDelta(
			"CREATE TABLE {$sources} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				platform varchar(20) NOT NULL,
				provider varchar(30) NOT NULL,
				external_id varchar(191) NOT NULL,
				name varchar(255) NOT NULL DEFAULT '',
				url varchar(500) NOT NULL DEFAULT '',
				rating_avg decimal(3,2) DEFAULT NULL,
				rating_count int(10) unsigned DEFAULT NULL,
				sync_interval varchar(20) NOT NULL DEFAULT 'off',
				last_synced_at datetime DEFAULT NULL,
				backfilled_at datetime DEFAULT NULL,
				last_status varchar(20) NOT NULL DEFAULT 'never',
				last_error text,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY platform_external (platform,external_id)
			) {$charset};
			CREATE TABLE {$reviews} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_id bigint(20) unsigned NOT NULL,
				platform varchar(20) NOT NULL,
				external_id varchar(191) NOT NULL,
				author_name varchar(255) NOT NULL DEFAULT '',
				author_url varchar(500) NOT NULL DEFAULT '',
				author_avatar varchar(500) NOT NULL DEFAULT '',
				rating tinyint(3) unsigned DEFAULT NULL,
				recommendation varchar(10) DEFAULT NULL,
				content longtext,
				language varchar(10) NOT NULL DEFAULT '',
				review_url varchar(500) NOT NULL DEFAULT '',
				reply_content text,
				published_at datetime NOT NULL,
				is_hidden tinyint(1) unsigned NOT NULL DEFAULT 0,
				is_pinned tinyint(1) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY source_review (source_id,external_id),
				KEY source_visible (source_id,is_hidden,published_at),
				KEY rating (rating)
			) {$charset};"
		);

		\update_option( self::OPTION, self::DB_VERSION, false );
	}

	/**
	 * Drops tables and options. Called from uninstall.php.
	 */
	public static function uninstall(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Uninstall; table names from Schema.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::reviews_table() . ', ' . self::sources_table() );

		foreach ( array( self::OPTION, Settings::OPTION ) as $option ) {
			\delete_option( $option );
		}

		Plugin::cloud()->uninstall();
	}
}
