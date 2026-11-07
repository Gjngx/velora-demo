<?php

namespace Angie\Modules\ThemeManager\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Theme Activator
 *
 * Handles theme activation and deactivation
 */
class Theme_Activator {

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	protected $namespace = 'angie/v1';

	/**
	 * REST API base for activation
	 *
	 * @var string
	 */
	protected $rest_base_activate = 'themes/activate';

	/**
	 * REST API base for deactivation
	 *
	 * @var string
	 */
	protected $rest_base_deactivate = 'themes/deactivate';


	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}


	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base_activate, [
			'methods' => 'POST',
			'callback' => [ $this, 'activate_theme' ],
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

		register_rest_route($this->namespace, '/' . $this->rest_base_deactivate, [
			'methods' => 'POST',
			'callback' => [ $this, 'deactivate_theme' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		]);
	}


	public function permissions_check() {
		return current_user_can( 'switch_themes' );
	}


	public function activate_theme( $request ) {
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
				'theme_already_active',
				esc_html__( 'Theme is already active.', 'angie' ),
				[ 'status' => 409 ]
			);
		}

		switch_theme( $stylesheet );

		if ( get_stylesheet() !== $stylesheet ) {
			return new \WP_Error(
				'theme_activation_failed',
				esc_html__( 'Theme activation failed.', 'angie' ),
				[ 'status' => 500 ]
			);
		}

		$response = [
			'success' => true,
			'data' => [
				'name' => $theme->get( 'Name' ),
				'stylesheet' => $theme->get_stylesheet(),
				'message' => esc_html__( 'Theme activated successfully.', 'angie' ),
			],
		];

		return rest_ensure_response( $response );
	}


	public function deactivate_theme() {
		$current_theme = wp_get_theme();
		$current_stylesheet = $current_theme->get_stylesheet();

		$default_theme = WP_DEFAULT_THEME;

		if ( $current_stylesheet === $default_theme ) {
			return new \WP_Error(
				'theme_already_default',
				esc_html__( 'Current theme is already the default theme.', 'angie' ),
				[ 'status' => 409 ]
			);
		}

		switch_theme( $default_theme );

		if ( get_stylesheet() !== $default_theme ) {
			return new \WP_Error(
				'theme_deactivation_failed',
				esc_html__( 'Theme deactivation failed.', 'angie' ),
				[ 'status' => 500 ]
			);
		}

		$response = [
			'success' => true,
			'data' => [
				'previous' => $current_stylesheet,
				'current' => $default_theme,
				'message' => esc_html__( 'Theme deactivated successfully. Switched to default theme.', 'angie' ),
			],
		];

		return rest_ensure_response( $response );
	}
}
