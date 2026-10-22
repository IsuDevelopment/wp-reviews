<?php
/**
 * Plugin settings.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

/**
 * One option array. Secrets (license_key, google_api_key) are write-only in the UI.
 */
final class Settings {

	const OPTION  = 'isurev_settings';
	const SECRETS = array( 'license_key', 'google_api_key' );

	/**
	 * Defaults.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'api_url'          => (string) ISUREV_API_URL,
			'license_key'      => '',
			'google_api_key'   => '',
			'default_template' => 'default',
			'language'         => \substr( \get_locale(), 0, 2 ),
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return \array_merge( self::defaults(), (array) \get_option( self::OPTION, array() ) );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 */
	public static function get( string $key ): string {
		return (string) ( self::all()[ $key ] ?? '' );
	}

	/**
	 * Saves submitted values. Empty secret fields keep the stored secret.
	 *
	 * @param array<string, mixed> $input Raw form input.
	 */
	public static function save( array $input ): void {
		$current = self::all();
		$next    = $current;

		$next['api_url']  = \esc_url_raw( \trim( (string) ( $input['api_url'] ?? $current['api_url'] ) ) );
		$next['language'] = \substr( \sanitize_key( (string) ( $input['language'] ?? $current['language'] ) ), 0, 5 );

		foreach ( self::SECRETS as $key ) {
			if ( ! empty( $input[ $key . '_clear' ] ) ) {
				$next[ $key ] = '';
			} elseif ( isset( $input[ $key ] ) && '' !== \trim( (string) $input[ $key ] ) ) {
				$next[ $key ] = \sanitize_text_field( \trim( (string) $input[ $key ] ) );
			}
		}

		\update_option( self::OPTION, $next, false );

		if ( $next['api_url'] !== $current['api_url'] ) {
			// A token belongs to one API server.
			Plugin::cloud()->sites()->forget();
		}

		if ( $next['license_key'] !== $current['license_key'] || $next['api_url'] !== $current['api_url'] ) {
			Plugin::license()->refresh();
		}
	}

	/**
	 * Sets the site-wide default template.
	 *
	 * @param string $slug Template slug.
	 */
	public static function set_default_template( string $slug ): void {
		$settings                     = self::all();
		$settings['default_template'] = \sanitize_key( $slug );
		\update_option( self::OPTION, $settings, false );
	}
}
