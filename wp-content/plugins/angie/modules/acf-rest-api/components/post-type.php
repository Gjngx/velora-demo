<?php

namespace Angie\Modules\AcfRestApi\Components;

use WP_REST_Response;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ACF REST API Post Type Controller
 */
class Post_Type extends Base {
	/**
	 * REST API base
	 *
	 * @var string
	 */
	protected $rest_base = 'post-types';

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, [
			'methods' => 'GET',
			'callback' => [ $this, 'get_items' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<slug>[a-zA-Z0-9_-]+)', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_item' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'slug' => [
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base, [
			'methods' => 'POST',
			'callback' => [ $this, 'create_item' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<slug>[a-zA-Z0-9_-]+)', [
			'methods' => 'PUT',
			'callback' => [ $this, 'update_item' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'slug' => [
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<slug>[a-zA-Z0-9_-]+)', [
			'methods' => 'DELETE',
			'callback' => [ $this, 'delete_item' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'slug' => [
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);
	}

	/**
	 * Get all post types
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		$post_types = acf_get_post_types();

		return new WP_REST_Response( $post_types, 200 );
	}

	/**
	 * Get a specific post type
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$slug = $request['slug'];

		$post_type = $this->get_acf_post_type_by_slug( $slug );

		if ( is_wp_error( $post_type ) ) {
			return $post_type;
		}

		return new WP_REST_Response( $post_type, 200 );
	}

	/**
	 * Get ACF post type by slug
	 *
	 * @param string $slug The post type slug.
	 * @return array|WP_Error ACF post type array or WP_Error if not found
	 */
	protected function get_acf_post_type_by_slug( $slug ) {
		// First check if the post type exists in WordPress.
		if ( ! post_type_exists( $slug ) ) {
			return new WP_Error( 'post_type_not_found', 'Post type not found', [ 'status' => 404 ] );
		}

		// Find the ACF post type ID for this post type.
		$acf_post_types = acf_get_internal_post_type_posts( 'acf-post-type', [ 'post_type' => $slug ] );

		if ( empty( $acf_post_types ) ) {
			return new WP_Error( 'post_type_not_found', 'ACF post type not found', [ 'status' => 404 ] );
		}

		// Find the exact matching post type instead of just taking the first one.
		$result = null;
		foreach ( $acf_post_types as $post_type ) {
			if ( isset( $post_type['post_type'] ) && $slug === $post_type['post_type'] ) {
				$result = $post_type;
				break;
			}
		}

		// If no exact match was found, return an error.
		if ( null === $result ) {
			return new WP_Error(
				'post_type_not_found',
				'Exact post type match not found',
				[ 'status' => 404 ]
			);
		}

		return $result;
	}

	/**
	 * Create a post type
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$params = $request->get_params();
		$slug = $params['slug'];

		// Ensure required fields are present.
		if ( empty( $params['post_type'] ) ) {
			$params['post_type'] = $slug;
		}

		// Set title if not provided.
		if ( empty( $params['title'] ) ) {
			$params['title'] = ucfirst( str_replace( '_', ' ', $params['post_type'] ) );
		}

		$post_type = acf_validate_post_type( $params );

		if ( is_wp_error( $post_type ) ) {
			return $post_type;
		}

		// Update the post type and handle any errors.
		$result = acf_update_post_type( $post_type );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * Update a post type
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$slug = $request['slug'];
		$params = $request->get_params();

		// Ensure we have the correct post type value.
		if ( empty( $params['post_type'] ) ) {
			$params['post_type'] = $slug;
		}

		$existing_post_type = $this->get_acf_post_type_by_slug( $slug );

		if ( is_wp_error( $existing_post_type ) ) {
			return $existing_post_type;
		}

		// Merge with existing post type to preserve settings not included in request.
		$params = array_merge( $existing_post_type, $params );

		// Set title if not provided.
		if ( empty( $params['title'] ) ) {
			$params['title'] = ucfirst( str_replace( '_', ' ', $params['post_type'] ) );
		}

		// Validate the updated post type.
		$post_type = acf_validate_post_type( $params );

		if ( is_wp_error( $post_type ) ) {
			return $post_type;
		}

		// Update the post type and handle any errors.
		$result = acf_update_post_type( $post_type );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Delete a post type
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$slug = $request['slug'];

		$post_type = $this->get_acf_post_type_by_slug( $slug );

		if ( is_wp_error( $post_type ) ) {
			return $post_type;
		}

		// Delete using the ACF post type key.
		acf_delete_post_type( $post_type['key'] );

		return new WP_REST_Response( null, 204 );
	}
}
