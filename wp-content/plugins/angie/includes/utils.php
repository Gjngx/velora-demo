<?php

namespace Angie\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Utils {
	/**
	 * Safely retrieve and sanitize a value from $_GET
	 *
	 * @param string $key The query variable key.
	 * @param string $default_value The default value if not set.
	 * @return string Sanitized value or default.
	 */
	public static function get_sanitized_query_var( $key, $default_value = '' ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ $key ] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}
		return $default_value;
	}

	public static function is_plugin_active( $plugin_path ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin_path );
	}

	public static function get_asset_url( $file, $path ) {
		return plugin_dir_url( $path ) . 'assets/' . $file;
	}
}
