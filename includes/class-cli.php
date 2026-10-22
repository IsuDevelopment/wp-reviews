<?php
/**
 * WP-CLI commands.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

/**
 * Manage review sources and syncs. `register` and `license` come from the isuDev plugin SDK.
 *
 * ## EXAMPLES
 *
 *     wp isurev source-add google ChIJN1t_tDeuEmsRUsoyG83frY4 --provider=cloud
 *     wp isurev sync --all
 *     wp isurev license
 */
final class Cli {

	/**
	 * Registers the command.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'isurev', self::class );
	}

	/**
	 * Lists sources.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv, yaml, ids, count.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand sources
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function sources( array $args, array $assoc_args ): void {
		$reviews = new Review_Repository();
		$rows    = \array_map(
			static fn( array $source ) => array(
				'id'          => $source['id'],
				'platform'    => $source['platform'],
				'provider'    => $source['provider'],
				'external_id' => $source['external_id'],
				'name'        => $source['name'],
				'rating'      => $source['rating_avg'],
				'total'       => $source['rating_count'],
				'stored'      => $reviews->count(
					array(
						'source_ids'     => array( (int) $source['id'] ),
						'include_hidden' => true,
					)
				),
				'interval'    => $source['sync_interval'],
				'last_sync'   => $source['last_synced_at'],
				'status'      => $source['last_status'],
			),
			( new Source_Repository() )->all()
		);

		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'id', 'platform', 'provider', 'external_id', 'name', 'rating', 'total', 'stored', 'interval', 'last_sync', 'status' ) );
	}

	/**
	 * Adds a source (no license or source-count checks, for development).
	 *
	 * ## OPTIONS
	 *
	 * <platform>
	 * : google or facebook.
	 *
	 * <external_id>
	 * : Google Place ID or Facebook page ID.
	 *
	 * [--provider=<provider>]
	 * : cloud or google_places.
	 * ---
	 * default: cloud
	 * ---
	 *
	 * [--name=<name>]
	 * : Display name.
	 *
	 * [--interval=<interval>]
	 * : off, daily, weekly.
	 * ---
	 * default: off
	 * ---
	 *
	 * [--no-sync]
	 * : Do not fetch right away.
	 *
	 * @subcommand source-add
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function source_add( array $args, array $assoc_args ): void {
		list( $platform, $external_id ) = $args;

		$id = ( new Source_Repository() )->create(
			array(
				'platform'      => \sanitize_key( $platform ),
				'provider'      => \sanitize_key( $assoc_args['provider'] ?? 'cloud' ),
				'external_id'   => \sanitize_text_field( $external_id ),
				'name'          => \sanitize_text_field( $assoc_args['name'] ?? '' ),
				'sync_interval' => \sanitize_key( $assoc_args['interval'] ?? 'off' ),
			)
		);

		if ( \is_wp_error( $id ) ) {
			\WP_CLI::error( $id->get_error_message() );
		}

		\WP_CLI::success( "Source {$id} added." );

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'sync', true ) ) {
			$this->sync( array( (string) $id ), array() );
		}
	}

	/**
	 * Adds the demo source (30 sample reviews from assets/demo/reviews.json) and imports it. No API, works on local sites.
	 *
	 * ## EXAMPLES
	 *
	 *     wp isurev demo
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function demo( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed,Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WP-CLI signature.
		$id = Providers\Demo_Provider::ensure_source();
		if ( \is_wp_error( $id ) ) {
			\WP_CLI::error( $id->get_error_message() );
		}

		$this->sync( array( (string) $id ), array() );
	}

	/**
	 * Deletes a source and its reviews.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Source ID.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * @subcommand source-delete
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function source_delete( array $args, array $assoc_args ): void {
		\WP_CLI::confirm( "Delete source {$args[0]} and all its reviews?", $assoc_args );
		( new Source_Repository() )->delete( (int) $args[0] );
		\WP_CLI::success( 'Deleted.' );
	}

	/**
	 * Syncs one source, or all of them.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Source ID.
	 *
	 * [--all]
	 * : Sync every source.
	 *
	 * [--due]
	 * : Run what the cron tick would run (respects license and intervals).
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function sync( array $args, array $assoc_args ): void {
		if ( ! empty( $assoc_args['due'] ) ) {
			Cron::tick();
			\WP_CLI::success( Plugin::license()->has( 'auto_sync' ) ? 'Cron tick done.' : 'Cron tick skipped: no auto_sync feature in the license.' );
			return;
		}

		$ids = ! empty( $assoc_args['all'] )
			? \array_column( ( new Source_Repository() )->all(), 'id' )
			: $args;

		if ( ! $ids ) {
			\WP_CLI::error( 'Pass a source ID, --all or --due.' );
		}

		$service = Plugin::sync_service();
		$failed  = 0;
		foreach ( $ids as $id ) {
			$result = $service->sync( (int) $id );
			if ( \is_wp_error( $result ) ) {
				++$failed;
				\WP_CLI::warning( "Source {$id}: " . $result->get_error_message() );
				continue;
			}
			\WP_CLI::log( "Source {$id}: {$result['fetched']} fetched, {$result['new']} new." );
		}

		$failed ? \WP_CLI::error( "{$failed} source(s) failed." ) : \WP_CLI::success( 'Sync done.' );
	}

	/**
	 * Sets a plugin setting. Secrets are accepted but never printed.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : api_url, license_key, google_api_key, language.
	 *
	 * <value>
	 * : New value.
	 *
	 * @subcommand set
	 *
	 * @param string[] $args Positional args.
	 */
	public function set( array $args ): void {
		list( $key, $value ) = $args;

		if ( ! \array_key_exists( $key, Settings::defaults() ) || 'default_template' === $key ) {
			\WP_CLI::error( "Unknown setting {$key}." );
		}

		Settings::save( array( $key => $value ) );
		\WP_CLI::success( \in_array( $key, Settings::SECRETS, true ) ? "{$key} saved." : "{$key} = " . Settings::get( $key ) );
	}
}
