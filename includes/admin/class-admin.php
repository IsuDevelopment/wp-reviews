<?php
/**
 * Admin screens.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Admin;

use IsuDev\Reviews\Plugin;
use IsuDev\Reviews\Providers\Demo_Provider;
use IsuDev\Reviews\Providers\Provider_Registry;
use IsuDev\Reviews\Review_Repository;
use IsuDev\Reviews\Settings;
use IsuDev\Reviews\Source_Repository;
use IsuDev\Reviews\Templates;
use const IsuDev\Reviews\DIR;
use const IsuDev\Reviews\FILE;
use const IsuDev\Reviews\VERSION;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, form handlers (admin-post.php) and notices.
 */
final class Admin {

	const SLUG       = 'isudev-reviews';
	const CAPABILITY = 'manage_options';

	/**
	 * Hooks.
	 */
	public function register(): void {
		\add_action( 'admin_menu', array( $this, 'menu' ) );
		\add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		\add_action( 'admin_notices', array( $this, 'notice' ) );

		foreach ( array( 'add_source', 'add_demo', 'delete_source', 'sync_source', 'save_settings', 'set_template', 'review_visibility' ) as $action ) {
			\add_action( 'admin_post_isurev_' . $action, array( $this, 'handle_' . $action ) );
		}
	}

	/**
	 * Menu: Reviews › All reviews, Sources, Templates, Settings.
	 */
	public function menu(): void {
		$hook = \add_menu_page( \__( 'Reviews', 'isudev-reviews' ), \__( 'Reviews', 'isudev-reviews' ), self::CAPABILITY, self::SLUG, array( $this, 'page_reviews' ), 'dashicons-star-filled', 26 );
		\add_action( 'load-' . $hook, array( $this, 'handle_bulk' ) );
		\add_submenu_page( self::SLUG, \__( 'All reviews', 'isudev-reviews' ), \__( 'All reviews', 'isudev-reviews' ), self::CAPABILITY, self::SLUG, array( $this, 'page_reviews' ) );
		\add_submenu_page( self::SLUG, \__( 'Sources', 'isudev-reviews' ), \__( 'Sources', 'isudev-reviews' ), self::CAPABILITY, self::SLUG . '-sources', array( $this, 'page_sources' ) );
		\add_submenu_page( self::SLUG, \__( 'Templates', 'isudev-reviews' ), \__( 'Templates', 'isudev-reviews' ), self::CAPABILITY, self::SLUG . '-templates', array( $this, 'page_templates' ) );
		\add_submenu_page( self::SLUG, \__( 'Settings', 'isudev-reviews' ), \__( 'Settings', 'isudev-reviews' ), self::CAPABILITY, self::SLUG . '-settings', array( $this, 'page_settings' ) );
	}

	/**
	 * Admin CSS everywhere in the plugin; block + template styles on Templates (live previews).
	 *
	 * @param string $hook_suffix Current screen.
	 */
	public function assets( string $hook_suffix ): void {
		if ( ! \str_contains( $hook_suffix, self::SLUG ) ) {
			return;
		}

		\wp_enqueue_style( 'isurev-admin', \plugins_url( 'assets/admin.css', FILE ), array(), VERSION );

		if ( \str_contains( $hook_suffix, 'templates' ) || \str_contains( $hook_suffix, 'page_' . self::SLUG ) ) {
			\wp_enqueue_style( 'isudev-reviews-reviews-style' );
			Templates::enqueue_all_styles();
		}
	}

	/**
	 * Pages.
	 */
	public function page_reviews(): void {
		$this->view( 'reviews', array( 'table' => new Reviews_List_Table( new Review_Repository(), new Source_Repository() ) ) );
	}

	/**
	 * Sources page.
	 */
	public function page_sources(): void {
		$this->view(
			'sources',
			array(
				'sources'   => ( new Source_Repository() )->all(),
				'providers' => ( new Provider_Registry() )->all(),
				'reviews'   => new Review_Repository(),
				'is_pro'    => Plugin::license()->is_pro(),
			)
		);
	}

	/**
	 * Templates page.
	 */
	public function page_templates(): void {
		$this->view(
			'templates',
			array(
				'templates' => Templates::all(),
				'current'   => Settings::get( 'default_template' ),
			)
		);
	}

