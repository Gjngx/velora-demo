<?php

namespace Angie\Modules\AcfRestApi\Components;

use WP_REST_Response;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ACF REST API Field Group Controller
 */
class FieldGroup extends Base {
	/**
	 * REST API base
	 *
	 * @var string
	 */
	protected $rest_base = 'field-groups';

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
	}

	/**
	 * Get all field groups
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		$field_groups = acf_get_field_groups();

		return new WP_REST_Response( $field_groups, 200 );
	}

	/**
	 * Get a specific field group
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$key = $request['key'];

		$field_group = acf_get_field_group( $key );

		if ( ! $field_group ) {
			return new WP_Error( 'field_group_not_found', 'Field group not found', [ 'status' => 404 ] );
		}

		// Get fields belonging to this group.
		$fields = acf_get_fields( $field_group );
		$field_group['fields'] = $fields;

		return new WP_REST_Response( $field_group, 200 );
	}

	/**
	 * Create a field group
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$params = $request->get_params();

		// Set defaults for a new field group using patterns from ACF_Field_Group.
		if ( empty( $params['key'] ) ) {
			$params['key'] = uniqid( 'group_' );
		}

		// Apply default settings from ACF.
		$defaults = [
			'title' => '',
			'fields' => [],
			'location' => [],
			'menu_order' => 0,
			'position' => 'normal',
			'style' => 'default',
			'label_placement' => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen' => [],
			'active' => true,
			'description' => '',
			'show_in_rest' => false,
		];

		$field_group = wp_parse_args( $params, $defaults );

		// Save fields separately if provided.
		$fields = [];
		if ( ! empty( $field_group['fields'] ) ) {
			$fields = $field_group['fields'];
			$field_group['fields'] = []; // Remove fields for initial save.
		}

		// Create the field group.
		$field_group = acf_update_field_group( $field_group );

		if ( ! $field_group ) {
			return new WP_Error( 'cannot_create_field_group', 'Failed to create field group', [ 'status' => 500 ] );
		}

		// Add fields to the group if provided.
		if ( ! empty( $fields ) ) {
			foreach ( $fields as $i => $field ) {
				$field['parent'] = $field_group['ID'];
				$field['menu_order'] = $i;
				// Auto-generate a unique key if not provided.
				if ( empty( $field['key'] ) ) {
					$field['key'] = 'field_' . uniqid();
				}
				acf_update_field( $field );
			}

			// Get updated field group with fields.
			$field_group = acf_get_field_group( $field_group['key'] );
			$field_group['fields'] = acf_get_fields( $field_group );
		}

		return new WP_REST_Response( $field_group, 201 );
	}

	/**
	 * Update a field group
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$key = $request['key'];
		$params = $request->get_params();
		$params['key'] = $key;

		// Check if field group exists.
		$existing = acf_get_field_group( $key );
		if ( ! $existing ) {
			return new WP_Error( 'field_group_not_found', 'Field group not found', [ 'status' => 404 ] );
		}

		// Handle fields separately if provided.
		$fields = [];
		if ( isset( $params['fields'] ) ) {
			$fields = $params['fields'];
			unset( $params['fields'] ); // Remove fields for the field group update.
		}

		// Update field group.
		$field_group = array_merge( $existing, $params );
		$field_group = acf_update_field_group( $field_group );

		if ( ! $field_group ) {
			return new WP_Error( 'cannot_update_field_group', 'Failed to update field group', [ 'status' => 500 ] );
		}

		// Update fields if provided.
		if ( ! empty( $fields ) ) {
			// Get existing fields to compare.
			$existing_fields = acf_get_fields( $field_group );
			$existing_keys = wp_list_pluck( $existing_fields, 'key' );

			// Process fields.
			foreach ( $fields as $i => $field ) {
				$field['parent'] = $field_group['key'];
				$field['menu_order'] = $i;
				acf_update_field( $field );
			}

			// Get field keys from the request.
			$new_keys = wp_list_pluck( $fields, 'key' );

			// Delete fields that are not in the new set.
			foreach ( $existing_keys as $existing_key ) {
				if ( ! in_array( $existing_key, $new_keys, true ) ) {
					acf_delete_field( $existing_key );
				}
			}

			// Get updated field group with fields.
			$field_group = acf_get_field_group( $field_group['key'] );
			$field_group['fields'] = acf_get_fields( $field_group );
		}

		return new WP_REST_Response( $field_group, 200 );
	}

	/**
	 * Delete a field group
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$key = $request['key'];

		$field_group = acf_get_field_group( $key );

		if ( ! $field_group ) {
			return new WP_Error( 'field_group_not_found', 'Field group not found', [ 'status' => 404 ] );
		}

		acf_delete_field_group( $field_group['ID'] );

		return new WP_REST_Response( null, 204 );
	}
}
