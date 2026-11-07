<?php

namespace Angie\Modules\AngieSettings\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Page Templates
 *
 * Handles page templates retrieval via REST API
 */
class Page_Templates {

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
	protected $rest_base = 'page-templates';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			'methods' => 'GET',
			'callback' => [ $this, 'get_page_templates' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'post_type' => [
					'required' => false,
					'type' => 'string',
					'default' => 'page',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => 'Post type to get templates for',
				],
			],
		] );
	}

	/**
	 * Check if user has permission to access the endpoint
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function permissions_check( $request ) {
		$post_type = $request->get_param( 'post_type' );

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object ) {
			return false;
		}

		return current_user_can( $post_type_object->cap->edit_posts );
	}

	/**
	 * Get page templates for the specified post type
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_page_templates( $request ) {
		$post_type = $request->get_param( 'post_type' );

		try {
			$theme = \wp_get_theme();
			$templates = $theme->get_page_templates( null, $post_type );
			$templates = array_flip( $templates );

			return rest_ensure_response( [
				'success' => true,
				'data' => [
					'post_type' => $post_type,
					'templates' => $templates,
				],
			] );
		} catch ( Exception $e ) {
			return new \WP_Error(
				'template_retrieval_failed',
				esc_html__( 'Failed to retrieve page templates.', 'angie' ) . ' Error: ' . $e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}
}
