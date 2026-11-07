<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;
use Angie\Modules\CodeSnippets\PreviewSettings\Snippet_Type_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token-gated preview URLs and access checks for Angie Snippet front previews.
 */
class Snippet_Preview_Access {

	const PREVIEW_QUERY_VAR = 'angie_preview';
	const PREVIEW_TOKEN_META = '_angie_snippet_preview_token';

	public static function register_hooks(): void {
		add_action( 'save_post_' . Module::CPT_NAME, [ __CLASS__, 'ensure_preview_token_on_save' ], 20, 3 );
		add_filter( 'angie_snippet_data', [ __CLASS__, 'add_preview_url_to_snippet_data' ], 10, 2 );
	}

	/**
	 * @param int      $post_id
	 * @param \WP_Post $post
	 * @param bool     $update
	 */
	public static function ensure_preview_token_on_save( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! Snippet_Type_Resolver::has_type( (int) $post_id, 'elementor-widget' ) ) {
			return;
		}

		if ( get_post_meta( $post_id, self::PREVIEW_TOKEN_META, true ) ) {
			return;
		}

		update_post_meta( $post_id, self::PREVIEW_TOKEN_META, wp_generate_password( 48, false, false ) );
	}

	/**
	 * @param array<string, mixed> $data
	 * @param \WP_Post             $post
	 * @return array<string, mixed>
	 */
	public static function add_preview_url_to_snippet_data( $data, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return $data;
		}

		$types = $data['types'] ?? [];
		if ( ! is_array( $types ) || ! in_array( 'elementor-widget', $types, true ) ) {
			return $data;
		}

		$url = self::get_preview_url( $post );
		if ( '' !== $url ) {
			$data['previewUrl'] = $url;
		}

		return $data;
	}

	/**
	 * @param \WP_Post $post
	 * @return string
	 */
	public static function get_preview_url( $post ) {
		if ( ! $post instanceof \WP_Post || Module::CPT_NAME !== $post->post_type ) {
			return '';
		}

		if ( 'publish' !== $post->post_status ) {
			return '';
		}

		$token = get_post_meta( $post->ID, self::PREVIEW_TOKEN_META, true );
		if ( empty( $token ) || ! is_string( $token ) ) {
			if ( ! Module::current_user_can_manage_snippets() ) {
				return '';
			}
			$token = wp_generate_password( 48, false, false );
			update_post_meta( $post->ID, self::PREVIEW_TOKEN_META, $token );
		}

		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}

		return add_query_arg( self::PREVIEW_QUERY_VAR, $token, $permalink );
	}

	/**
	 * @return string
	 */
	public static function get_request_preview_token() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public preview token, not a form nonce
		$preview_token = sanitize_text_field( wp_unslash( $_GET[ self::PREVIEW_QUERY_VAR ] ?? '' ) );

		return $preview_token;
	}

	/**
	 * @param \WP_Post $post
	 * @param string   $request_preview_token Token from the preview URL query string.
	 * @return bool
	 */
	public static function user_can_preview_snippet( $post, $request_preview_token = '' ) {
		if ( ! $post instanceof \WP_Post || Module::CPT_NAME !== $post->post_type ) {
			return false;
		}

		if ( Module::current_user_can_manage_snippets() ) {
			return true;
		}

		$stored = get_post_meta( $post->ID, self::PREVIEW_TOKEN_META, true );

		if ( '' === $request_preview_token || '' === $stored || ! is_string( $stored ) ) {
			return false;
		}

		return hash_equals( $stored, $request_preview_token );
	}
}
