<?php
/**
 * Auto-sync (Pro).
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

/**
 * Hourly tick that syncs sources whose interval has passed.
 */
final class Cron {

	const HOOK = 'isurev_cron_tick';

	/**
	 * Hooks the tick handler.
	 */
	public static function register(): void {
		\add_action( self::HOOK, array( self::class, 'tick' ) );

		// Recover a missing schedule (e.g. after a migration copied the DB without cron).
		if ( ! \wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	/**
	 * Schedules the hourly tick.
	 */
	public static function schedule(): void {
		if ( ! \wp_next_scheduled( self::HOOK ) ) {
			\wp_schedule_event( \time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/**
	 * Removes the tick.
	 */
	public static function unschedule(): void {
		\wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Syncs due sources. Few per tick so one slow API call can't stall WP-Cron.
	 */
	public static function tick(): void {
		if ( ! Plugin::license()->has( 'auto_sync' ) ) {
			return;
		}

		$service = Plugin::sync_service();
		foreach ( \array_slice( ( new Source_Repository() )->due_for_sync(), 0, 3 ) as $source ) {
			$service->sync( (int) $source['id'] );
		}
	}
}
