<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Abilities_Registrar {

	public static function register_category(): void {
		wp_register_ability_category(
			static::CATEGORY,
			static::get_category_meta()
		);
	}

	/**
	 * @return array{label: string, description: string}
	 */
	abstract protected static function get_category_meta(): array;

	protected static function register_rest_tool(
		string $name,
		array $config,
		string $rest_namespace,
		callable $permission_callback
	): void {
		$input = is_array( $config['args'] ?? null ) ? $config['args'] : [];
		$input_schema = Rest_Args_Schema_Builder::from_rest_args( $input );

		if ( ! empty( $config['allow_additional_properties'] ) ) {
			$input_schema['additionalProperties'] = true;
		}

		wp_register_ability(
			$name,
			[
				'label'               => $config['label'],
				'description'         => $config['description'],
				'category'            => static::CATEGORY,
				'input_schema'        => Rest_Args_Schema_Builder::from_rest_args( $input ),
				'output_schema'       => [
					'type' => 'object',
				],
				'execute_callback'    => static function ( $ability_input ) use ( $config, $rest_namespace ) {
					$params = is_array( $ability_input ) ? $ability_input : [];
					return Rest_Ability_Proxy::execute(
						$rest_namespace,
						$config['method'],
						$config['route'],
						$params
					);
				},
				'permission_callback' => $permission_callback,
				'meta'                => [
					'show_in_rest' => true,
					'mcp'          => Wp_Abilities_Support::tool_mcp_meta(),
					'annotations'  => static::default_tool_annotations( $config['annotations'] ?? [] ),
				],
			]
		);
	}

	protected static function register_markdown_resource(
		string $name,
		array $config,
		callable $permission_callback
	): void {
		$file_path = $config['file'];

		wp_register_ability(
			$name,
			[
				'label'               => $config['label'],
				'description'         => $config['description'],
				'category'            => static::CATEGORY,
				'output_schema'       => [
					'type' => 'string',
				],
				'execute_callback'    => static function () use ( $file_path ) {
					return Markdown_Resource::load( $file_path );
				},
				'permission_callback' => $permission_callback,
				'meta'                => [
					'mcp'          => array_merge(
						Wp_Abilities_Support::resource_mcp_meta(),
						[
							'uri'         => $config['uri'],
							'annotations' => [
								'readonly' => true,
								'audience' => [ 'assistant' ],
							],
						]
					),
					'show_in_rest' => true,
				],
			]
		);
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed|null>
	 */
	protected static function default_tool_annotations( array $overrides = [] ): array {
		return array_merge(
			[
				'readonly'    => null,
				'destructive' => null,
				'idempotent'  => null,
			],
			$overrides
		);
	}

	protected static function check_capability( string $capability ): bool {
		return current_user_can( $capability );
	}
}
