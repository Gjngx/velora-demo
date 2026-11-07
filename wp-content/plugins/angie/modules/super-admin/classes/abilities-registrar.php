<?php

namespace Angie\Modules\SuperAdmin\Classes;

use Angie\Modules\WpAbilities\Classes\Abilities_Registrar as Base_Abilities_Registrar;
use Angie\Modules\WpAbilities\Classes\Angie_Ability_Permissions;
use Angie\Modules\WpAbilities\Classes\Rest_Args_Schema_Builder;
use Angie\Modules\WpAbilities\Classes\Wp_Abilities_Support;
use Angie\Modules\SuperAdmin\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Abilities_Registrar extends Base_Abilities_Registrar {

	const CATEGORY = 'super-admin';

	protected static function get_category_meta(): array {
		return [
			'label'       => esc_html__( 'Super Admin', 'angie' ),
			'description' => esc_html__( 'Direct server-side operations for Angie super admin.', 'angie' ),
		];
	}

	public static function register_abilities(): void {
		if ( ! Module::is_active() ) {
			return;
		}

		self::register_direct_tool(
			'angie/execute-php',
			[
				'label'       => esc_html__( 'Execute PHP', 'angie' ),
				'description' => esc_html__( 'Execute PHP code on the server (super admin only).', 'angie' ),
				'method'      => 'POST',
				'handler'     => 'execute_php',
				'args'        => [
					'code' => [
						'required'    => true,
						'type'        => 'string',
						'description' => esc_html__( 'PHP code to execute.', 'angie' ),
					],
				],
				'annotations' => [
					'destructive' => true,
				],
			]
		);

		self::register_direct_tool(
			'angie/list-directory',
			[
				'label'       => esc_html__( 'List directory', 'angie' ),
				'description' => esc_html__( 'List entries in a directory under ABSPATH.', 'angie' ),
				'method'      => 'GET',
				'handler'     => 'list_directory',
				'args'        => [
					'path' => [
						'required'    => true,
						'type'        => 'string',
						'description' => esc_html__( 'Directory path relative to ABSPATH.', 'angie' ),
					],
				],
			]
		);

		self::register_direct_tool(
			'angie/read-file',
			[
				'label'       => esc_html__( 'Read file', 'angie' ),
				'description' => esc_html__( 'Read a file under ABSPATH.', 'angie' ),
				'method'      => 'GET',
				'handler'     => 'read_file',
				'args'        => [
					'path' => [
						'required'    => true,
						'type'        => 'string',
						'description' => esc_html__( 'File path relative to ABSPATH.', 'angie' ),
					],
				],
			]
		);

		self::register_direct_tool(
			'angie/get-super-admin-status',
			[
				'label'       => esc_html__( 'Get super admin status', 'angie' ),
				'description' => esc_html__( 'Check whether Angie super admin is enabled.', 'angie' ),
				'method'      => 'GET',
				'handler'     => 'get_status',
				'args'        => [],
				'requires_enabled' => false,
			]
		);

		self::register_resource_abilities();
	}

	private static function register_resource_abilities(): void {
		$content_dir = dirname( __DIR__ ) . '/content';

		self::register_module_markdown_resource(
			'angie/create-elementor-page-guide',
			[
				'label'       => esc_html__( 'Create Elementor page guide', 'angie' ),
				'description' => esc_html__( 'Playbook for building Elementor pages via execute-php.', 'angie' ),
				'uri'         => 'angie://super-admin/guide/create-elementor-page',
				'file'        => $content_dir . '/create-elementor-page.md',
			]
		);
	}

	protected static function register_module_markdown_resource( string $name, array $config ): void {
		parent::register_markdown_resource(
			$name,
			$config,
			Angie_Ability_Permissions::gate(
				static function () {
					return Module::is_enabled() && Module::current_user_can_use();
				}
			)
		);
	}

	private static function register_direct_tool( string $name, array $config ): void {
		$input = is_array( $config['args'] ?? null ) ? $config['args'] : [];
		$handler = $config['handler'];

		wp_register_ability(
			$name,
			[
				'label'               => $config['label'],
				'description'         => $config['description'],
				'category'            => self::CATEGORY,
				'input_schema'        => Rest_Args_Schema_Builder::from_rest_args( $input ),
				'output_schema'       => [
					'type' => 'object',
				],
				'execute_callback'    => static function ( $ability_input ) use ( $config, $handler ) {
					$controller = new Rest_Api_Controller();
					$request    = new \WP_REST_Request(
						$config['method'],
						'/angie/v1/super-admin/' . self::handler_route_segment( $handler )
					);

					if ( is_array( $ability_input ) ) {
						foreach ( $ability_input as $key => $value ) {
							$request->set_param( $key, $value );
						}
					}

					$response = $controller->$handler( $request );
					if ( $response instanceof \WP_REST_Response ) {
						return $response->get_data();
					}

					return $response;
				},
				'permission_callback' => Angie_Ability_Permissions::gate(
					static function () use ( $config ) {
						if ( false === ( $config['requires_enabled'] ?? true ) ) {
							return Module::current_user_can_use();
						}

						return Module::is_enabled() && Module::current_user_can_use();
					}
				),
				'meta'                => [
					'show_in_rest' => true,
					'mcp'          => Wp_Abilities_Support::tool_mcp_meta(),
					'annotations'  => static::default_tool_annotations( $config['annotations'] ?? [] ),
				],
			]
		);
	}

	private static function handler_route_segment( string $handler ): string {
		$routes = [
			'execute_php'    => 'execute',
			'list_directory' => 'list',
			'read_file'      => 'read',
			'get_status'     => 'status',
		];

		return $routes[ $handler ] ?? str_replace( '_', '-', $handler );
	}
}
