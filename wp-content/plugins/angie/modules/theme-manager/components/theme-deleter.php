<?php

namespace Angie\Modules\ThemeManager\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Theme Deleter
 *
 * Handles theme deletion
 */
class Theme_Deleter {

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
	protected $rest_base = 'themes/delete';


	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}


	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, [
			'methods' => 'POST',
			'callback' => [ $this, 'delete_theme' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'stylesheet' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => 'Theme stylesheet identifier',
				],
			],
		]);
	}


	public function permissions_check() {
		return current_user_can( 'delete_themes' );
	}


	public function delete_theme( $request ) {
		$stylesheet = $request->get_param( 'stylesheet' );

		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new \WP_Error(
				'theme_not_found',
				esc_html__( 'Theme not found.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		if ( $theme->get_stylesheet() === get_stylesheet() ) {
			return new \WP_Error(
				'theme_is_active',
				esc_html__( 'Cannot delete the active theme.', 'angie' ),
				[ 'status' => 409 ]
			);
		}

		if ( $theme->get_stylesheet() === WP_DEFAULT_THEME ) {
			return new \WP_Error(
				'theme_is_default',
				esc_html__( 'Cannot delete the default theme.', 'angie' ),
				[ 'status' => 409 ]
			);
		}

		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$result = \delete_theme( $stylesheet );

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				'theme_deletion_error',
				$result->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		$response = [
			'success' => true,
			'data' => [
				'stylesheet' => $stylesheet,
				'message' => esc_html__( 'Theme deleted successfully.', 'angie' ),
			],
		];

		return rest_ensure_response( $response );
	}
}
