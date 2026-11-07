<?php
/**
 * Elementor Settings REST API Component
 *
 * @package Angie\Modules\AngieSettings\Components
 */

namespace Angie\Modules\AngieSettings\Components;

use Angie\Includes\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor Settings Component
 *
 * Provides REST API endpoints for Elementor-related settings and configuration
 */
class Elementor_Settings {

	/**
	 * Route namespace
	 *
	 * @var string
	 */
	protected $namespace = 'angie/v1';

	/**
	 * Route base
	 *
	 * @var string
	 */
	protected $rest_base = 'elementor-settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		\add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Get supported post types
		\register_rest_route( $this->namespace, '/' . $this->rest_base . '/supported-post-types', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_supported_post_types' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );
	}

	/**
	 * Check if user has permission to access the endpoint
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return \current_user_can( 'use_angie' );
	}

	/**
	 * Get Elementor supported post types
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_supported_post_types( $request ) {
		if ( ! Utils::is_plugin_active( 'elementor/elementor.php' ) ) {
			return new \WP_Error(
				'elementor_not_active',
				'Elementor plugin is not active',
				[ 'status' => 404 ]
			);
		}

		$supported_post_types = \get_option( 'elementor_cpt_support', false );

		// If option doesn't exist, dynamically determine supported post types
		if ( false === $supported_post_types ) {
			$supported_post_types = $this->get_default_elementor_supported_post_types();
		}

		if ( ! is_array( $supported_post_types ) ) {
			$supported_post_types = [];
		}

		return \rest_ensure_response( $supported_post_types );
	}

	/**
	 * Get default Elementor supported post types by checking post type support
	 *
	 * @return array
	 */
	private function get_default_elementor_supported_post_types() {
		$all_post_types = \get_post_types( [ 'public' => true ], 'names' );
		$supported_post_types = [];

		foreach ( $all_post_types as $post_type ) {
			if ( \post_type_supports( $post_type, 'elementor' ) ) {
				$supported_post_types[] = $post_type;
			}
		}

		// If no post types have explicit Elementor support, check Elementor's defaults
		if ( empty( $supported_post_types ) && class_exists( '\Elementor\Plugin' ) ) {
			// Get Elementor's default supported post types from its settings
			$elementor_settings = \Elementor\Plugin::$instance->documents->get_document_types();
			foreach ( $elementor_settings as $document_type ) {
				if ( method_exists( $document_type, 'get_post_type_title' ) ) {
					$post_type = $document_type::get_post_type();
					if ( $post_type && ! in_array( $post_type, $supported_post_types, true ) ) {
						$supported_post_types[] = $post_type;
					}
				}
			}
		}

		return $supported_post_types;
	}
}
