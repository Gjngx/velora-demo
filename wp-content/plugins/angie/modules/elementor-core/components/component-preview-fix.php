<?php

namespace Angie\Modules\ElementorCore\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor registers elementor_component with public=false and no publicly_queryable flag.
 * The classic editor preview iframe uses ?p={id}&elementor-preview={id}, which 404s without frontend queries.
 */
class Component_Preview_Fix {

	private const COMPONENT_POST_TYPE = 'elementor_component';

	public function __construct() {
		\add_filter( 'register_post_type_args', [ $this, 'filter_register_post_type_args' ], 10, 2 );
		\add_action( 'init', [ $this, 'ensure_component_is_publicly_queryable' ], 20 );
	}

	public function filter_register_post_type_args( array $args, string $post_type ): array {
		if ( self::COMPONENT_POST_TYPE !== $post_type ) {
			return $args;
		}

		$args['publicly_queryable'] = true;

		return $args;
	}

	public function ensure_component_is_publicly_queryable(): void {
		global $wp_post_types;

		if ( ! isset( $wp_post_types[ self::COMPONENT_POST_TYPE ] ) ) {
			return;
		}

		$wp_post_types[ self::COMPONENT_POST_TYPE ]->publicly_queryable = true;
	}
}
