<?php

namespace Angie\Modules\CodeSnippets\PreviewSettings;

use Angie\Modules\CodeSnippets\Classes\Snippet_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor_Preview_Settings {

	const META_KEY = '_angie_snippet_elementor_preview_settings';

	/**
	 * @param int $post_id
	 * @return array<string, mixed>
	 */
	public static function get( $post_id ) {
		$raw = get_post_meta( (int) $post_id, self::META_KEY, true );
		if ( '' === $raw || ! is_string( $raw ) ) {
			return [];
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * @param int                    $post_id
	 * @param array<string, mixed>   $settings
	 * @return bool
	 */
	public static function update( $post_id, array $settings ) {
		if ( empty( $settings ) ) {
			return delete_post_meta( (int) $post_id, self::META_KEY );
		}

		$encoded = wp_json_encode( $settings );
		if ( false === $encoded ) {
			return false;
		}

		return (bool) update_post_meta( (int) $post_id, self::META_KEY, $encoded );
	}

	/**
	 * @param int                    $post_id
	 * @param object                 $widget_type
	 * @param array<string, mixed>|null $settings
	 * @return array<string, mixed>
	 */
	public static function resolve_settings( int $post_id, object $widget_type, ?array $settings = null ): array {
		$defaults = method_exists( $widget_type, 'get_default_settings' )
			? (array) $widget_type->get_default_settings()
			: [];

		if ( null === $settings ) {
			$settings = self::get( $post_id );
		}

		return Elementor_Preview_Settings_Fallback::fill_missing(
			$widget_type,
			array_merge( $defaults, $settings )
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return array<string, mixed>|null|\WP_Error
	 */
	public static function parse_from_request( $request ) {
		$preview_settings_param = $request->get_param( 'elementor_preview_settings' );
		if ( null === $preview_settings_param ) {
			return null;
		}

		return self::sanitize( $preview_settings_param );
	}

	/**
	 * @param int              $post_id
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|null
	 */
	public static function save_from_request( $post_id, $request ) {
		$settings = self::parse_from_request( $request );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		self::save_if_needed( (int) $post_id, $settings );

		return null;
	}

	/**
	 * @param \WP_Post           $post
	 * @param array<string,mixed> $base
	 * @return array<string,mixed>
	 */
	public static function enrich_files_update_response( $post, array $base ) {
		$snippet_data = Snippet_Repository::get_snippet_data( $post );
		$settings     = self::get( $post->ID );

		return array_merge(
			$base,
			[
				'post_id' => $post->ID,
				'slug'    => $snippet_data['slug'] ?? Snippet_Repository::get_snippet_slug_from_post( $post ),
			],
			array_filter(
				[
					'previewUrl'               => $snippet_data['previewUrl'] ?? null,
					'widgetName'               => $snippet_data['widgetName'] ?? null,
					'elementorPreviewSettings' => ! empty( $settings ) ? $settings : null,
				],
				static fn( $value ) => null !== $value
			)
		);
	}

	/**
	 * @param int                         $post_id
	 * @param array<string, mixed>|null   $settings
	 */
	private static function save_if_needed( $post_id, $settings ) {
		$post_id = (int) $post_id;

		if ( null === $settings ) {
			return;
		}

		if ( ! Snippet_Type_Resolver::has_type( $post_id, 'elementor-widget' ) ) {
			self::update( $post_id, $settings );
			return;
		}

		if ( empty( $settings ) ) {
			return;
		}

		$resolved    = $settings;
		$widget_type = Elementor_Preview_Document::get_widget_type_for_snippet( $post_id );
		if ( $widget_type ) {
			$resolved = self::resolve_settings( $post_id, $widget_type, $settings );
		}
		if ( ! empty( $resolved ) ) {
			self::update( $post_id, $resolved );
		}
	}

	/**
	 * @param mixed $value
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error(
				'invalid_elementor_preview_settings',
				esc_html__( 'elementor_preview_settings must be a JSON object.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		$encoded = wp_json_encode( $value );
		if ( false === $encoded ) {
			return new \WP_Error(
				'invalid_elementor_preview_settings',
				esc_html__( 'elementor_preview_settings could not be encoded as JSON.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		if ( strlen( $encoded ) > 102400 ) {
			return new \WP_Error(
				'elementor_preview_settings_too_large',
				esc_html__( 'elementor_preview_settings exceeds the maximum size (100KB).', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		$decoded = json_decode( $encoded, true );

		return is_array( $decoded ) ? $decoded : [];
	}
}
