<?php

namespace Angie\Modules\CodeSnippets\PreviewSettings;

use Angie\Modules\CodeSnippets\Classes\Taxonomy_Manager;
use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Snippet_Type_Resolver {

	/**
	 * @param int    $post_id
	 * @param string $type
	 * @return bool
	 */
	public static function has_type( int $post_id, string $type ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$terms = wp_get_object_terms( $post_id, Taxonomy_Manager::TAXONOMY_NAME, [ 'fields' => 'slugs' ] );

		return ! is_wp_error( $terms ) && in_array( $type, $terms, true );
	}

	/**
	 * @param int $post_id
	 * @return bool
	 */
	public static function is_elementor_widget_snippet( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || Module::CPT_NAME !== $post->post_type ) {
			return false;
		}

		return self::has_type( $post_id, 'elementor-widget' );
	}
}
