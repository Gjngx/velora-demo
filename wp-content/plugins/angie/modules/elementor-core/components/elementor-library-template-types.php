<?php

namespace Angie\Modules\ElementorCore\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes Elementor's registered template types so the MCP layer can
 * validate and list them without a hardcoded static set on the JS side.
 *
 * Response shape:
 * [
 *   { slug: 'popup', post_type: 'elementor_library' },
 *   { slug: 'floating-buttons', post_type: 'e-floating-buttons' },
 *   ...
 * ]
 */
class Elementor_Library_Template_Types {

	protected $namespace = 'angie/v1';

	protected $rest_base = 'elementor-library/template-types';

	/** @var array<string,string> slug → post_type overrides for types not in elementor_library */
	private static $post_type_overrides = [
		'floating-buttons'    => 'e-floating-buttons',
		'floating-bars'       => 'e-floating-buttons',
		'elementor_component' => 'elementor_component',
	];

	public function __construct() {
		\add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_template_types' ],
				'permission_callback' => function () {
					return \current_user_can( 'edit_posts' );
				},
			]
		);
	}

	public function get_template_types() {
		$slugs = $this->resolve_slugs();

		$result = [];
		foreach ( $slugs as $slug ) {
			$result[] = [
				'slug'      => $slug,
				'post_type' => self::$post_type_overrides[ $slug ] ?? 'elementor_library',
			];
		}

		return \rest_ensure_response( $result );
	}

	/**
	 * Tries to pull slugs from Elementor's own Source_Local; falls back to
	 * the taxonomy terms already on this site, then to a hard-coded baseline.
	 *
	 * @return string[]
	 */
	private function resolve_slugs(): array {
		// Primary: Elementor Source_Local::get_template_types() (Elementor ≥ 3.x)
		if (
			class_exists( '\Elementor\TemplateLibrary\Source_Local' ) &&
			method_exists( '\Elementor\TemplateLibrary\Source_Local', 'get_template_types' )
		) {
			$types = \Elementor\TemplateLibrary\Source_Local::get_template_types();
			if ( ! empty( $types ) && is_array( $types ) ) {
				return array_values( $types );
			}
		}

		// Secondary: live taxonomy terms on this site
		$terms = \get_terms( [
			'taxonomy'   => 'elementor_library_type',
			'hide_empty' => false,
			'fields'     => 'slugs',
		] );

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			return array_values( $terms );
		}

		// Fallback: known baseline matching the TS static set
		return [
			'loop-item', 'error-404', 'product', 'product-archive',
			'archive', 'header', 'footer', 'popup', 'single',
			'single-post', 'single-page', 'search-results', 'section',
			'container', 'e-div-block', 'e-flexbox', 'page', 'widget',
			'floating-buttons', 'floating-bars', 'elementor_component',
		];
	}
}
