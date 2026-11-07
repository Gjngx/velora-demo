<?php

namespace Angie\Modules\AcfRestApi\Components;

use WP_REST_Response;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ACF REST API Field Controller
 */
class Field extends Base {
	/**
	 * REST API base
	 *
	 * @var string
	 */
	protected $rest_base = 'fields';

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, [
			'methods' => 'GET',
			'callback' => [ $this, 'get_items' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<key>[a-zA-Z0-9_-]+)', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_item' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'key' => [
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

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<key>[a-zA-Z0-9_-]+)', [
			'methods' => 'PUT',
			'callback' => [ $this, 'update_item' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'key' => [
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<key>[a-zA-Z0-9_-]+)', [
			'methods' => 'DELETE',
			'callback' => [ $this, 'delete_item' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'key' => [
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);

		// Endpoint to get fields by group.
		register_rest_route($this->namespace, '/field-groups/(?P<group_key>[a-zA-Z0-9_-]+)/fields', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_fields_by_group' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'group_key' => [
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);
	}

	/**
	 * Get all fields
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		// Get all field groups.
		$field_groups = acf_get_field_groups();
		$all_fields = [];

		// Loop through each field group and get its fields.
		foreach ( $field_groups as $field_group ) {
			$fields = acf_get_fields( $field_group );
			if ( $fields ) {
				$all_fields = array_merge( $all_fields, $fields );
			}
		}

		return new WP_REST_Response( $all_fields, 200 );
	}

	/**
	 * Get a specific field
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$key = $request['key'];

		$field = acf_get_field( $key );

		if ( ! $field ) {
			return new WP_Error( 'field_not_found', 'Field not found', [ 'status' => 404 ] );
		}

		return new WP_REST_Response( $field, 200 );
	}

	/**
	 * Get fields by group
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_fields_by_group( $request ) {
		$group_key = $request['group_key'];

		$field_group = acf_get_field_group( $group_key );

		if ( ! $field_group ) {
			return new WP_Error( 'field_group_not_found', 'Field group not found', [ 'status' => 404 ] );
		}

		$fields = acf_get_fields( $field_group );

		return new WP_REST_Response( $fields, 200 );
	}

	/**
	 * Create a field
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$params = $request->get_params();

		// Validate parent field group.
		if ( empty( $params['parent'] ) ) {
			return new WP_Error( 'missing_parent', 'Field must belong to a field group', [ 'status' => 400 ] );
		}

		// Check if parent field group exists.
		$field_group = acf_get_field_group( $params['parent'] );
		if ( ! $field_group ) {
			return new WP_Error( 'invalid_parent', 'Field group not found', [ 'status' => 400 ] );
		}

		// Generate unique key if not provided.
		if ( empty( $params['key'] ) ) {
			$params['key'] = uniqid( 'field_' );
		}

		// Set field defaults.
		$defaults = [
			'label' => '',
			'name' => '',
			'type' => 'text',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '',
				'class' => '',
				'id' => '',
			],
			'default_value' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'maxlength' => '',
		];

		$field = wp_parse_args( $params, $defaults );

		// Validate name if not set.
		if ( empty( $field['name'] ) && ! empty( $field['label'] ) ) {
			$field['name'] = sanitize_title( $field['label'] );
		}

		// Update the field.
		$field = acf_update_field( $field );

		if ( ! $field ) {
			return new WP_Error( 'cannot_create_field', 'Failed to create field', [ 'status' => 500 ] );
		}

		return new WP_REST_Response( $field, 201 );
	}

	/**
	 * Update a field
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$key = $request['key'];
		$params = $request->get_params();

		// Get existing field.
		$existing = acf_get_field( $key );
		if ( ! $existing ) {
			return new WP_Error( 'field_not_found', 'Field not found', [ 'status' => 404 ] );
		}

		// Set key from URL parameter.
		$params['key'] = $key;

		// Ensure ID is preserved.
		$params['ID'] = $existing['ID'];

		// Ensure parent is preserved if not explicitly changed.
		if ( ! isset( $params['parent'] ) ) {
			$params['parent'] = $existing['parent'];
		} elseif ( $params['parent'] !== $existing['parent'] ) {
			// If parent is changing, verify the new parent exists.
			$field_group = acf_get_field_group( $params['parent'] );
			if ( ! $field_group ) {
				return new WP_Error( 'invalid_parent', 'Field group not found', [ 'status' => 400 ] );
			}
		}

		// Merge with existing field.
		$field = array_merge( $existing, $params );

		// Update the field.
		$field = acf_update_field( $field );

		if ( ! $field ) {
			return new WP_Error( 'cannot_update_field', 'Failed to update field', [ 'status' => 500 ] );
		}

		return new WP_REST_Response( $field, 200 );
	}

	/**
	 * Delete a field
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$key = $request['key'];

		$field = acf_get_field( $key );

		if ( ! $field ) {
			return new WP_Error( 'field_not_found', 'Field not found', [ 'status' => 404 ] );
		}

		$result = acf_delete_field( $key );

		if ( ! $result ) {
			return new WP_Error( 'cannot_delete_field', 'Failed to delete field', [ 'status' => 500 ] );
		}

		return new WP_REST_Response( null, 204 );
	}
}
