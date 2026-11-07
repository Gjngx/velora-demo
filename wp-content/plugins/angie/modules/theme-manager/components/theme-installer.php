<?php

namespace Angie\Modules\ThemeManager\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Theme Installer
 *
 * Handles theme installation from WordPress.org repository
 */
class Theme_Installer {

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
	protected $rest_base = 'themes/install';


	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}


	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, [
			'methods' => 'POST',
			'callback' => [ $this, 'install_theme' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'slug' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => 'Theme slug to install',
				],
			],
		]);
	}


	public function permissions_check() {
		return current_user_can( 'install_themes' );
	}


	public function install_theme( $request ) {
		$slug = $request->get_param( 'slug' );

		$installed_themes = wp_get_themes();
		foreach ( $installed_themes as $theme ) {
			if ( $theme->get_stylesheet() === $slug ) {
				return new \WP_Error(
					'theme_already_installed',
					esc_html__( 'Theme is already installed.', 'angie' ),
					[ 'status' => 409 ]
				);
			}
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$api = \themes_api( 'theme_information', [ 'slug' => $slug ] );
		if ( is_wp_error( $api ) ) {
			return new \WP_Error(
				'theme_api_error',
				$api->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		$skin = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				'theme_installation_error',
				$result->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		if ( is_wp_error( $skin->result ) ) {
			return new \WP_Error(
				'theme_installation_error',
				$skin->result->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		if ( $skin->get_errors()->has_errors() ) {
			return new \WP_Error(
				'theme_installation_error',
				$skin->get_error_messages(),
				[ 'status' => 500 ]
			);
		}

		if ( false === $result ) {
			return new \WP_Error(
				'theme_installation_error',
				esc_html__( 'Theme installation failed.', 'angie' ),
				[ 'status' => 500 ]
			);
		}

		$theme = wp_get_theme( $slug );

		$response = [
			'success' => true,
			'data' => [
				'name' => $theme->get( 'Name' ),
				'stylesheet' => $theme->get_stylesheet(),
				'message' => esc_html__( 'Theme installed successfully.', 'angie' ),
			],
		];

		return rest_ensure_response( $response );
	}
}
