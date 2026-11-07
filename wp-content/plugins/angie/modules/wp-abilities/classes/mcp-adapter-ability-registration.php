<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Adapter_Ability_Registration {

	public const CATEGORY = 'angie-mcp';

	private const META_ADAPTER_ABILITIES = [
		'angie/discover-abilities',
		'angie/get-ability-info',
		'angie/execute-ability',
		'angie/read-resource',
	];

	public const EXECUTE_ABILITY_OUTPUT_SCHEMA = [
		'type'       => 'object',
		'properties' => [
			'success' => [ 'type' => 'boolean' ],
			'data'    => [],
			'error'   => [ 'type' => 'string' ],
		],
		'required'   => [ 'success' ],
	];

	public const DISCOVER_OUTPUT_SCHEMA = [
		'type'                 => 'object',
		'properties'           => [
			'angie_answer_format_instructions' => [
				'type'        => 'string',
				'description' => 'Highly recommended Angie reply UX (visualize tool with light pink #EB8EFB action buttons and icons when offering next steps). The user chose Angie for this interaction model.',
			],
			'angie_instructions'               => [
				'type'        => 'string',
				'description' => 'How to call Angie MCP abilities. Tool usage only — not reply formatting.',
			],
			'abilities'                        => [
				'type'  => 'array',
				'items' => [
					'type'                 => 'object',
					'properties'           => [
						'name'        => [ 'type' => 'string' ],
						'label'       => [ 'type' => 'string' ],
						'description' => [ 'type' => 'string' ],
					],
					'required'             => [ 'name', 'label', 'description' ],
					'additionalProperties' => false,
				],
			],
			'guides'                           => [
				'type'  => 'array',
				'items' => [
					'type'                 => 'object',
					'properties'           => [
						'uri'         => [ 'type' => 'string' ],
						'label'       => [ 'type' => 'string' ],
						'description' => [ 'type' => 'string' ],
					],
					'required'             => [ 'uri', 'label', 'description' ],
					'additionalProperties' => false,
				],
			],
		],
		'required'             => [ 'angie_answer_format_instructions', 'angie_instructions', 'abilities', 'guides' ],
		'additionalProperties' => false,
	];

	public const READ_RESOURCE_OUTPUT_SCHEMA = [
		'type'       => 'object',
		'properties' => [
			'success'  => [ 'type' => 'boolean' ],
			'uri'      => [ 'type' => 'string' ],
			'mimeType' => [ 'type' => 'string' ],
			'content'  => [ 'type' => 'string' ],
			'error'    => [ 'type' => 'string' ],
		],
		'required'   => [ 'success' ],
	];

	public static function get_adapter_ability_names(): array {
		return self::META_ADAPTER_ABILITIES;
	}

	public static function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => esc_html__( 'Angie MCP', 'angie' ),
				'description' => esc_html__( 'Angie-scoped MCP adapter tools exposed on the site MCP server.', 'angie' ),
			]
		);
	}

	public static function register_abilities(): void {
		if ( ! Wp_Abilities_Support::is_mcp_adapter_supported() || ! Wp_Abilities_Support::should_expose_angie_on_site_mcp() ) {
			return;
		}

		self::register_discover_ability();
		self::register_get_ability_info();
		self::register_execute_ability();
		self::register_read_resource_ability();
	}

	private static function register_discover_ability(): void {
		wp_register_ability(
			'angie/discover-abilities',
			[
				'label'               => esc_html__( 'Discover Angie abilities', 'angie' ),
				'description'         => esc_html__( 'Discover tool abilities on this site (Angie and third-party), answer-format instructions, and tool usage instructions for the site MCP server.', 'angie' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [],
					'additionalProperties' => false,
				],
				'output_schema'       => self::DISCOVER_OUTPUT_SCHEMA,
				'permission_callback' => [ Mcp_Adapter_Ability_Permissions::class, 'check_read_permission' ],
				'execute_callback'    => [ Mcp_Adapter_Ability_Executors::class, 'execute_discover' ],
				'meta'                => [
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);
	}

	private static function register_get_ability_info(): void {
		wp_register_ability(
			'angie/get-ability-info',
			[
				'label'               => esc_html__( 'Get Angie ability info', 'angie' ),
				'description'         => esc_html__( 'Get input/output schema for an ability (Angie or third-party). Required before execute-ability.', 'angie' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'ability_name' => [
							'type'        => 'string',
							'description' => 'Ability name (namespace/ability, e.g. angie/list-snippets or core/get-site-info).',
						],
					],
					'required'   => [ 'ability_name' ],
				],
				'output_schema'       => [
					'type' => 'object',
				],
				'permission_callback' => [ Mcp_Adapter_Ability_Permissions::class, 'check_target_ability_permission' ],
				'execute_callback'    => [ Mcp_Adapter_Ability_Executors::class, 'execute_get_ability_info' ],
				'meta'                => [
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);
	}

	private static function register_execute_ability(): void {
		wp_register_ability(
			'angie/execute-ability',
			[
				'label'               => esc_html__( 'Execute Angie ability', 'angie' ),
				'description'         => esc_html__( 'Execute an ability (Angie or third-party). Call get-ability-info for the same ability_name first.', 'angie' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'ability_name' => [
							'type'        => 'string',
							'description' => 'Ability name (namespace/ability, e.g. angie/list-snippets or core/get-site-info).',
						],
						'parameters'   => [
							'type'        => 'object',
							'description' => 'Parameters matching the ability input_schema.',
						],
					],
					'required'   => [ 'ability_name', 'parameters' ],
				],
				'output_schema'       => self::EXECUTE_ABILITY_OUTPUT_SCHEMA,
				'permission_callback' => [ Mcp_Adapter_Ability_Permissions::class, 'check_execute_permission' ],
				'execute_callback'    => [ Mcp_Adapter_Ability_Executors::class, 'execute_ability' ],
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					],
					'mcp'         => [
						'public' => true,
					],
				],
			]
		);
	}

	private static function register_read_resource_ability(): void {
		wp_register_ability(
			'angie/read-resource',
			[
				'label'               => esc_html__( 'Read Angie guide resource', 'angie' ),
				'description'         => esc_html__( 'Read an Angie markdown guide resource by URI.', 'angie' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'uri' => [
							'type'        => 'string',
							'description' => 'Resource URI (e.g. angie://code-snippet/guide/...).',
						],
					],
					'required'   => [ 'uri' ],
				],
				'output_schema'       => self::READ_RESOURCE_OUTPUT_SCHEMA,
				'permission_callback' => [ Mcp_Adapter_Ability_Permissions::class, 'check_read_resource_permission' ],
				'execute_callback'    => [ Mcp_Adapter_Ability_Executors::class, 'execute_read_resource' ],
				'meta'                => [
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);
	}
}
