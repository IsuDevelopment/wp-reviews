<?php
/**
 * Self-updates from GitHub releases.
 *
 * @package IsuDev\Reviews
 */

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Plugin Update Checker against the release ZIPs of the public GitHub repo
 * (same setup as isudev-consent). Skipped in a git checkout, on Composer-managed sites,
 * with ISUREV_DISABLE_SELF_UPDATES or when the isurev_self_updates_enabled filter says no.
 */
final class Update_Checker {

	const REPOSITORY_URL = 'https://github.com/IsuDevelopment/wp-reviews/';

	/**
	 * Hooks the checker for admin and cron requests.
	 */
	public static function register(): void {
		if ( ! self::should_register() ) {
			return;
		}

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( self::REPOSITORY_URL, FILE, 'isudev-reviews' );

		if ( \method_exists( $checker, 'getVcsApi' ) ) {
			$vcs_api = $checker->getVcsApi();
			if ( \is_object( $vcs_api ) && \method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
				$vcs_api->enableReleaseAssets();
			}
		}
	}

	/**
	 * Whether this install should update itself.
	 */
	public static function should_register(): bool {
		if ( ! ( \is_admin() || \wp_doing_cron() ) || ! \class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
			return false;
		}

		// Source checkouts (the local symlink) and Composer-managed sites update another way.
		if ( \is_dir( DIR . '/.git' ) || ( \class_exists( '\\Composer\\InstalledVersions' ) && \Composer\InstalledVersions::isInstalled( 'isudev/reviews' ) ) ) {
			return false;
		}

		if ( \defined( 'ISUREV_DISABLE_SELF_UPDATES' ) && (bool) \constant( 'ISUREV_DISABLE_SELF_UPDATES' ) ) {
			return false;
		}

		/**
		 * Filters whether the plugin updates itself from GitHub releases.
		 *
		 * @param bool   $enabled Default true.
		 * @param string $file    Main plugin file.
		 */
		return (bool) \apply_filters( 'isurev_self_updates_enabled', true, FILE );
	}
}
