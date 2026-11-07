<?php

namespace Angie\Modules\PluginManager\Components;

use WP_REST_Response;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin Searcher
 *
 * Handles retrieving plugin details from the WordPress.org repository
 */
class Plugin_Searcher extends Base {

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
	protected $rest_base = 'plugins';

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base . '/search', [
			'methods' => 'GET',
			'callback' => [ $this, 'search_plugins' ],
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

		register_rest_route($this->namespace, '/' . $this->rest_base . '/info/(?P<slug>[a-zA-Z0-9_-]+)', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_plugin_info' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'slug' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => 'Plugin slug',
				],
			],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/update-info', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_plugins_update_info' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		]);
	}

	/**
	 * Search plugins in WordPress.org repository
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function search_plugins( $request ) {
		$search = $request->get_param( 'search' );
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$args = [
			'search' => $search,
			'page' => $page,
			'per_page' => $per_page,
			'fields' => [
				'name' => true,
				'slug' => true,
				'version' => true,
				'short_description' => true,
				'download_link' => true,
				'author' => true,
				'rating' => true,
				'num_ratings' => true,
				'active_installs' => true,
			],
		];

		$api = \plugins_api( 'query_plugins', $args );

		if ( is_wp_error( $api ) ) {
			return new \WP_Error(
				'plugin_search_error',
				$api->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		$response = [
			'plugins' => $api->plugins,
			'total' => isset( $api->info['results'] ) ? $api->info['results'] : 0,
			'pages' => isset( $api->info['pages'] ) ? $api->info['pages'] : 1,
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Get plugin information from WordPress.org
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_plugin_info( $request ) {
		$slug = $request->get_param( 'slug' );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$api = \plugins_api( 'plugin_information', [ 'slug' => $slug ] );

		if ( is_wp_error( $api ) ) {
			return new \WP_Error(
				'plugin_info_error',
				$api->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response( $api );
	}

	public function get_plugins_update_info() {
		require_once ABSPATH . 'wp-admin/includes/update.php';

		// Force update.
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		$plugins_needing_updates = get_plugin_updates();

		$result = array_map( function ( $plugin ) {
			return $plugin->update->new_version;
		}, $plugins_needing_updates );

		return rest_ensure_response( [ 'updates_available' => $result ] );
	}
}
