<?php

namespace Angie\Modules\AngieSettings\Components;

use ElementorOne\Connect\Classes\GrantTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Token Provider
 *
 * Provides OAuth tokens from wp-one-package to the iframe via REST API
 */
class Token_Provider {

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
	protected $rest_base = 'token';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			'methods' => 'GET',
			'callback' => [ $this, 'get_token' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );
	}

	/**
	 * Check if user has permission to access the endpoint
	 *
	 * @param \WP_REST_Request $request The REST request object
	 * @return bool
	 */
	public function permissions_check( \WP_REST_Request $request ) {
		// WordPress REST API automatically handles cookie authentication
		// We just need to check if user is logged in and has the required capability
		return is_user_logged_in() && ( current_user_can( 'use_angie' ) || current_user_can( 'manage_options' ) );
	}

	/**
	 * Get OAuth token from wp-one-package
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_token( \WP_REST_Request $request ) {

		if ( ! class_exists( '\ElementorOne\Connect\Facade' ) ) {
			return new \WP_Error(
				'wp_one_package_not_loaded',
				'Elementor One package is not loaded',
				[ 'status' => 503 ]
			);
		}

		$registered_facades = \ElementorOne\Connect\Facade::registered();

		if ( empty( $registered_facades ) ) {
			return new \WP_Error(
				'no_facades_registered',
				'No Facade instances registered',
				[ 'status' => 503 ]
			);
		}

		$plugin_slug = reset( $registered_facades );
		$facade = \ElementorOne\Connect\Facade::get( $plugin_slug );

		if ( ! $facade ) {
			return new \WP_Error(
				'facade_not_found',
				'Facade not initialized for slug: ' . $plugin_slug . '. Available: ' . implode( ', ', $registered_facades ),
				[ 'status' => 503 ]
			);
		}

        try {

            $access_token = $facade->data()->get_access_token(GrantTypes::REFRESH_TOKEN);
            $expires_in = $this->get_token_expires_in( $access_token );
            $is_token_valid = $access_token && $expires_in > 60;

            if ( $is_token_valid ) {
                $response_data = [
                    'access_token' => $access_token,
                    'token_type' => 'Bearer',
                    'expires_in' => $expires_in,
                ];

                return new \WP_REST_Response( $response_data, 200 );
            }

            $token_data = $facade->service()->renew_access_token(
                GrantTypes::REFRESH_TOKEN
            );

            if ( empty( $token_data['access_token'] ) ) {
                return new \WP_Error(
                    'no_token',
                    'No access token available',
                    [ 'status' => 401 ]
                );
            }

			$response_data = [
				'access_token' => $token_data['access_token'],
				'token_type' => $token_data['token_type'] ?? 'Bearer',
				'expires_in' => $token_data['expires_in'] ?? 3600,
			];

			return new \WP_REST_Response( $response_data, 200 );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'token_error',
				'Failed to retrieve token: ' . $e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	private function get_token_expires_in( ?string $token ): int {
		if ( empty( $token ) ) {
			return 0;
		}

		$parts = explode( '.', $token );
		if ( count( $parts ) !== 3 ) {
			return 0;
		}

		$payload = base64_decode( strtr( $parts[1], '-_', '+/' ) );
		if ( ! $payload ) {
			return 0;
		}

		$data = json_decode( $payload, true );
		if ( ! $data || ! isset( $data['exp'] ) ) {
			return 0;
		}

		$expires_in = $data['exp'] - time();

		return max( 0, $expires_in );
	}
}

