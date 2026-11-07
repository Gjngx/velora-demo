<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cache_Manager {

	const CACHE_KEY_PUBLISHED_SNIPPET_IDS = 'angie_published_snippet_ids';

	public static function init() {
		add_action( 'save_post_' . Module::CPT_NAME, [ __CLASS__, 'clear_published_snippet_cache' ], 999 );
		add_action( 'wp_trash_post', [ __CLASS__, 'clear_published_cache_on_trash' ] );
		add_action( 'untrash_post', [ __CLASS__, 'clear_published_cache_on_trash' ] );
	}

	public static function get_published_snippet_ids() {
		$published_ids = get_transient( self::CACHE_KEY_PUBLISHED_SNIPPET_IDS );

		if ( false === $published_ids ) {
			$args = [
				'post_type' => Module::CPT_NAME,
				'post_status' => 'publish',
				'fields' => 'ids',
				'posts_per_page' => -1,
				'no_found_rows' => true,
			];
			$published_ids = get_posts( $args );

			set_transient( self::CACHE_KEY_PUBLISHED_SNIPPET_IDS, $published_ids, 5 * HOUR_IN_SECONDS );
		}

		return $published_ids;
	}

	public static function clear_published_snippet_cache() {
		delete_transient( self::CACHE_KEY_PUBLISHED_SNIPPET_IDS );
	}

	public static function clear_published_cache_on_trash( $post_id ) {
		$post = get_post( $post_id );
		if ( $post && Module::CPT_NAME === $post->post_type ) {
			delete_transient( self::CACHE_KEY_PUBLISHED_SNIPPET_IDS );
		}
	}
}
