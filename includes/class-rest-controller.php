<?php
/**
 * REST routes for the editor and admin.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews;

defined( 'ABSPATH' ) || exit;

/**
 * Namespace isudev-reviews/v1. The SDK adds site-verify to the same namespace.
 */
final class Rest_Controller {

	const NAMESPACE = 'isudev-reviews/v1';

	/**
	 * Hooks route registration.
	 */
	public function register(): void {
		\add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Routes.
	 */
	public function routes(): void {
		\register_rest_route(
			self::NAMESPACE,
			'/sources',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'sources' ),
				'permission_callback' => static fn() => \current_user_can( 'edit_posts' ),
			)
		);

		\register_rest_route(
			self::NAMESPACE,
			'/templates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'templates' ),
				'permission_callback' => static fn() => \current_user_can( 'edit_posts' ),
			)
		);

		\register_rest_route(
			self::NAMESPACE,
			'/sources/(?P<id>\d+)/sync',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync' ),
				'permission_callback' => static fn() => \current_user_can( 'manage_options' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Sources for the block sidebar.
	 */
	public function sources(): \WP_REST_Response {
		$reviews = new Review_Repository();

		return \rest_ensure_response(
			\array_map(
				static fn( array $source ) => array(
					'id'           => (int) $source['id'],
					'platform'     => (string) $source['platform'],
					'name'         => '' !== (string) $source['name'] ? (string) $source['name'] : (string) $source['external_id'],
					'rating'       => (float) $source['rating_avg'],
					'rating_count' => (int) $source['rating_count'],
					'stored'       => $reviews->count( array( 'source_ids' => array( (int) $source['id'] ) ) ),
				),
				( new Source_Repository() )->all()
			)
		);
	}

	/**
	 * Templates with lock state.
	 */
	public function templates(): \WP_REST_Response {
		return \rest_ensure_response(
			\array_values(
				\array_map(
					static fn( array $template ) => array(
						'slug'        => $template['slug'],
						'title'       => $template['title'],
						'description' => $template['description'],
						'tier'        => $template['tier'],
						'locked'      => Templates::is_locked( $template ),
						'isDefault'   => Settings::get( 'default_template' ) === $template['slug'],
					),
					Templates::all()
				)
			)
		);
	}

	/**
	 * Runs a sync.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function sync( \WP_REST_Request $request ) {
		$result = Plugin::sync_service()->sync( (int) $request['id'] );

		return \is_wp_error( $result ) ? $result : \rest_ensure_response( $result );
	}
}
