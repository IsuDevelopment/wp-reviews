<?php
/**
 * Per-plugin SDK configuration.
 *
 * @package IsuDev\PluginSdk
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Vendor\IsuDev\PluginSdk;

defined( 'ABSPATH' ) || exit;

/**
 * What differs between isuDev plugins: product slug, option/hook prefix, option names,
 * license features and where the API URL and license key come from (the plugin's settings).
 */
final class Config {

	/**
	 * Option names, keyed site_token, verify_nonce, license, links.
	 *
	 * @var array<string, string>
	 */
	private array $options;

	/**
	 * Constructor.
	 *
	 * @param string                $product     Product slug on the platform, e.g. reviews.
	 * @param string                $prefix      Option, hook and error-code prefix, e.g. isurev.
	 * @param string                $agent       X-Isurev-Agent value, e.g. isudev-reviews/1.0.0.
	 * @param \Closure              $api_url     Returns the API base URL.
	 * @param \Closure              $license_key Returns the license key ('' when none).
	 * @param string[]              $features    Feature slugs the plugin understands; others are dropped.
	 * @param array<string, string> $options     Overrides for the default option names.
	 * @param string                $cli_command Parent WP-CLI command, defaults to the prefix.
	 * @param array<string, string> $links       Fallback links (pricing, account, …) until the platform answered.
	 */
	public function __construct(
		public readonly string $product,
		public readonly string $prefix,
		public readonly string $agent,
		private readonly \Closure $api_url,
		private readonly \Closure $license_key,
		public readonly array $features = array(),
		array $options = array(),
		public readonly string $cli_command = '',
		public readonly array $links = array(),
	) {
		$this->options = \array_merge(
			array(
				'site_token'   => $prefix . '_site_token',
				'verify_nonce' => $prefix . '_verify_nonce',
				'license'      => $prefix . '_license',
				'links'        => $prefix . '_links',
			),
			$options
		);
	}

	/**
	 * API base URL without a trailing slash.
	 */
	public function api_url(): string {
		return \untrailingslashit( (string) ( $this->api_url )() );
	}

	/**
	 * License key, '' when none is set.
	 */
	public function license_key(): string {
		return (string) ( $this->license_key )();
	}

	/**
	 * Option (or transient) name.
	 *
	 * @param string $key site_token, verify_nonce, license or links.
	 */
	public function option( string $key ): string {
		return $this->options[ $key ];
	}

	/**
	 * REST namespace the platform calls back during registration.
	 */
	public function rest_namespace(): string {
		return 'isudev-' . $this->product . '/v1';
	}

	/**
	 * Parent WP-CLI command.
	 */
	public function cli_command(): string {
		return '' !== $this->cli_command ? $this->cli_command : $this->prefix;
	}
}
