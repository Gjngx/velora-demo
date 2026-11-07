<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;
use Angie\Modules\ConsentManager\Module as ConsentManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Post_Type_Manager {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
		add_action( 'admin_menu', [ __CLASS__, 'adjust_menu_position' ], 999 );
		add_action( 'admin_bar_menu', [ __CLASS__, 'remove_new_snippet_from_admin_bar' ], 999 );
	}

	public static function register_post_type() {
		$labels = [
			'name' => esc_html__( 'Angie Code Snippets', 'angie' ),
			'singular_name' => esc_html__( 'Angie Snippet', 'angie' ),
			'add_new' => esc_html__( 'Add New', 'angie' ),
			'add_new_item' => esc_html__( 'Add New Angie Snippet', 'angie' ),
			'edit_item' => esc_html__( 'Edit Angie Snippet', 'angie' ),
			'new_item' => esc_html__( 'New Angie Snippet', 'angie' ),
			'view_item' => esc_html__( 'View Angie Snippet', 'angie' ),
			'view_items' => esc_html__( 'View Angie Code Snippets', 'angie' ),
			'search_items' => esc_html__( 'Search Angie Code Snippets', 'angie' ),
			'not_found' => esc_html__( 'No snippets found', 'angie' ),
			'not_found_in_trash' => esc_html__( 'No snippets found in Trash', 'angie' ),
			'all_items' => esc_html__( 'Code Snippets', 'angie' ),
			'archives' => esc_html__( 'Angie Code Snippets', 'angie' ),
			'attributes' => esc_html__( 'Snippet Attributes', 'angie' ),
			'insert_into_item' => esc_html__( 'Insert into snippet', 'angie' ),
			'uploaded_to_this_item' => esc_html__( 'Uploaded to this snippet', 'angie' ),
			'menu_name' => esc_html__( 'Code Snippets', 'angie' ),
			'name_admin_bar' => esc_html__( 'Angie Snippet', 'angie' ),
		];

		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';

		$args = [
			'labels' => $labels,
			'public' => false,
			'publicly_queryable' => true,
			'exclude_from_search' => true,
			'show_ui' => true,
			'show_in_menu' => ConsentManager::has_consent() ? 'angie-app' : false,
			'show_in_admin_bar' => true,
			'show_in_nav_menus' => false,
			'show_in_rest' => false,
			'has_archive' => false,
			'rewrite' => false,
			'hierarchical' => false,
			'supports' => [ 'title' ],
			'map_meta_cap' => false,
			'capability_type' => Module::CPT_NAME,
			'capabilities' => [
				'edit_post' => $capability,
				'read_post' => $capability,
				'delete_post' => $capability,
				'edit_posts' => $capability,
				'edit_others_posts' => $capability,
				'publish_posts' => $capability,
				'read_private_posts' => $capability,
				'create_posts' => $capability,
				'delete_posts' => $capability,
				'delete_private_posts' => $capability,
				'delete_published_posts' => $capability,
				'delete_others_posts' => $capability,
				'edit_private_posts' => $capability,
				'edit_published_posts' => $capability,
			],
		];

		register_post_type( Module::CPT_NAME, $args );
	}

	public static function remove_new_snippet_from_admin_bar( $wp_admin_bar ): void {
		if ( ! $wp_admin_bar instanceof \WP_Admin_Bar ) {
			return;
		}

		$wp_admin_bar->remove_node( 'new-' . Module::CPT_NAME );
	}

	public static function adjust_menu_position() {
		global $submenu;

		if ( ! isset( $submenu['angie-app'] ) ) {
			return;
		}

		$snippets_item = null;
		$snippets_key = null;

		foreach ( $submenu['angie-app'] as $key => $item ) {
			if ( isset( $item[2] ) && strpos( $item[2], 'edit.php?post_type=' . Module::CPT_NAME ) !== false ) {
				$snippets_item = $item;
				$snippets_key = $key;
				break;
			}
		}

		if ( $snippets_item && $snippets_key !== null ) {
			unset( $submenu['angie-app'][ $snippets_key ] );

			$new_submenu = [];
			$position = 0;

			foreach ( $submenu['angie-app'] as $item ) {
				$new_submenu[ $position ] = $item;
				$position++;

				if ( $position === 1 ) {
					$new_submenu[ $position ] = $snippets_item;
					$position++;
				}
			}

			$submenu['angie-app'] = $new_submenu;
		}
	}
}
