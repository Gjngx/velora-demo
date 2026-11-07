<?php

namespace Angie\Modules\Sidebar\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sidebar CSS Injector Component
 *
 * Injects inline CSS for the sidebar with RTL support, responsive breakpoints,
 * accessibility features, and plugin compatibility.
 */
class Sidebar_Css_Injector {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_head', [ $this, 'enqueue_css' ] );

		add_action( 'wp_head', [ $this, 'enqueue_css' ] );

		add_action( 'elementor/editor/init', function () {
			add_action( 'wp_footer', [ $this, 'enqueue_css' ] );
		} );
	}

	public function enqueue_css() {
		$plugin_url = plugin_dir_url( __DIR__ );
		wp_enqueue_style( 'angie-sidebar-css', $plugin_url . 'assets/sidebar.css', [], ANGIE_VERSION );
	}
}
