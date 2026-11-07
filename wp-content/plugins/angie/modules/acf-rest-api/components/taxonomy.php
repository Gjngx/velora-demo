<?php

namespace Angie\Modules\AcfRestApi\Components;

use WP_REST_Response;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ACF REST API Taxonomy Controller
 */
class Taxonomy extends Base {
	/**
	 * REST API base
	 *
	 * @var string
	 */
	protected $rest_base = 'taxonomies';

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
	 * Get all taxonomies
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		$taxonomies = acf_get_taxonomies();

		return new WP_REST_Response( $taxonomies, 200 );
	}

	/**
	 * Get a specific taxonomy
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$slug = $request['slug'];

		// First try to find the taxonomy by slug directly.
		$taxonomy = acf_get_taxonomy( $slug );

		// If not found by slug, try to find by ID.
		if ( ! $taxonomy ) {
			$id = $this->get_taxonomy_id_from_slug( $slug );
			if ( $id ) {
				$taxonomy = acf_get_taxonomy( $id );
			}
		}

		// Ensure the returned taxonomy matches the requested slug.
		if ( ! $taxonomy || ( isset( $taxonomy['taxonomy'] ) && $taxonomy['taxonomy'] !== $slug ) ) {
			return new WP_Error( 'taxonomy_not_found', 'Taxonomy not found', [ 'status' => 404 ] );
		}

		return new WP_REST_Response( $taxonomy, 200 );
	}

	/**
	 * Helper method to get taxonomy ID from slug
	 *
	 * @param string $slug The taxonomy slug.
	 * @return int|false The taxonomy ID or false if not found
	 */
	protected function get_taxonomy_id_from_slug( $slug ) {
		// First check if this is a numeric ID.
		if ( is_numeric( $slug ) ) {
			return (int) $slug;
		}

		// Try to get taxonomy by slug using ACF's function.
		// This function accepts ID, key or name (slug).
		$taxonomy = acf_get_taxonomy( $slug );
		if ( $taxonomy && isset( $taxonomy['ID'] ) ) {
			return $taxonomy['ID'];
		}

		// If not found directly, try to get taxonomy post using ACF's function.
		$taxonomy_post = acf_get_taxonomy_post( $slug );
		if ( $taxonomy_post ) {
			return $taxonomy_post->ID;
		}

		// If still not found, try to search in all ACF taxonomies.
		$taxonomies = acf_get_acf_taxonomies([
			'taxonomy' => $slug,
		]);

		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $tax ) {
				if ( isset( $tax['taxonomy'] ) && $tax['taxonomy'] === $slug ) {
					return $tax['ID'];
				}
			}

			// If exact match not found, return the first one.
			$first_tax = reset( $taxonomies );
			return $first_tax['ID'];
		}

		return false;
	}

	/**
	 * Create a taxonomy
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$params = $request->get_params();

		// Validate taxonomy name length.
		if ( isset( $params['taxonomy'] ) && ( strlen( $params['taxonomy'] ) < 1 || strlen( $params['taxonomy'] ) > 32 ) ) {
			return new WP_Error(
				'invalid_taxonomy_name',
				'Taxonomy names must be between 1 and 32 characters in length.',
				[ 'status' => 400 ]
			);
		}

		// Ensure taxonomy has a title/label.
		if ( empty( $params['title'] ) && ! empty( $params['label'] ) ) {
			$params['title'] = $params['label'];
		} elseif ( empty( $params['title'] ) && ! empty( $params['taxonomy'] ) ) {
			// If no title or label, set title based on taxonomy name.
			$params['title'] = ucfirst( str_replace( '_', ' ', $params['taxonomy'] ) );
		}

		// Ensure label is set if title is available.
		if ( empty( $params['label'] ) && ! empty( $params['title'] ) ) {
			$params['label'] = $params['title'];
		}

		$taxonomy = acf_validate_taxonomy( $params );

		if ( is_wp_error( $taxonomy ) ) {
			return $taxonomy;
		}

		$taxonomy = acf_update_taxonomy( $taxonomy );

		return new WP_REST_Response( $taxonomy, 201 );
	}

	/**
	 * Update a taxonomy
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$slug = $request['slug'];
		$params = $request->get_params();

		// First try to find the existing taxonomy.
		$existing_taxonomy = acf_get_taxonomy( $slug );

		// If not found by slug, try to find by ID.
		if ( ! $existing_taxonomy ) {
			$id = $this->get_taxonomy_id_from_slug( $slug );
			if ( $id ) {
				$existing_taxonomy = acf_get_taxonomy( $id );
			}
		}

		if ( ! $existing_taxonomy ) {
			return new WP_Error( 'taxonomy_not_found', 'Taxonomy not found', [ 'status' => 404 ] );
		}

		// Merge with existing taxonomy to preserve existing fields.
		$params = array_merge( $existing_taxonomy, $params );
		$params['slug'] = $slug;

		// Ensure taxonomy field is set to avoid empty taxonomy names.
		if ( empty( $params['taxonomy'] ) ) {
			$params['taxonomy'] = $slug;
		}

		// Validate taxonomy name length.
		if ( isset( $params['taxonomy'] ) && ( strlen( $params['taxonomy'] ) < 1 || strlen( $params['taxonomy'] ) > 32 ) ) {
			return new WP_Error(
				'invalid_taxonomy_name',
				'Taxonomy names must be between 1 and 32 characters in length.',
				[ 'status' => 400 ]
			);
		}

		// Ensure taxonomy has a title/label.
		if ( empty( $params['title'] ) && ! empty( $params['label'] ) ) {
			$params['title'] = $params['label'];
		}

		// Ensure label is set if title is available.
		if ( empty( $params['label'] ) && ! empty( $params['title'] ) ) {
			$params['label'] = $params['title'];
		}

		$taxonomy = acf_validate_taxonomy( $params );

		if ( is_wp_error( $taxonomy ) ) {
			return $taxonomy;
		}

		$taxonomy = acf_update_taxonomy( $taxonomy );

		return new WP_REST_Response( $taxonomy, 200 );
	}

	/**
	 * Delete a taxonomy
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$slug = $request['slug'];

		// First try to find the taxonomy by slug directly.
		$taxonomy = acf_get_taxonomy( $slug );

		// If not found by slug, try to find by ID.
		if ( ! $taxonomy ) {
			$id = $this->get_taxonomy_id_from_slug( $slug );
			if ( $id ) {
				$taxonomy = acf_get_taxonomy( $id );
			}
		}

		if ( ! $taxonomy ) {
			return new WP_Error( 'taxonomy_not_found', 'Taxonomy not found', [ 'status' => 404 ] );
		}

		// If we found it by ID, delete using the ID.
		if ( isset( $taxonomy['ID'] ) ) {
			acf_delete_taxonomy( $taxonomy['ID'] );
		} else {
			// Otherwise delete by slug.
			acf_delete_taxonomy( $slug );
		}

		return new WP_REST_Response( null, 204 );
	}
}
