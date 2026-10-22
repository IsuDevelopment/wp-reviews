<?php
/**
 * Server render for isudev-reviews/reviews.
 *
 * @package IsuDev\Reviews
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

// Renderer escapes every part where it is built.
echo IsuDev\Reviews\Renderer::render( $attributes, get_block_wrapper_attributes() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
