<?php

namespace Angie\Modules\ThemeManager\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Theme Searcher
 *
 * Handles searching themes in the WordPress.org repository
 */
class Theme_Searcher {

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
	protected $rest_base = 'themes/search';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, [
			'methods' => 'GET',
			'callback' => [ $this, 'search_themes' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'search' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => 'Search term',
				],
				'page' => [
					'required' => false,
					'type' => 'integer',
					'default' => 1,
					'sanitize_callback' => 'absint',
					'description' => 'Page number',
				],
				'per_page' => [
					'required' => false,
					'type' => 'integer',
					'default' => 10,
					'sanitize_callback' => 'absint',
					'description' => 'Number of results per page',
				],
			],
		]);

		register_rest_route($this->namespace, '/themes/info/(?P<slug>[a-zA-Z0-9_-]+)', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_theme_info' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'slug' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => 'Theme slug',
				],
			],
		]);

		register_rest_route($this->namespace, '/themes/update-info', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_theme_update_info' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		]);
	}

	public function permissions_check() {
		return current_user_can( 'install_themes' );
	}

	public function search_themes( $request ) {
		$search = $request->get_param( 'search' );
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$args = [
			'search' => $search,
			'page' => $page,
			'per_page' => $per_page,
			'fields' => [
				'name' => true,
				'slug' => true,
				'version' => true,
				'preview_url' => true,
				'screenshot_url' => true,
				'description' => true,
				'download_link' => true,
			],
		];

		$api = \themes_api( 'query_themes', $args );

		if ( is_wp_error( $api ) ) {
			return new \WP_Error(
				'theme_search_error',
				$api->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		$response = [
			'themes' => $api->themes,
			'total' => $api->info['results'],
			'pages' => $api->info['pages'],
		];

		return rest_ensure_response( $response );
	}

	public function get_theme_info( $request ) {
		$slug = $request->get_param( 'slug' );

		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$api = \themes_api( 'theme_information', [ 'slug' => $slug ] );

		if ( is_wp_error( $api ) ) {
			return new \WP_Error(
				'theme_info_error',
				$api->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response( $api );
	}

	public function get_theme_update_info() {
		require_once ABSPATH . 'wp-admin/includes/update.php';

		// Force refresh of theme update information.
		delete_site_transient( 'update_themes' );
		wp_update_themes();

		$updates = get_theme_updates();

		$result = array_map( function ( $update_data ) {
			return $update_data->update['new_version'];
		}, $updates );

		return rest_ensure_response( [ 'updates_available' => $result ] );
	}
}
