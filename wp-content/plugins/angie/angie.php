<?php
/**
 * Plugin Name: Angie
 * Description: Agentic AI for WordPress
 * Plugin URI: https://elementor.com/pages/angie-early-access
 * Version: 1.1.19
 * Author: Elementor.com
 * Author URI: https://elementor.com/?utm_source=wp-plugins-angie&utm_campaign=author-uri&utm_medium=wp-dash
 * Text Domain: angie
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.9
 * Tested up to: 7.1
 * Requires PHP: 7.4
 *
 * @package Angie
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'ANGIE_VERSION', '1.1.19' );
define( 'ANGIE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ANGIE_URL', plugins_url( '/', __FILE__ ) );
define( 'ANGIE_ASSETS_PATH', ANGIE_PATH . 'assets/' );
define( 'ANGIE_ASSETS_URL', ANGIE_URL . 'assets/' );

/**
 *  Angie Class
 */
final class Angie {

	public function __construct() {
		require_once ANGIE_PATH . 'includes/load-composer-autoload.php';
		angie_load_composer_autoload( ANGIE_PATH );
		add_action( 'plugins_loaded', [ $this, 'init' ] );
		add_action( 'admin_init', [ $this, 'redirect_after_activation' ] );
		add_action( 'init', [ $this, 'register_meta_fields' ] );
	}

	public function register_meta_fields() {
		register_post_meta(
			'post',
			'page_builder',
			[
				'show_in_rest' => true,
				'single' => true,
				'type' => 'string',
			]
		);

		register_post_meta(
			'attachment',
			'angie_ai_description',
			[
				'show_in_rest' => true,
				'single' => true,
				'type' => 'string',
				'default' => '',
				'auth_callback' => [ $this, 'can_edit_ai_description_meta' ],
			]
		);

	}

	public function can_edit_ai_description_meta(
		bool $allowed,
		string $meta_key,
		int $post_id,
		int $user_id = 0
	): bool {
		if ( 'angie_ai_description' !== $meta_key ) {
			return $allowed;
		}

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		return current_user_can( 'edit_post', $post_id );
	}

	public function init() {
		// Once we get here, We have passed all validation checks, so we can safely include our plugin.
		require_once 'plugin.php';
	}

	/**
	 * Redirect to Angie app page after plugin activation
	 */
	public function redirect_after_activation() {
		// Only redirect if the transient exists
		if ( ! get_transient( 'angie_activation_redirect' ) ) {
			return;
		}

		// Delete the transient so we don't redirect again
		delete_transient( 'angie_activation_redirect' );

		// Don't redirect if we're not in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Don't redirect if we're doing AJAX, running WP-CLI, or REST requests
                if ( wp_doing_ajax() ||
                         ( defined( 'WP_CLI' ) && WP_CLI ) ||
                         ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// Don't redirect if the user doesn't have the required capability
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check for bulk activation - properly sanitized
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['activate-multi'] ) ) {
			return;
		}

		// Don't redirect if headers already sent
		if ( headers_sent() ) {
			return;
		}

		// If activation was triggered from any same-origin wp-admin page (e.g.
		// installed via Elementor editor or any 3rd-party plugin screen), bounce
		// straight back to it with `open-angie=1` so the sidebar auto-opens —
		// avoids the visible flash of the angie-app page that the JS fallback
		// redirect cannot prevent (the 302 to angie-app happens before any JS
		// runs).
		$target = self::resolve_post_install_target( wp_get_referer() );
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Resolve the post-install / consent-flow target URL.
	 *
	 * Prefers the same-origin wp-admin referrer (with `open-angie=1` appended) so
	 * the user is sent back to where they triggered the install/consent action.
	 * Falls back to the Angie app page when no usable referrer is available.
	 *
	 * @param string|false $referer A referer URL or false.
	 * @param array        $extra_args Extra query args to merge into the bounce URL.
	 * @return string Absolute URL suitable for redirect.
	 */
	public static function resolve_post_install_target( $referer, array $extra_args = [] ) {
		$fallback_base = admin_url( 'admin.php?page=angie-app' );
		$fallback = empty( $extra_args ) ? $fallback_base : add_query_arg( $extra_args, $fallback_base );

		if ( ! $referer || 0 !== strpos( $referer, admin_url() ) ) {
			return $fallback;
		}

		$parts = wp_parse_url( $referer );
		$path = isset( $parts['path'] ) ? basename( $parts['path'] ) : '';
		$query = [];
		if ( ! empty( $parts['query'] ) ) {
			wp_parse_str( $parts['query'], $query );
		}

		// Never bounce back to nonce-protected action pages (e.g. update.php
		// used by zip upload) — the nonce will have expired by the time the
		// redirect fires, producing "The link you followed has expired."
		$nonce_pages = [ 'update.php', 'update-core.php' ];
		if ( in_array( $path, $nonce_pages, true ) ) {
			return $fallback;
		}

		// Never bounce back to plugin management pages — the Angie sidebar does
		// not render there, so open-angie=1 has no effect and the user sees no change.
		$plugin_pages = [ 'plugins.php', 'plugin-install.php' ];
		if ( in_array( $path, $plugin_pages, true ) ) {
			return $fallback;
		}

		// Never bounce back to angie-app / angie-consent — they're the source of the flash.
		$source_page = isset( $query['page'] ) ? $query['page'] : '';
		if ( in_array( $source_page, [ 'angie-app', 'angie-consent' ], true ) ) {
			return $fallback;
		}

		$args = array_merge( [ 'open-angie' => '1' ], $extra_args );
		return add_query_arg( $args, $referer );
	}

	public static function activate_plugin() {
		require_once __DIR__ . '/plugin.php';

		if ( class_exists( '\Angie\Classes\Angie_Capability_Manager' ) ) {
			\Angie\Classes\Angie_Capability_Manager::add_angie_capability_to_default_roles();
		}
		// Set a transient to redirect on next page load
		set_transient( 'angie_activation_redirect', true, 30 );
	}
}

new Angie();

register_activation_hook( __FILE__, [ 'Angie', 'activate_plugin' ] );
register_uninstall_hook( __FILE__, 'angie_uninstall_cleanup' );

/**
 * Uninstall cleanup - WordPress recommended approach
 * This runs when plugin is deleted via WordPress admin
 */
function angie_uninstall_cleanup() {
	// Reset consent data to ensure clean onboarding flow on reinstall
	delete_option( 'angie_external_scripts_consent' );
	delete_option( 'angie_sidebar_default_state' );
}
