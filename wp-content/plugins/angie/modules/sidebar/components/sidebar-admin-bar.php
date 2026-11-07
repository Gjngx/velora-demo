<?php

namespace Angie\Modules\Sidebar\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sidebar Admin Bar Component
 *
 * Integrates the sidebar toggle button into the WordPress admin bar
 * instead of creating a standalone button.
 */
class Sidebar_Admin_Bar {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_bar_menu', [ $this, 'add_toggle_to_admin_bar' ], 999 );
		
		// Elementor editor integration (admin_bar_menu hook doesn't fire there)
		add_action( 'elementor/editor/init', function () {
			add_action( 'wp_footer', [ $this, 'add_toggle_to_admin_bar' ] );
		} );
	}

	/**
	 * Check if admin bar integration should be active
	 *
	 * @return bool True if admin bar integration should be active
	 */
	private function should_add_to_admin_bar(): bool {
		if ( ! current_user_can( 'use_angie' ) ) {
			return false;
		}

		if ( ! is_admin_bar_showing() ) {
			return false;
		}

		return true;
	}

	/**
	 * Add toggle button to WordPress admin bar or inject for Elementor
	 *
	 * @param \WP_Admin_Bar|null $wp_admin_bar WordPress admin bar object (null when called from Elementor).
	 */
	public function add_toggle_to_admin_bar( $wp_admin_bar ): void {
		if ( ! $this->should_add_to_admin_bar() ) {
			return;
		}

		if ( $wp_admin_bar ) {
			$wp_admin_bar->add_node( [
				'id'    => 'angie-sidebar-toggle',
				'title' => '',
				'href'  => '#',
				'meta'  => [
					'class' => 'angie-sidebar-toggle-item',
					'title' => '',
				],
			] );
		} else {
			// No admin bar object = Elementor editor context, just create the basic element JavaScript needs
			echo '<div id="wp-admin-bar-angie-sidebar-toggle" class="angie-sidebar-toggle-item" style="display: none;">
				<a href="#" class="ab-item" title=""></a>
			</div>';
		}
	}
}
