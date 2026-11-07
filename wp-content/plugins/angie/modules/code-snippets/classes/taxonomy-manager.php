<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Taxonomy_Manager {

	const TAXONOMY_NAME = 'angie_snippet_type';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_taxonomy' ] );
	}

	public static function register_taxonomy() {
		$labels = [
			'name'              => esc_html__( 'Snippet Types', 'angie' ),
			'singular_name'     => esc_html__( 'Snippet Type', 'angie' ),
			'search_items'      => esc_html__( 'Search Snippet Types', 'angie' ),
			'all_items'         => esc_html__( 'All Snippet Types', 'angie' ),
			'edit_item'         => esc_html__( 'Edit Snippet Type', 'angie' ),
			'update_item'       => esc_html__( 'Update Snippet Type', 'angie' ),
			'add_new_item'      => esc_html__( 'Add New Snippet Type', 'angie' ),
			'new_item_name'     => esc_html__( 'New Snippet Type Name', 'angie' ),
			'menu_name'         => esc_html__( 'Snippet Types', 'angie' ),
		];

		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';

		$args = [
			'labels'            => $labels,
			'public'            => false,
			'publicly_queryable' => false,
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_in_menu'      => false,
			'show_in_nav_menus' => false,
			'show_in_rest'      => false,
			'show_admin_column' => true,
			'rewrite'           => false,
			'capabilities'      => [
				'manage_terms' => $capability,
				'edit_terms'   => $capability,
				'delete_terms' => $capability,
				'assign_terms' => $capability,
			],
		];

		register_taxonomy( self::TAXONOMY_NAME, Module::CPT_NAME, $args );

		self::create_default_terms();
	}

	public static function create_default_terms() {
		$default_terms = [
			'code-snippet' => esc_html__( 'Code Snippet', 'angie' ),
			'elementor-widget' => esc_html__( 'Elementor Widget', 'angie' ),
			'gutenberg-block' => esc_html__( 'Gutenberg Block', 'angie' ),
			'popup' => esc_html__( 'Popup', 'angie' ),
			'form' => esc_html__( 'Form', 'angie' ),
			'visual-app' => esc_html__( 'Visual App', 'angie' ),
			'web-mcp' => esc_html__( 'Web MCP', 'angie' ),
		];

		foreach ( $default_terms as $slug => $name ) {
			if ( ! term_exists( $slug, self::TAXONOMY_NAME ) ) {
				wp_insert_term( $name, self::TAXONOMY_NAME, [ 'slug' => $slug ] );
			}
		}
	}

	public static function get_valid_types() {
		return [
			'code-snippet',
			'elementor-widget',
			'gutenberg-block',
			'popup',
			'form',
			'visual-app',
			'web-mcp',
		];
	}

	public static function is_valid_type( $type ) {
		return in_array( $type, self::get_valid_types(), true );
	}
}
