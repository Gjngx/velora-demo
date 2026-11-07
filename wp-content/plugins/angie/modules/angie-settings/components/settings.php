<?php

namespace Angie\Modules\AngieSettings\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Angie Settings
 *
 * Handles Angie settings storage and retrieval via REST API
 */
class Settings {

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
	protected $rest_base = 'settings';

	/**
	 * Option prefix for all Angie settings
	 *
	 * @var string
	 */
	const OPTION_PREFIX = '_angie_';

	/**
	 * Option name for website UUID
	 *
	 * @var string
	 */
	const WEBSITE_UUID_OPTION = '_angie_website_uuid';


	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}


	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<name>[a-zA-Z0-9_-]+)', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_setting' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'name' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => 'Setting name',
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<name>[a-zA-Z0-9_-]+)', [
			'methods' => 'POST',
			'callback' => [ $this, 'update_setting' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'name' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => 'Setting name',
				],
				'value' => [
					'required' => true,
					'description' => 'Setting value as JSON',
				],
			],
		] );

		register_rest_route( $this->namespace, '/website-uuid', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_website_uuid_endpoint' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );
	}

	/**
	 * Check if user has permission to access the endpoint
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get or generate the website unique ID
	 *
	 * @return string The website UUID
	 */
	public function get_website_uuid() {
		$uuid = \get_option( self::WEBSITE_UUID_OPTION );

		if ( empty( $uuid ) ) {
			$uuid = \wp_generate_uuid4();
			\update_option( self::WEBSITE_UUID_OPTION, $uuid );
		}

		return $uuid;
	}

	/**
	 * Get a specific Angie setting
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_setting( $request ) {
		$name = $request->get_param( 'name' );
		$option_name = self::OPTION_PREFIX . $name;

		$value = \get_option( $option_name, null );

		if ( null === $value ) {
			$value = '{}';
		}

		$decoded_value = json_decode( $value, true );

		return rest_ensure_response( [
			'success' => true,
			'data' => [
				'name' => $name,
				'value' => $decoded_value,
			],
		] );
	}

	/**
	 * Update an Angie setting
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_setting( $request ) {
		// Handle JSON body parameters by merging them into request parameters.
		$json_params = $request->get_json_params();
		if ( $json_params ) {
			$request->set_body_params( $json_params );
		}

		$name = $request->get_param( 'name' );
		$value = $request->get_param( 'value' );

		// Validate required parameters.
		if ( null === $value ) {
			return new \WP_Error(
				'missing_value',
				esc_html__( 'Missing value parameter.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		$option_name = self::OPTION_PREFIX . $name;

		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new \WP_Error(
					'invalid_json',
					esc_html__( 'Invalid JSON format.', 'angie' ),
					[ 'status' => 400 ]
				);
			}
			$json_value = $value;
		} else {
			$json_value = wp_json_encode( $value );
			if ( false === $json_value ) {
				return new \WP_Error(
					'json_encode_failed',
					esc_html__( 'Failed to encode value to JSON.', 'angie' ),
					[ 'status' => 500 ]
				);
			}
		}

		$updated = \update_option( $option_name, $json_value );

		if ( ! $updated && \get_option( $option_name ) !== $json_value ) {
			return new \WP_Error(
				'update_failed',
				esc_html__( 'Failed to update setting.', 'angie' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response( [
			'success' => true,
			'data' => [
				'name' => $name,
				'value' => is_string( $value ) ? json_decode( $value, true ) : $value,
				'message' => esc_html__( 'Setting updated successfully.', 'angie' ),
			],
		] );
	}

	/**
	 * Get website UUID endpoint
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_website_uuid_endpoint() {
		return rest_ensure_response( [
			'success' => true,
			'data' => [
				'uuid' => $this->get_website_uuid(),
			],
		] );
	}
}
