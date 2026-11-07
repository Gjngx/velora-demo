<?php

namespace Angie\Modules\ElementorCore\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs elementor_library_type taxonomy after Angie MCP template create.
 *
 * Elementor documents->create() sets _elementor_template_type meta but may not call
 * wp_set_object_terms. WP REST also ignores elementor_library_type without show_in_rest.
 */
class Elementor_Library_Type_Sync {

	protected $namespace = 'angie/v1';

	protected $rest_base = 'elementor-library';

	public function __construct() {
		\add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/sync-type',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'sync_type' ],
				'permission_callback' => function ( $request ) {
					return \current_user_can( 'edit_post', (int) $request['id'] );
				},
				'args'                => [
					'id'            => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
					],
					'template_type' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	public function sync_type( \WP_REST_Request $request ) {
		$post_id       = (int) $request['id'];
		$template_type = $request->get_param( 'template_type' );

		if ( 'elementor_library' !== \get_post_type( $post_id ) ) {
			return new \WP_Error(
				'invalid_post_type',
				'Post must be elementor_library.',
				[ 'status' => 400 ]
			);
		}

		\update_post_meta( $post_id, '_elementor_template_type', $template_type );

		$term_result = \wp_set_object_terms( $post_id, $template_type, 'elementor_library_type', false );

		if ( \is_wp_error( $term_result ) ) {
			return $term_result;
		}

		$terms = \wp_get_object_terms(
			$post_id,
			'elementor_library_type',
			[
				'fields' => 'slugs',
			]
		);

		if ( \is_wp_error( $terms ) ) {
			return $terms;
		}

		return \rest_ensure_response(
			[
				'post_id'       => $post_id,
				'template_type' => $template_type,
				'terms'         => $terms,
			]
		);
	}
}
