<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets_Manager {

	public static function init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_code_editor_assets' ] );
	}

	public static function enqueue_code_editor_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ( 'post' !== $screen->base && 'post-new' !== $screen->base ) ) {
			return;
		}

		$post_type = '';
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id   = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_type = get_post_type( $post_id );
		} elseif ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $screen->post_type ) ) {
			$post_type = (string) $screen->post_type;
		}

		if ( Module::CPT_NAME !== $post_type ) {
			return;
		}

		$settings = wp_enqueue_code_editor( [ 'type' => 'text/plain' ] );
		if ( false === $settings ) {
			return;
		}

		wp_enqueue_script( 'code-editor' );
		wp_enqueue_style( 'code-editor' );

		$settings_js = wp_json_encode( $settings );
		wp_add_inline_script( 'code-editor', 'window.AngieCodeEditorSettings = ' . $settings_js . ';', 'after' );
	}
}
