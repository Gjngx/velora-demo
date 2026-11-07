<?php

namespace Angie\Modules\ElementorCore\Components;

use Angie\Modules\WpAbilities\Classes\Abilities_Registrar as Base_Abilities_Registrar;
use Angie\Modules\WpAbilities\Classes\Angie_Ability_Permissions;
use Angie\Modules\ElementorCore\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Abilities_Registrar extends Base_Abilities_Registrar {

	const CATEGORY = 'elementor-core';

	const REST_NAMESPACE = 'angie/v1';

	protected static function get_category_meta(): array {
		return [
			'label'       => esc_html__( 'Elementor Core', 'angie' ),
			'description' => esc_html__( 'Elementor kit settings and library template operations.', 'angie' ),
		];
	}

	public static function register_abilities(): void {
		if ( ! Module::is_active() ) {
			return;
		}

		self::register_module_rest_tool(
			'angie/get-elementor-kit',
			[
				'label'       => esc_html__( 'Get Elementor kit', 'angie' ),
				'description' => esc_html__( 'Get the active Elementor kit settings.', 'angie' ),
				'method'      => 'GET',
				'route'       => '/elementor-kit',
				'permission'  => 'edit_theme_options',
			]
		);

		self::register_module_rest_tool(
			'angie/update-elementor-kit',
			[
				'label'       => esc_html__( 'Update Elementor kit', 'angie' ),
				'description' => esc_html__( 'Patch the active Elementor Site Settings kit. Accepts any kit control key from Elementor tabs: global-colors (system_colors, custom_colors), global-typography (system_typography, custom_typography, default_generic_fonts), theme-style-typography, theme-style-buttons, theme-style-images, theme-style-form-fields, settings-site-identity (site_name, site_description, site_logo, site_favicon), settings-background, settings-layout (container_width, space_between_widgets, default_page_template, breakpoints), settings-lightbox, settings-page-transitions, settings-custom-css, plus keys added by plugins via elementor/kit/register_tabs. Values are merged into existing kit settings. Call angie/get-elementor-kit for current values and angie/get-elementor-kit-schema for field types and valid values before updating.', 'angie' ),
				'method'      => 'POST',
				'route'       => '/elementor-kit',
				'permission'  => 'edit_theme_options',
				'allow_additional_properties' => true,
				'args'        => [
					'system_colors'     => [
						'type'        => 'array',
						'description' => esc_html__( 'System colors repeater (_id: primary, secondary, text, accent).', 'angie' ),
					],
					'custom_colors'     => [
						'type'        => 'array',
						'description' => esc_html__( 'Custom global colors repeater.', 'angie' ),
					],
					'system_typography' => [
						'type'        => 'array',
						'description' => esc_html__( 'System typography repeater (_id: primary, secondary, text, accent).', 'angie' ),
					],
					'custom_typography' => [
						'type'        => 'array',
						'description' => esc_html__( 'Custom global typography repeater.', 'angie' ),
					],
				],
			]
		);

		self::register_module_rest_tool(
			'angie/get-elementor-kit-schema',
			[
				'label'       => esc_html__( 'Get Elementor kit schema', 'angie' ),
				'description' => esc_html__( 'Get the schema for Elementor kit controls.', 'angie' ),
				'method'      => 'GET',
				'route'       => '/elementor-kit/schema',
				'permission'  => 'edit_theme_options',
			]
		);

		self::register_module_rest_tool(
			'angie/get-elementor-kit-fonts',
			[
				'label'       => esc_html__( 'Get Elementor kit fonts', 'angie' ),
				'description' => esc_html__( 'List fonts available in Elementor.', 'angie' ),
				'method'      => 'GET',
				'route'       => '/elementor-kit/fonts',
				'permission'  => 'edit_theme_options',
			]
		);

		self::register_module_rest_tool(
			'angie/sync-elementor-library-type',
			[
				'label'       => esc_html__( 'Sync Elementor library type', 'angie' ),
				'description' => esc_html__( 'Sync elementor_library_type taxonomy for a library item.', 'angie' ),
				'method'      => 'POST',
				'route'                      => '/elementor-library/(?P<id>\d+)/sync-type',
				'permission'                 => 'edit_post',
				'permission_object_id_arg'   => 'id',
				'args'        => [
					'id'            => [
						'required'    => true,
						'type'        => 'integer',
						'description' => esc_html__( 'Elementor library post ID.', 'angie' ),
					],
					'template_type' => [
						'required'    => true,
						'type'        => 'string',
						'description' => esc_html__( 'Template type slug to set.', 'angie' ),
					],
				],
			]
		);

		self::register_module_rest_tool(
			'angie/get-elementor-library-template-types',
			[
				'label'       => esc_html__( 'Get Elementor library template types', 'angie' ),
				'description' => esc_html__( 'List registered Elementor library template types.', 'angie' ),
				'method'      => 'GET',
				'route'       => '/elementor-library/template-types',
				'permission'  => 'edit_posts',
			]
		);
	}

	protected static function register_module_rest_tool( string $name, array $config ): void {
		parent::register_rest_tool(
			$name,
			$config,
			self::REST_NAMESPACE,
			self::build_tool_permission_callback( $config )
		);
	}

	private static function build_tool_permission_callback( array $config ): callable {
		$object_id_arg = $config['permission_object_id_arg'] ?? null;
		if ( is_string( $object_id_arg ) && '' !== $object_id_arg ) {
			$capability = $config['permission'] ?? 'edit_post';

			return Angie_Ability_Permissions::gate(
				static function ( $input ) use ( $object_id_arg, $capability ) {
					if ( ! Module::is_active() ) {
						return false;
					}

					$params = is_array( $input ) ? $input : [];
					$object_id = (int) ( $params[ $object_id_arg ] ?? 0 );
					if ( $object_id <= 0 ) {
						return false;
					}

					return current_user_can( $capability, $object_id );
				}
			);
		}

		$capability = $config['permission'] ?? 'edit_theme_options';

		return Angie_Ability_Permissions::gate(
			static function () use ( $capability ) {
				return Module::is_active() && current_user_can( $capability );
			}
		);
	}
}
