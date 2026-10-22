<?php
/**
 * Wires the plugin together.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

use IsuDev\Reviews\Vendor\IsuDev\PluginSdk\Config;
use IsuDev\Reviews\Vendor\IsuDev\PluginSdk\License;
use IsuDev\Reviews\Vendor\IsuDev\PluginSdk\Sdk;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap.
 */
final class Plugin {

	/**
	 * Feature slugs the plugin understands in the license response.
	 */
	const FEATURES = array( 'auto_sync', 'facebook', 'unlimited', 'pro_templates', 'multiple_sources' );

	/**
	 * Cloud SDK (API client, site registration, license).
	 *
	 * @var Sdk|null
	 */
	private static ?Sdk $cloud = null;

	/**
	 * Hooks everything up. Runs on plugins_loaded.
	 */
	public static function boot(): void {
		Schema::maybe_upgrade();

		Cron::register();
		( new Rest_Controller() )->register();
		Block::register();

		if ( \is_admin() ) {
			( new Admin\Admin() )->register();
		}

		if ( \defined( 'WP_CLI' ) && WP_CLI ) {
			Cli::register();
		}

		// After Cli::register(): the SDK adds `register` and `license` under `wp isurev`.
		self::cloud()->register();

		Update_Checker::register();
	}

	/**
	 * Activation: tables and cron.
	 */
	public static function activate(): void {
		Schema::install();
		Cron::schedule();
	}

	/**
	 * Deactivation: stop cron. Data stays until uninstall.
	 */
	public static function deactivate(): void {
		Cron::unschedule();
	}

	/**
	 * Shared sync service with the default provider registry.
	 */
	public static function sync_service(): Sync_Service {
		return new Sync_Service( new Source_Repository(), new Review_Repository(), new Providers\Provider_Registry() );
	}

	/**
	 * Cloud SDK, configured for the reviews product.
	 */
	public static function cloud(): Sdk {
		self::$cloud ??= new Sdk(
			new Config(
				product: 'reviews',
				prefix: 'isurev',
				agent: 'isudev-reviews/' . VERSION,
				api_url: static fn(): string => Settings::get( 'api_url' ),
				license_key: static fn(): string => Settings::get( 'license_key' ),
				features: self::FEATURES,
			)
		);

		return self::$cloud;
	}

	/**
	 * License state (tier, features).
	 */
	public static function license(): License {
		return self::cloud()->license();
	}
}
