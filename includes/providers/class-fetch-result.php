<?php
/**
 * Provider result.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Normalized reviews plus source meta (name, url, average, total).
 */
final class Fetch_Result {

	/**
	 * Constructor.
	 *
	 * @param array<int, array<string, mixed>>                                $reviews Normalized reviews.
	 * @param array{name?: string, url?: string, rating?: float, total?: int} $source  Source meta.
	 */
	public function __construct(
		public readonly array $reviews,
		public readonly array $source = array()
	) {}
}
