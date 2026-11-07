<?php

namespace Angie\Modules\Navigation\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu Provider
 *
 * Handles WordPress admin menu items via REST API
 */
class Menu_Provider {

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	protected $namespace = 'angie/v1';

	/**
	 * REST API base
	 *
	 * @var string
	 */
	protected $rest_base = 'menu-items';

	public function __construct() {
		\add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		\add_action( 'admin_menu', [ $this, 'cache_admin_menu' ], 999 );

		// Add cache invalidation hooks.
		\add_action( 'activated_plugin', [ $this, 'invalidate_menu_cache' ] );
		\add_action( 'deactivated_plugin', [ $this, 'invalidate_menu_cache' ] );
		\add_action( 'switch_theme', [ $this, 'invalidate_menu_cache' ] );
		\add_action( 'wp_update_nav_menu', [ $this, 'invalidate_menu_cache' ] );
	}

	public function register_routes() {
		\register_rest_route($this->namespace, '/' . $this->rest_base, [
			'methods' => 'GET',
			'callback' => [ $this, 'get_menu_items' ],
			'permission_callback' => function () {
				return \current_user_can( 'edit_posts' );
			},
		]);
	}

	/**
	 * Invalidate cached menu data
	 */
	public function invalidate_menu_cache() {
		\delete_transient( 'angie_cached_menu' );
		\delete_transient( 'angie_cached_submenu' );
	}

	/**
	 * Cache admin menu when it's available
	 */
	public function cache_admin_menu() {
		global $menu, $submenu;

		if ( ! empty( $menu ) ) {
			\set_transient( 'angie_cached_menu', $menu, 3600 ); // 1 hour
		}
		if ( ! empty( $submenu ) ) {
			\set_transient( 'angie_cached_submenu', $submenu, 3600 ); // 1 hour
		}
	}

	/**
	 * Get WordPress menu items for REST API
	 *
	 * @return array Menu items data
	 */
	public function get_menu_items() {
		$cached_menu = \get_transient( 'angie_cached_menu' );
		$cached_submenu = \get_transient( 'angie_cached_submenu' );

		return \rest_ensure_response([
			'menu' => false !== $cached_menu ? $cached_menu : [],
			'submenu' => false !== $cached_submenu ? $cached_submenu : [],
		]);
	}
}
