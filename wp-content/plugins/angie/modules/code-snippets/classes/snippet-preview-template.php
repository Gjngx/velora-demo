<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;
use Angie\Modules\CodeSnippets\PreviewSettings\Elementor_Preview_Document;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Snippet_Preview_Template {

	public static function init() {
		Snippet_Preview_Access::register_hooks();
		add_action( 'template_redirect', [ __CLASS__, 'serve_snippet_preview' ], 0 );
	}

	public static function serve_snippet_preview(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! is_singular( Module::CPT_NAME ) ) {
			return;
		}

		$snippet_post = get_queried_object();
		if ( ! $snippet_post instanceof \WP_Post || Module::CPT_NAME !== $snippet_post->post_type ) {
			return;
		}

		if ( 'publish' !== $snippet_post->post_status ) {
			wp_die(
				esc_html__( 'Snippet preview was not found.', 'angie' ),
				esc_html__( 'Preview unavailable', 'angie' ),
				[ 'response' => 404 ]
			);
		}

		if ( ! self::user_can_preview_snippet( $snippet_post ) ) {
			wp_die(
				esc_html__( 'You do not have permission to preview this snippet.', 'angie' ),
				esc_html__( 'Forbidden', 'angie' ),
				[ 'response' => 403 ]
			);
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			wp_die(
				esc_html__( 'Elementor is required for snippet previews.', 'angie' ),
				esc_html__( 'Elementor required', 'angie' ),
				[ 'response' => 500 ]
			);
		}

		$snippet_post_id = (int) $snippet_post->ID;

		if ( ! Elementor_Preview_Document::is_elementor_widget_snippet( $snippet_post_id ) ) {
			wp_die(
				esc_html__( 'Snippet preview is available for Elementor widget snippets only.', 'angie' ),
				esc_html__( 'Preview unavailable', 'angie' ),
				[ 'response' => 404 ]
			);
		}

		if ( ! Elementor_Preview_Document::ensure_snippet_registered_for_preview( $snippet_post_id ) ) {
			wp_die(
				esc_html__( 'Snippet preview is unavailable.', 'angie' ),
				esc_html__( 'Preview unavailable', 'angie' ),
				[ 'response' => 400 ]
			);
		}

		status_header( 200 );
		nocache_headers();

		Elementor_Preview_Document::bootstrap_frontend_preview( $snippet_post );
	}

	/**
	 * @param \WP_Post $post
	 * @return bool
	 */
	public static function user_can_preview_snippet( $post ) {
		return Snippet_Preview_Access::user_can_preview_snippet(
			$post,
			Snippet_Preview_Access::get_request_preview_token()
		);
	}
}
