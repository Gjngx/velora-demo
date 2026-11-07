<?php
namespace Angie\Modules\CodeSnippets\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Widget_Name_Resolver {

	const ANGIE_WIDGETS_CATEGORY = 'angie-widgets';

	public static function get_widget_name_for_snippet( int $post_id ): ?string {
		$files = Snippet_Repository::get_snippet_files( $post_id );

		if ( empty( $files ) ) {
			return null;
		}

		foreach ( $files as $file ) {
			if ( ! isset( $file['name'] ) || ! str_ends_with( $file['name'], '.php' ) ) {
				continue;
			}

			$content = isset( $file['content_b64'] ) ? base64_decode( $file['content_b64'], true ) : null;

			if ( ! $content ) {
				continue;
			}

			$widget_name = self::extract_widget_name_from_content( $content );

			if ( $widget_name ) {
				return $widget_name;
			}
		}

		return null;
	}

	private static function extract_widget_name_from_content( string $content ): ?string {
		$pattern = '/function\s+get_name\s*\(\s*\)\s*(?::\s*\w+\s*)?\{[^}]*return\s+[\'"]([^\'"]+)[\'"]/s';

		if ( preg_match( $pattern, $content, $matches ) ) {
			return $matches[1];
		}

		return null;
	}
}
