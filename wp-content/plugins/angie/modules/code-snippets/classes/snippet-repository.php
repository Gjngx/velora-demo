<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Snippet_Repository {

	public static function find_snippet_post_by_id( $id ) {
		$post = get_post( (int) $id );

		if ( ! $post || Module::CPT_NAME !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	public static function find_snippet_post_by_artifact_id( $artifact_id ) {
		$args = [
			'post_type'      => Module::CPT_NAME,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'   => '_angie_snippet_artifact_id',
					'value' => sanitize_text_field( $artifact_id ),
				],
			],
		];

		$posts = get_posts( $args );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	public static function get_snippet_slug_from_post( $post ) {
		$slug = $post->post_name;
		if ( empty( $slug ) ) {
			$slug = sanitize_title( $post->post_title );
		}
		if ( empty( $slug ) ) {
			$slug = 'snippet-' . $post->ID;
		}
		return $slug;
	}

	public static function create_snippet( $title ) {
		return wp_insert_post( [
			'post_title'  => sanitize_text_field( $title ),
			'post_type'   => Module::CPT_NAME,
			'post_status' => 'publish',
		], true );
	}

	public static function delete_snippet( $post_id ) {
		return wp_delete_post( $post_id, true );
	}

	public static function get_snippet_files( $post_id ) {
		$files = get_post_meta( $post_id, '_angie_snippet_files', true );
		if ( ! is_array( $files ) ) {
			$files = [];
		}
		return $files;
	}

	public static function update_snippet_files( $post_id, $files ) {
		return update_post_meta( $post_id, '_angie_snippet_files', $files );
	}

	public static function get_snippet_files_by_post( $post ) {
		$files = self::get_snippet_files( $post->ID );
		foreach ( $files as &$file ) {
			if ( ! isset( $file['content_b64'] ) || ! is_string( $file['content_b64'] ) ) {
				$file['content'] = '';
				continue;
			}
			$decoded = base64_decode( $file['content_b64'], true );
			$file['content'] = ( false === $decoded ) ? '' : $decoded;
		}
		return $files;
	}

	public static function get_all_snippets( $type = null ) {
		$args = [
			'post_type'      => Module::CPT_NAME,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( ! empty( $type ) && Taxonomy_Manager::is_valid_type( $type ) ) {
			$args['tax_query'] = [
				[
					'taxonomy' => Taxonomy_Manager::TAXONOMY_NAME,
					'field'    => 'slug',
					'terms'    => $type,
				],
			];
		}

		return get_posts( $args );
	}

	private static function build_file_list( $files ) {
		$file_list = [];
		foreach ( $files as $file ) {
			$file_list[] = [
				'name' => $file['name'],
				'size' => isset( $file['content_b64'] ) ? strlen( base64_decode( $file['content_b64'], true ) ) : 0,
			];
		}
		return $file_list;
	}

	public static function get_snippet_data( $post ) {
		$files = self::get_snippet_files( $post->ID );
		$terms = wp_get_object_terms( $post->ID, Taxonomy_Manager::TAXONOMY_NAME, [ 'fields' => 'slugs' ] );
		$types = is_wp_error( $terms ) ? [] : $terms;
		$timestamps = Dev_Mode_Manager::get_snippet_environment_timestamps( $post->ID );
		$is_elementor_widget = ! is_wp_error( $terms ) && in_array( 'elementor-widget', $terms, true );

		$artifact_id = get_post_meta( $post->ID, '_angie_snippet_artifact_id', true );
		$version = get_post_meta( $post->ID, '_angie_snippet_version', true );

		$data = [
			'id'                    => $post->ID,
			'slug'                  => self::get_snippet_slug_from_post( $post ),
			'title'                 => $post->post_title,
			'status'                => $post->post_status,
			'types'                 => $types,
			'files'                 => self::build_file_list( $files ),
			'deploymentStatus'      => $timestamps['status'],
			'artifactId'            => $artifact_id ? $artifact_id : null,
			'version'               => $version ? (int) $version : null,
			'createdAt'             => $post->post_date_gmt,
			'isOwnedByCurrentUser'  => (int) $post->post_author === get_current_user_id(),
		];

		if ( $is_elementor_widget ) {
			$data['widgetName'] = Widget_Name_Resolver::get_widget_name_for_snippet( $post->ID );
		}

		return apply_filters( 'angie_snippet_data', $data, $post );
	}

	public static function get_file_by_name( $post_id, $filename ) {
		$files = self::get_snippet_files( $post_id );

		foreach ( $files as $file ) {
			if ( $file['name'] === $filename ) {
				return $file;
			}
		}

		return null;
	}

	public static function merge_snippet_files( $existing_files, $new_files ) {
		$merged_by_name = [];

		foreach ( $existing_files as $file ) {
			$merged_by_name[ $file['name'] ] = $file;
		}

		foreach ( $new_files as $file ) {
			$merged_by_name[ $file['name'] ] = $file;
		}

		return array_values( $merged_by_name );
	}

	public static function has_main_php_file( $files ) {
		foreach ( $files as $file ) {
			if ( 'main.php' === $file['name'] ) {
				return true;
			}
		}

		return false;
	}

	public static function get_snippet_file_list( $post_id ) {
		$files = self::get_snippet_files( $post_id );

		return self::build_file_list( $files );
	}
}
