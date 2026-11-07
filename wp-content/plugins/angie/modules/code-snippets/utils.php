<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function angie_cs_get_snippet_asset_url( $file_path, $asset_filename ): string {
	$snippet_dir = dirname( $file_path );
	$normalized_snippet_dir = wp_normalize_path( $snippet_dir );
	$normalized_content_dir = wp_normalize_path( WP_CONTENT_DIR );
	$relative_path = str_replace( $normalized_content_dir, '', $normalized_snippet_dir );
	$safe_filename = basename( $asset_filename );

	return content_url( $relative_path ) . '/' . $safe_filename;
}
