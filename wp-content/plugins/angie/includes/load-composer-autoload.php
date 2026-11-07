<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Load Angie Composer dependencies (Jetpack autoloader when available).
 *
 * @param string $plugin_root Absolute path to the Angie plugin root.
 */
function angie_load_composer_autoload( string $plugin_root ): void {
	$packages_autoload = $plugin_root . '/vendor/autoload_packages.php';
	$standard_autoload = $plugin_root . '/vendor/autoload.php';
	$autoload_path     = is_readable( $packages_autoload ) ? $packages_autoload : $standard_autoload;

	if ( ! is_readable( $autoload_path ) ) {
		return;
	}

	try {
		require_once $autoload_path;
	} catch ( \Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled
			error_log( 'Angie: Failed to load composer autoloader: ' . $e->getMessage() );
		}
	}
}
