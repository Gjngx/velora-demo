<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Markdown_Resource {

	public static function load( string $path ) {
		if ( ! is_readable( $path ) ) {
			return new \WP_Error(
				'resource_not_found',
				sprintf(
					/* translators: %s: file path */
					esc_html__( 'Resource file not found: %s', 'angie' ),
					$path
				),
				[ 'status' => 404 ]
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local markdown guide files.
		$content = file_get_contents( $path );
		if ( false === $content ) {
			return new \WP_Error(
				'resource_read_failed',
				esc_html__( 'Failed to read resource file.', 'angie' ),
				[ 'status' => 500 ]
			);
		}

		return $content;
	}
}
