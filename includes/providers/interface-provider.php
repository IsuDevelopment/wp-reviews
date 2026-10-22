<?php
/**
 * Review provider contract.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * A provider fetches normalized reviews for one source. See .agents/plan.md for the review shape.
 */
interface Provider {

	/**
	 * Machine name stored in sources.provider.
	 */
	public function id(): string;

	/**
	 * Human label for the admin.
	 */
	public function label(): string;

	/**
	 * Platforms this provider can fetch.
	 *
	 * @return string[] google|facebook.
	 */
	public function platforms(): array;

	/**
	 * Whether it can run now (e.g. API key present, license allows the platform).
	 *
	 * @param string $platform Platform slug.
	 * @return true|\WP_Error
	 */
	public function is_available( string $platform );

	/**
	 * Fetches reviews.
	 *
	 * @param array<string, mixed> $source Source row.
	 * @param array<string, mixed> $args   limit, since (ISO 8601), language.
	 * @return Fetch_Result|\WP_Error
	 */
	public function fetch( array $source, array $args );
}
