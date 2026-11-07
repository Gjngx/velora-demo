<?php

namespace Angie\Modules\ElementorCore\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kit Provider
 *
 * Exposes Elementor Site Settings (kit document) via REST. POST merges any kit
 * control keys registered on the active kit tabs into saved settings.
 */
class Kit_Provider {

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	protected $namespace = 'angie/v1';

	/**
	 * REST API base
	 *
	 * @var string
	 */
	protected $rest_base = 'elementor-kit';

	/**
	 * Constructor
	 */
	public function __construct() {
		\add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_kit_settings' ],
				'permission_callback' => function () {
					return \current_user_can( 'edit_theme_options' );
				},
			]
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_kit_settings' ],
				'permission_callback' => function () {
					return \current_user_can( 'edit_theme_options' );
				},
			]
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/schema',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_elementor_kit_schema' ],
				'permission_callback' => function () {
					return \current_user_can( 'edit_theme_options' );
				},
			]
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/fonts',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_fonts' ],
				'permission_callback' => function () {
					return \current_user_can( 'edit_theme_options' );
				},
			]
		);
	}

	/**
	 * Get Elementor kit settings
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_kit_settings() {
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		$active_kit   = $kits_manager->get_active_kit();

		if ( ! $active_kit ) {
			return new \WP_Error( 'no_active_kit', 'No active Elementor kit found', [ 'status' => 404 ] );
		}

		$kit_id = $active_kit->get_id();

		$kit_document = \Elementor\Plugin::$instance->documents->get( $kit_id );

		if ( ! $kit_document ) {
			return new \WP_Error( 'kit_document_not_found', 'Kit document not found', [ 'status' => 404 ] );
		}

		$saved_settings = $kit_document->get_settings();

		return \rest_ensure_response( $saved_settings );
	}

	/**
	 * Get all fonts available in Elementor
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_fonts() {
		try {
			$fonts = \Elementor\Fonts::get_fonts();
			$font_groups = \Elementor\Fonts::get_font_groups();

			$response_data = [
				'fonts' => $fonts,
				'font_groups' => $font_groups,
				'google_fonts_enabled' => \Elementor\Fonts::is_google_fonts_enabled(),
				'font_display_setting' => \Elementor\Fonts::get_font_display_setting(),
				'total_fonts' => count( $fonts ),
			];

			return \rest_ensure_response( $response_data );
		} catch ( \Exception $e ) {
			return new \WP_Error( 
				'fonts_fetch_error', 
				'Failed to fetch fonts: ' . $e->getMessage(), 
				[ 'status' => 500 ] 
			);
		}
	}

	/**
	 * Update Elementor kit settings
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function update_kit_settings( $request ) {
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		$active_kit   = $kits_manager->get_active_kit();
		$params       = $request->get_params();

		if ( ! $active_kit ) {
			return new \WP_Error( 'no_active_kit', 'No active Elementor kit found', [ 'status' => 404 ] );
		}

		$kit_id = $active_kit->get_id();

		$kit_document = \Elementor\Plugin::$instance->documents->get( $kit_id );

		if ( ! $kit_document ) {
			return new \WP_Error( 'kit_document_not_found', 'Kit document not found', [ 'status' => 404 ] );
		}

		$current_settings = $kit_document->get_settings();

		$merged_settings = array_merge( $current_settings, $params );

		$kit_document->save(
			[
				'settings' => $merged_settings,
			]
		);

		$this->clear_elementor_cache();

		return \rest_ensure_response(
			[
				'success'          => true,
				'kit_id'           => $kit_id,
				'message'          => 'Site settings updated successfully',
				'updated_settings' => $params,
			]
		);
	}

	/**
	 * Get Elementor's default kit settings
	 *
	 * @return array Default kit settings
	 */
	protected function get_elementor_default_kit_settings() {
		$default_settings = [];

		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		$active_kit   = $kits_manager->get_active_kit();

		if ( ! $active_kit ) {
			return new \WP_Error( 'no_active_kit', 'No active Elementor kit found', [ 'status' => 404 ] );
		}

		if ( $active_kit ) {
			$controls = $active_kit->get_controls();

			foreach ( $controls as $control_id => $control ) {
				if ( isset( $control['default'] ) ) {
					$default_settings[ $control_id ] = $control['default'];
				}
			}
		}

		return $default_settings;
	}


	/**
	 * Clear Elementor cache
	 */
	protected function clear_elementor_cache() {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		$active_kit   = $kits_manager->get_active_kit();
		$kit_id       = $active_kit->get_id();
		if ( $kit_id ) {
			\delete_post_meta( $kit_id, '_elementor_css' );
		}
	}

	/**
	 * Process control schema recursively to handle nested repeater fields
	 *
	 * @param array $control The control array to process.
	 */
	private function process_control_schema( $control ) {
		$schema = [];
		if ( ! empty( $control['label'] ) ) {
			$schema['label'] = $control['label'];
		}
		if ( ! empty( $control['type'] ) ) {
			$schema['type'] = $control['type'];
		}
		if ( ! empty( $control['default'] ) ) {
			$schema['default'] = $control['default'];
		}
		if ( ! empty( $control['options'] ) ) {
			$schema['options'] = $control['options'];
		}

		if ( isset( $control['fields'] ) && \is_array( $control['fields'] ) ) {
			$schema['fields'] = [];
			foreach ( $control['fields'] as $field_id => $field ) {
				$schema['fields'][ $field_id ] = $this->process_control_schema( $field );
			}
		}

		if ( 'repeater' === $control['type'] || isset( $control['is_repeater'] ) ) {
			$schema['title_field']   = $control['title_field'] ?? '';
			$schema['prevent_empty'] = $control['prevent_empty'] ?? true;
			$schema['max_items']     = $control['max_items'] ?? 0;
			$schema['min_items']     = $control['min_items'] ?? 0;
			$schema['item_actions']  = $control['item_actions'] ?? [];
		}

		return $schema;
	}



	/**
	 * Get Elementor kit schema
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_elementor_kit_schema() {
		\Elementor\Core\Frontend\Performance::set_use_style_controls( true );
		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

		$tabs = $kit->get_tabs();

		$tab_controls = new \stdClass();

		\Elementor\Plugin::$instance->controls_manager->clear_stack_cache();

		foreach ( $tabs as $tab_id => $tab ) {
			\Elementor\Plugin::$instance->controls_manager->delete_stack( $kit );

			$tab->register_controls();

			$tab_specific_controls = $kit->get_controls();

			$tab_controls->$tab_id = new \stdClass();

			foreach ( $tab_specific_controls as $control_id => $control ) {
				if ( 'section' === $control['type'] ||
				'heading' === $control['type'] ||
				'popover_toggle' === $control['type'] ) {
					continue;
				}

				$tab_controls->$tab_id->$control_id = $this->process_control_schema( $control );
			}
		}

		return \rest_ensure_response( $tab_controls );
	}
}
