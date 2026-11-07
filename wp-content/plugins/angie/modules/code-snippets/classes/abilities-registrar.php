<?php

namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\WpAbilities\Classes\Abilities_Registrar as Base_Abilities_Registrar;
use Angie\Modules\WpAbilities\Classes\Angie_Ability_Permissions;
use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Abilities_Registrar extends Base_Abilities_Registrar {

	const CATEGORY = 'code-snippets';

	const REST_NAMESPACE = 'angie/v1';

	private const SNIPPET_TYPES = [
		'code-snippet',
		'elementor-widget',
		'gutenberg-block',
		'popup',
		'form',
		'visual-app',
		'web-mcp',
	];

	protected static function get_category_meta(): array {
		return [
			'label'       => esc_html__( 'Code Snippets', 'angie' ),
			'description' => esc_html__( 'Create, edit, and publish Angie code snippets.', 'angie' ),
		];
	}

	public static function register_abilities(): void {
		if ( ! Module::is_active() ) {
			return;
		}

		self::register_rest_abilities();
		self::register_resource_abilities();
	}

	private static function register_rest_abilities(): void {
		self::register_module_rest_tool(
			'angie/list-snippets',
			[
				'label'       => esc_html__( 'List snippets', 'angie' ),
				'description' => esc_html__( 'List Angie code snippets with optional type and deployment status filters.', 'angie' ),
				'method'      => 'GET',
				'route'       => '/snippets',
				'args'        => [
					'type'              => [
						'type'        => 'string',
						'description' => esc_html__( 'Filter by snippet type.', 'angie' ),
					],
					'deployment_status' => [
						'type'        => 'string',
						'description' => esc_html__( 'Comma-separated deployment statuses to include.', 'angie' ),
					],
				],
			]
		);

		self::register_module_rest_tool(
			'angie/create-snippet',
			[
				'label'       => esc_html__( 'Create snippet', 'angie' ),
				'description' => esc_html__( 'Create a new Angie code snippet post.', 'angie' ),
				'method'      => 'POST',
				'route'       => '/snippets',
				'args'        => [
					'title' => [
						'required'    => true,
						'type'        => 'string',
						'description' => esc_html__( 'Snippet title.', 'angie' ),
					],
					'type'  => self::get_snippet_type_arg_definition(),
				],
			]
		);

		self::register_module_rest_tool(
			'angie/delete-snippet',
			[
				'label'       => esc_html__( 'Delete snippet', 'angie' ),
				'description' => esc_html__( 'Delete a snippet and its files.', 'angie' ),
				'method'      => 'DELETE',
				'route'       => '/snippets/(?P<id>\d+)',
				'args'        => [
					'id' => [
						'required'    => true,
						'type'        => 'integer',
						'description' => esc_html__( 'Snippet post ID.', 'angie' ),
					],
				],
				'annotations' => [
					'destructive' => true,
				],
			]
		);

		self::register_module_rest_tool(
			'angie/list-snippet-files',
			[
				'label'       => esc_html__( 'List snippet files', 'angie' ),
				'description' => esc_html__( 'List files for a snippet, optionally including file contents.', 'angie' ),
				'method'      => 'GET',
				'route'       => '/snippets/(?P<id>\d+)/files',
				'args'        => [
					'id'              => [
						'required'    => true,
						'type'        => 'integer',
						'description' => esc_html__( 'Snippet post ID.', 'angie' ),
					],
					'include_content' => [
						'type'        => 'boolean',
						'description' => esc_html__( 'Include file contents in the response.', 'angie' ),
					],
				],
			]
		);

		self::register_module_rest_tool(
			'angie/update-snippet-files',
			[
				'label'       => esc_html__( 'Update snippet files', 'angie' ),
				'description' => esc_html__( 'Update snippet files (merged with existing files). Must include main.php. For Elementor widgets, pass elementor_preview_settings for iframe preview (auto-filled with placeholders if omitted). When previewUrl is returned, follow previewInstruction and call angie-preview-widget directly.', 'angie' ),
				'method'      => 'PUT',
				'route'       => '/snippets/(?P<id>\d+)/files',
				'args'        => [
					'id'    => [
						'required'    => true,
						'type'        => 'integer',
						'description' => esc_html__( 'Snippet post ID.', 'angie' ),
					],
					'files' => self::get_snippet_files_arg_definition(),
					'type'  => [
						'type'        => 'string',
						'description' => esc_html__( 'Optional snippet type to set.', 'angie' ),
					],
					'elementor_preview_settings' => [
						'type'        => 'object',
						'description' => esc_html__( 'Elementor widget control values for iframe preview; merged with widget defaults. Required for best preview results — use Unsplash URLs for image/media controls. Generic placeholders are auto-filled when omitted.', 'angie' ),
					],
				],
			]
		);

		self::register_module_rest_tool(
			'angie/get-snippet-file',
			[
				'label'       => esc_html__( 'Get snippet file', 'angie' ),
				'description' => esc_html__( 'Read a single file from a snippet.', 'angie' ),
				'method'      => 'GET',
				'route'       => '/snippets/(?P<id>\d+)/files/(?P<filename>.+)',
				'args'        => [
					'id'       => [
						'required'    => true,
						'type'        => 'integer',
						'description' => esc_html__( 'Snippet post ID.', 'angie' ),
					],
					'filename' => [
						'required'    => true,
						'type'        => 'string',
						'description' => esc_html__( 'File name within the snippet.', 'angie' ),
					],
				],
			]
		);

		self::register_module_rest_tool(
			'angie/publish-snippet',
			[
				'label'       => esc_html__( 'Publish snippet', 'angie' ),
				'description' => esc_html__( 'Publish snippet files to production.', 'angie' ),
				'method'      => 'POST',
				'route'       => '/snippets/(?P<id>\d+)/publish',
				'args'        => [
					'id' => [
						'required'    => true,
						'type'        => 'integer',
						'description' => esc_html__( 'Snippet post ID.', 'angie' ),
					],
				],
				'annotations' => [
					'destructive' => true,
				],
			]
		);

		self::register_module_rest_tool(
			'angie/set-dev-mode',
			[
				'label'       => esc_html__( 'Set dev mode', 'angie' ),
				'description' => esc_html__( 'Enable or disable Angie snippet test mode.', 'angie' ),
				'method'      => 'POST',
				'route'       => '/dev-mode',
				'args'        => [
					'enabled' => [
						'required'    => true,
						'type'        => 'boolean',
						'description' => esc_html__( 'Whether test mode should be enabled.', 'angie' ),
					],
				],
			]
		);

		self::register_module_rest_tool(
			'angie/validate-snippet',
			[
				'label'       => esc_html__( 'Validate snippet', 'angie' ),
				'description' => esc_html__( 'Validate snippet files without saving them.', 'angie' ),
				'method'      => 'POST',
				'route'       => '/snippets/validate',
				'args'        => [
					'files' => self::get_snippet_files_arg_definition(),
				],
			]
		);

		self::register_module_rest_tool(
			'angie/get-dev-mode-status',
			[
				'label'       => esc_html__( 'Get dev mode status', 'angie' ),
				'description' => esc_html__( 'Check whether Angie snippet test mode is enabled.', 'angie' ),
				'method'      => 'GET',
				'route'       => '/dev-mode/status',
				'args'        => [],
			]
		);
	}

	private static function register_resource_abilities(): void {
		$content_dir = dirname( __DIR__ ) . '/content';

		self::register_module_markdown_resource(
			'angie/basic-instructions',
			[
				'label'       => esc_html__( 'Basic instructions', 'angie' ),
				'description' => esc_html__( 'Basic instructions for creating and managing code snippets.', 'angie' ),
				'uri'         => 'angie://code-snippet/guide/basic-instructions',
				'file'        => $content_dir . '/basic-instructions.md',
			]
		);

		self::register_module_markdown_resource(
			'angie/workflow-guide',
			[
				'label'       => esc_html__( 'Workflow guide', 'angie' ),
				'description' => esc_html__( 'Step-by-step workflow for creating and editing snippets.', 'angie' ),
				'uri'         => 'angie://code-snippet/guide/workflow',
				'file'        => $content_dir . '/workflow.md',
			]
		);

		self::register_module_markdown_resource(
			'angie/elementor-widget-requirements',
			[
				'label'       => esc_html__( 'Elementor widget requirements', 'angie' ),
				'description' => esc_html__( 'Requirements for building Elementor widgets as snippets.', 'angie' ),
				'uri'         => 'angie://code-snippet/guide/elementor-widget-requirements',
				'file'        => $content_dir . '/elementor-widget-requirements.md',
			]
		);

		self::register_module_markdown_resource(
			'angie/block-requirements',
			[
				'label'       => esc_html__( 'Block requirements', 'angie' ),
				'description' => esc_html__( 'Requirements for building Gutenberg blocks as snippets.', 'angie' ),
				'uri'         => 'angie://code-snippet/guide/block-requirements',
				'file'        => $content_dir . '/block-requirements.md',
			]
		);

		self::register_module_markdown_resource(
			'angie/extend-elementor-guide',
			[
				'label'       => esc_html__( 'Extend Elementor guide', 'angie' ),
				'description' => esc_html__( 'Guide for extending Elementor features via snippets.', 'angie' ),
				'uri'         => 'angie://code-snippet/guide/extend-elementor',
				'file'        => $content_dir . '/extend-elementor.md',
			]
		);

		self::register_module_markdown_resource(
			'angie/ask-for-snippet-details',
			[
				'label'       => esc_html__( 'Ask for snippet details', 'angie' ),
				'description' => esc_html__( 'When and how to ask clarifying questions using the host ask tool.', 'angie' ),
				'uri'         => 'angie://code-snippet/guide/ask-for-snippet-details',
				'file'        => $content_dir . '/ask-for-snippet-details.md',
			]
		);
	}

	protected static function register_module_rest_tool( string $name, array $config ): void {
		parent::register_rest_tool(
			$name,
			$config,
			self::REST_NAMESPACE,
			Angie_Ability_Permissions::gate(
				static function () {
					return Module::current_user_can_manage_snippets();
				}
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function get_snippet_type_arg_definition(): array {
		return [
			'type'        => 'string',
			'enum'        => self::SNIPPET_TYPES,
			'description' => esc_html__( 'Snippet type.', 'angie' ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function get_snippet_files_arg_definition(): array {
		return [
			'required'    => true,
			'type'        => 'array',
			'items'       => [
				'type'       => 'object',
				'properties' => [
					'name'    => [ 'type' => 'string' ],
					'content' => [ 'type' => 'string' ],
				],
				'required'   => [ 'name', 'content' ],
			],
			'description' => esc_html__( 'Array of files with name and content.', 'angie' ),
		];
	}

	protected static function register_module_markdown_resource( string $name, array $config ): void {
		parent::register_markdown_resource(
			$name,
			$config,
			Angie_Ability_Permissions::gate(
				static function () {
					return Module::current_user_can_manage_snippets();
				}
			)
		);
	}
}
