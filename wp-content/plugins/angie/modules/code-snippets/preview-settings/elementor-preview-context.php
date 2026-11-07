<?php

namespace Angie\Modules\CodeSnippets\PreviewSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor_Preview_Context {

	private const META_FILTER_PRIORITY = 10;

	private int $post_id;

	/**
	 * @var callable|null
	 */
	private $meta_filter_callback = null;

	public function __construct( int $post_id ) {
		$this->post_id = max( 0, $post_id );
	}

	public function activate(): void {
		if ( $this->post_id <= 0 ) {
			return;
		}

		delete_post_meta( $this->post_id, '_elementor_element_cache' );

		$this->meta_filter_callback = [ $this, 'filter_elementor_meta' ];

		add_filter( 'get_post_metadata', $this->meta_filter_callback, self::META_FILTER_PRIORITY, 5 );
	}

	public function deactivate(): void {
		if ( null === $this->meta_filter_callback ) {
			return;
		}

		remove_filter( 'get_post_metadata', $this->meta_filter_callback, self::META_FILTER_PRIORITY );
		$this->meta_filter_callback = null;
	}

	public function is_active(): bool {
		return null !== $this->meta_filter_callback;
	}

	/**
	 * @param mixed  $value
	 * @param int    $object_id
	 * @param string $meta_key
	 * @param bool   $single
	 * @param string $meta_type
	 * @return mixed
	 */
	public function filter_elementor_meta( $value, int $object_id, string $meta_key, bool $single, string $meta_type ) {
		if ( 'post' !== $meta_type || $object_id !== $this->post_id ) {
			return $value;
		}

		if ( ! Snippet_Type_Resolver::is_elementor_widget_snippet( $object_id ) ) {
			return $value;
		}

		switch ( $meta_key ) {
			case '_elementor_edit_mode':
				return $single ? 'builder' : [ 'builder' ];

			case '_elementor_template_type':
				return $single ? 'wp-post' : [ 'wp-post' ];

			case '_elementor_version':
				if ( defined( 'ELEMENTOR_VERSION' ) ) {
					return $single ? ELEMENTOR_VERSION : [ ELEMENTOR_VERSION ];
				}
				return $value;

			case '_elementor_data':
				$json = Elementor_Preview_Document::build_elementor_data_json( $object_id );
				if ( '' === $json ) {
					return $value;
				}

				return $single ? $json : [ $json ];

			case '_wp_page_template':
				return $single ? 'elementor_canvas' : [ 'elementor_canvas' ];
		}

		return $value;
	}
}
