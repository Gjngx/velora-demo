<?php

namespace Angie\Modules\ThemeManager\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Theme Updater
 *
 * Handles theme update from WordPress.org repository
 */
class Theme_Updater {

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
	protected $rest_base = 'themes/update';


	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}


	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, [
			'methods' => 'POST',
			'callback' => [ $this, 'update_theme' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'themes' => [
					'required' => true,
					'type' => 'array',
					'sanitize_callback' => [ $this, 'sanitize_themes_array' ],
					'description' => 'Themes to update',
				],
			],
		]);
	}


	public function permissions_check() {
		return current_user_can( 'update_themes' );
	}


	public function sanitize_themes_array( $themes ) {
		if ( ! is_array( $themes ) ) {
			return [];
		}

		return array_map( 'sanitize_text_field', $themes );
	}


	public function update_theme( \WP_REST_Request $request ) {
		$themes = $request->get_param( 'themes' );

		if ( empty( $themes ) ) {
			return new \WP_Error(
				'invalid_request',
				esc_html__( 'No themes specified for update.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		delete_site_transient( 'update_themes' );
		wp_update_themes();

		$skin = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );

		try {
			$results = $upgrader->bulk_upgrade( $themes );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'theme_update_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}

		if ( false === $results ) {
			if ( $skin->get_errors()->has_errors() ) {
				return new \WP_Error(
					'theme_update_error',
					$skin->get_error_messages(),
					[ 'status' => 500 ]
				);
			}

			return new \WP_Error(
				'theme_update_error',
				esc_html__( 'Theme update failed.', 'angie' ),
				[ 'status' => 500 ]
			);
		}

		$response = [];

		foreach ( $results as $theme => $result ) {
			if ( is_wp_error( $result ) ) {
				$response[ $theme ] = [
					'error' => $result->get_error_message(),
				];

				continue;
			}

			$response[ $theme ] = [
				'success' => true,
			];
		}

		return rest_ensure_response( [
			'success' => true,
			'data' => $response,
		] );
	}
}