	/**
	 * Settings page.
	 */
	public function page_settings(): void {
		$this->view(
			'settings',
			array(
				'settings' => Settings::all(),
				'license'  => Plugin::license()->state(),
			)
		);
	}

	/**
	 * Adds a source and runs the first sync.
	 */
	public function handle_add_source(): void {
		$this->verify( 'isurev_add_source' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verify() checked the nonce.
		$platform    = \sanitize_key( \wp_unslash( $_POST['platform'] ?? 'google' ) );
		$provider_id = \sanitize_key( \wp_unslash( $_POST['provider'] ?? 'cloud' ) );
		$external_id = \sanitize_text_field( \wp_unslash( $_POST['external_id'] ?? '' ) );
		$name        = \sanitize_text_field( \wp_unslash( $_POST['name'] ?? '' ) );
		$interval    = \sanitize_key( \wp_unslash( $_POST['sync_interval'] ?? 'off' ) );
		// phpcs:enable

		$provider = ( new Provider_Registry() )->get( $provider_id );
		$sources  = new Source_Repository();

		if ( '' === $external_id || ! $provider || ! \in_array( $platform, $provider->platforms(), true ) ) {
			$this->redirect( 'sources', 'error', \__( 'Choose a platform, a provider that supports it and an ID.', 'isudev-reviews' ) );
		}

		// The demo source does not use the free plan's one source.
		$real = \array_filter( $sources->all(), static fn( array $source ): bool => 'demo' !== $source['provider'] );
		if ( ! Plugin::license()->has( 'multiple_sources' ) && \count( $real ) >= 1 ) {
			$this->redirect( 'sources', 'error', \__( 'The free version supports one source. Upgrade to Pro for more.', 'isudev-reviews' ) );
		}

		$id = $sources->create(
			array(
				'platform'      => $platform,
				'provider'      => $provider_id,
				'external_id'   => $external_id,
				'name'          => $name,
				'sync_interval' => Plugin::license()->has( 'auto_sync' ) ? $interval : 'off',
			)
		);

		if ( \is_wp_error( $id ) ) {
			$this->redirect( 'sources', 'error', $id->get_error_message() );
		}

		$this->redirect_after_sync( (int) $id );
	}

	/**
	 * Adds (or refreshes) the demo source with sample reviews, no API needed.
	 */
	public function handle_add_demo(): void {
		$this->verify( 'isurev_add_demo' );

		$id = Demo_Provider::ensure_source();
		if ( \is_wp_error( $id ) ) {
			$this->redirect( 'sources', 'error', $id->get_error_message() );
		}

		$this->redirect_after_sync( (int) $id );
	}

	/**
	 * Manual sync.
	 */
	public function handle_sync_source(): void {
		$this->verify( 'isurev_sync_source' );
		$this->redirect_after_sync( \absint( $_POST['source_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify() checked the nonce.
	}

	/**
	 * Deletes a source and its reviews.
	 */
	public function handle_delete_source(): void {
		$this->verify( 'isurev_delete_source' );
		( new Source_Repository() )->delete( \absint( $_POST['source_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify() checked the nonce.
		$this->redirect( 'sources', 'success', \__( 'Source and its reviews deleted.', 'isudev-reviews' ) );
	}

	/**
	 * Settings form.
	 */
	public function handle_save_settings(): void {
		$this->verify( 'isurev_save_settings' );
		Settings::save( (array) \wp_unslash( $_POST['isurev'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified; Settings::save() sanitizes each key.
		$this->redirect( 'settings', 'success', \__( 'Settings saved.', 'isudev-reviews' ) );
	}

	/**
	 * Picks the site-wide default template.
	 */
	public function handle_set_template(): void {
		$this->verify( 'isurev_set_template' );

		$slug     = \sanitize_key( \wp_unslash( $_POST['template'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify() checked the nonce.
		$template = Templates::all()[ $slug ] ?? null;

		if ( ! $template || Templates::is_locked( $template ) ) {
			$this->redirect( 'templates', 'error', \__( 'This template needs a Pro license.', 'isudev-reviews' ) );
		}

		Settings::set_default_template( $slug );
		$this->redirect( 'templates', 'success', \__( 'Default template updated.', 'isudev-reviews' ) );
	}

	/**
	 * Hide/show from row actions (GET link with nonce) or bulk actions.
	 */
	public function handle_review_visibility(): void {
		$this->verify( 'isurev_review_visibility' );
		$this->handle_review_visibility_unchecked();
	}

	/**
	 * Applies visibility after the caller verified the request.
	 */
	private function handle_review_visibility_unchecked(): void {
		if ( ! \current_user_can( self::CAPABILITY ) ) {
			\wp_die( \esc_html__( 'You are not allowed to do this.', 'isudev-reviews' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verify() checked the nonce.
		$ids    = \array_map( 'absint', (array) \wp_unslash( $_REQUEST['review'] ?? array() ) );
		$hidden = 'hide' === \sanitize_key( \wp_unslash( $_REQUEST['visibility'] ?? '' ) );
		// phpcs:enable

		( new Review_Repository() )->set_hidden( $ids, $hidden );
		$this->redirect( '', 'success', $hidden ? \__( 'Reviews hidden.', 'isudev-reviews' ) : \__( 'Reviews visible again.', 'isudev-reviews' ) );
	}

	/**
	 * Bulk hide/show from the list table (GET form on the page itself, nonce from WP_List_Table).
	 */
	public function handle_bulk(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Checked below once we know it is a bulk request.
		$action = \sanitize_key( \wp_unslash( $_REQUEST['action'] ?? '' ) );
		if ( '-1' === $action || '' === $action ) {
			$action = \sanitize_key( \wp_unslash( $_REQUEST['action2'] ?? '' ) );
		}
		if ( ! \in_array( $action, array( 'hide', 'show' ), true ) || empty( $_REQUEST['review'] ) ) {
			return;
		}
		// phpcs:enable

		\check_admin_referer( 'bulk-reviews' );
		$_REQUEST['visibility'] = $action;
		$this->handle_review_visibility_unchecked();
	}

	/**
	 * One-shot notice stored per user (keeps messages out of URLs).
	 */
	public function notice(): void {
		$key    = 'isurev_notice_' . \get_current_user_id();
		$notice = \get_transient( $key );

		if ( ! \is_array( $notice ) ) {
			return;
		}

		\delete_transient( $key );
		\wp_admin_notice(
			\esc_html( (string) $notice['message'] ),
			array(
				'type'        => 'error' === $notice['type'] ? 'error' : 'success',
				'dismissible' => true,
			)
		);
	}

	/**
	 * Sync and report.
	 *
	 * @param int $source_id Source ID.
	 */
	private function redirect_after_sync( int $source_id ): void {
		$result = Plugin::sync_service()->sync( $source_id );

		if ( \is_wp_error( $result ) ) {
			$this->redirect( 'sources', 'error', $result->get_error_message() );
		}

		$this->redirect(
			'sources',
			'success',
			/* translators: 1: fetched reviews, 2: new reviews. */
			\sprintf( \__( 'Sync done: %1$d fetched, %2$d new.', 'isudev-reviews' ), $result['fetched'], $result['new'] )
		);
	}

	/**
	 * Capability and nonce check for a form handler.
	 *
	 * @param string $action Nonce action.
	 */
	private function verify( string $action ): void {
		if ( ! \current_user_can( self::CAPABILITY ) ) {
			\wp_die( \esc_html__( 'You are not allowed to do this.', 'isudev-reviews' ), 403 );
		}
		\check_admin_referer( $action );
	}

	/**
	 * Stores a notice and redirects to a plugin page.
	 *
	 * @param string $page    Page suffix ('' = reviews list).
	 * @param string $type    success|error.
	 * @param string $message Message.
	 */
	private function redirect( string $page, string $type, string $message ): void {
		\set_transient(
			'isurev_notice_' . \get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);

		\wp_safe_redirect( \admin_url( 'admin.php?page=' . self::SLUG . ( $page ? '-' . $page : '' ) ) );
		exit;
	}

	/**
	 * Includes a view with local variables.
	 *
	 * @param string               $name View file name.
	 * @param array<string, mixed> $vars Variables for the view.
	 */
	private function view( string $name, array $vars ): void {
		( static function ( string $isurev_file, array $isurev_vars ): void {
			\extract( $isurev_vars ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Scoped view variables.
			include $isurev_file;
		} )( DIR . '/includes/admin/views/' . $name . '.php', $vars );
	}
}
