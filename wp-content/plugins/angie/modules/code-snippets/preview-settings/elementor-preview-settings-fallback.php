<?php

namespace Angie\Modules\CodeSnippets\PreviewSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor_Preview_Settings_Fallback {

	const GALLERY_MIN_ITEMS = 4;

	const REPEATER_MIN_ITEMS = 3;

	private static $placeholder_index = 0;

	private static $virtual_urls = [];

	private static $next_virtual_id = 900000000;

	/**
	 * @param object               $widget_type
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public static function fill_missing( $widget_type, array $settings ) {
		if ( ! is_object( $widget_type ) || ! method_exists( $widget_type, 'get_controls' ) ) {
			return $settings;
		}

		$controls = $widget_type->get_controls();
		if ( ! is_array( $controls ) || [] === $controls ) {
			return $settings;
		}

		self::$placeholder_index = 0;
		if ( Elementor_Preview_Document::is_active() ) {
			self::$virtual_urls    = [];
			self::$next_virtual_id = 900000000;
		}

		return self::fill_controls( $controls, $settings );
	}

	/**
	 * @param array<string, array<string, mixed>> $controls
	 * @param array<string, mixed>                $settings
	 * @return array<string, mixed>
	 */
	private static function fill_controls( array $controls, array $settings ) {
		foreach ( $controls as $control_id => $control ) {
			if ( ! is_string( $control_id ) || ! is_array( $control ) || str_starts_with( $control_id, '_' ) ) {
				continue;
			}

			$type = $control['type'] ?? '';
			if ( 'repeater' === $type ) {
				$fields = is_array( $control['fields'] ?? null ) ? $control['fields'] : [];
				$items  = $settings[ $control_id ] ?? [];
				if ( ! is_array( $items ) || [] === $items ) {
					$items = array_fill( 0, self::REPEATER_MIN_ITEMS, [] );
				}
				foreach ( $items as $i => $item ) {
					if ( is_array( $item ) ) {
						$items[ $i ] = self::fill_controls( $fields, $item );
					}
				}
				$settings[ $control_id ] = $items;
				continue;
			}

			if ( in_array( $type, [ 'media', 'gallery' ], true ) ) {
				$settings[ $control_id ] = self::fill_if_empty( $settings[ $control_id ] ?? null, $type );
			}
		}

		return $settings;
	}

	/**
	 * @param mixed  $value
	 * @param string $type
	 * @return mixed
	 */
	private static function fill_if_empty( $value, $type ) {
		if ( 'gallery' === $type ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					if ( is_array( $item ) && '' !== trim( (string) ( $item['url'] ?? '' ) ) ) {
						return $value;
					}
				}
			}
			$gallery = [];
			for ( $i = 0; $i < self::GALLERY_MIN_ITEMS; $i++ ) {
				$gallery[] = self::make_media_value( self::next_placeholder_url() );
			}
			return $gallery;
		}

		if ( is_array( $value ) && '' !== trim( (string) ( $value['url'] ?? '' ) ) ) {
			return $value;
		}

		return self::make_media_value( self::next_placeholder_url() );
	}

	/**
	 * @param string $url
	 * @return array{id: string, url: string}
	 */
	private static function make_media_value( $url ) {
		$url = esc_url_raw( $url );
		$id  = '';
		if ( Elementor_Preview_Document::is_active() ) {
			$id                              = (string) self::$next_virtual_id++;
			self::$virtual_urls[ (int) $id ] = $url;
		}

		return [ 'id' => $id, 'url' => $url ];
	}

	private static function next_placeholder_url() {
		$n = ( self::$placeholder_index++ % 6 ) + 1;

		return "https://editor-static-bucket.elementor.com/snippet-preview-placeholders/{$n}.png";
	}

	/**
	 * @param array<int, mixed>|false $downsize
	 * @param int                     $attachment_id
	 * @param string|int[]            $size
	 * @return array<int, mixed>|false
	 */
	public static function filter_image_downsize( $downsize, $attachment_id, $size ) {
		$url = self::$virtual_urls[ (int) $attachment_id ] ?? null;

		return $url ? [ $url, 1448, 1024, true ] : $downsize;
	}
}
