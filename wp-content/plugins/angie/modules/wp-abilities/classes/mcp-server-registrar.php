<?php

namespace Angie\Modules\WpAbilities\Classes;

use Angie\Modules\CodeSnippets\Classes\Abilities_Registrar as Code_Snippets_Abilities_Registrar;
use Angie\Modules\CodeSnippets\Module as Code_Snippets_Module;
use Angie\Modules\ElementorCore\Components\Abilities_Registrar as Elementor_Core_Abilities_Registrar;
use Angie\Modules\ElementorCore\Module as Elementor_Core_Module;
use Angie\Modules\SuperAdmin\Classes\Abilities_Registrar as Super_Admin_Abilities_Registrar;
use Angie\Modules\SuperAdmin\Module as Super_Admin_Module;
use WP\MCP\Core\McpAdapter;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WP\MCP\Transport\HttpTransport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Server_Registrar {

	private const ROUTE_NAMESPACE = 'mcp';

	private const SERVER_ID = 'angie';

	private const SERVER_ROUTE = 'angie';

	private const SERVER_VERSION = '1.0.0';

	public static function register_servers( $adapter ): void {
		if ( ! $adapter instanceof McpAdapter ) {
			return;
		}

		if ( ! Wp_Abilities_Support::is_mcp_adapter_supported() || ! Wp_Abilities_Support::should_expose_angie_on_site_mcp() ) {
			return;
		}

		self::ensure_angie_abilities_registered();

		$resources = Mcp_Adapter_Ability_Discovery::get_mcp_resource_ability_names();
		$server_tools = self::resolve_server_tools();
		if ( empty( $resources ) && ! self::has_discoverable_mcp_tool_abilities() && empty( $server_tools ) ) {
			return;
		}

		$adapter->create_server(
			self::SERVER_ID,
			self::ROUTE_NAMESPACE,
			self::SERVER_ROUTE,
			esc_html__( 'Angie', 'angie' ),
			Mcp_Server_Instructions::get(),
			self::SERVER_VERSION,
			[ HttpTransport::class ],
			ErrorLogMcpErrorHandler::class,
			NullMcpObservabilityHandler::class,
			$server_tools,
			$resources,
			[],
			[ Mcp_Adapter_Authentication::class, 'check_transport_permission' ]
		);
	}

	private static function ensure_angie_abilities_registered(): void {
		$registrars = [
			[
				'registrar' => Code_Snippets_Abilities_Registrar::class,
				'probe'     => 'angie/list-snippets',
				'is_active' => static fn() => Code_Snippets_Module::is_active(),
			],
			[
				'registrar' => Elementor_Core_Abilities_Registrar::class,
				'probe'     => 'angie/get-elementor-kit',
				'is_active' => static fn() => Elementor_Core_Module::is_active(),
			],
			[
				'registrar' => Super_Admin_Abilities_Registrar::class,
				'probe'     => 'angie/get-super-admin-status',
				'is_active' => static fn() => Super_Admin_Module::is_active(),
			],
		];

		foreach ( $registrars as $config ) {
			$is_active = $config['is_active'];
			if ( ! is_callable( $is_active ) || ! $is_active() ) {
				continue;
			}

			if ( Wp_Abilities_Support::get_registered_ability( $config['probe'] ) ) {
				continue;
			}

			$config['registrar']::register_category();
			$config['registrar']::register_abilities();
		}
	}

	private static function has_discoverable_mcp_tool_abilities(): bool {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return false;
		}

		foreach ( wp_get_abilities() as $ability ) {
			if ( Mcp_Adapter_Ability_Discovery::is_discoverable_mcp_tool( $ability ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string[]
	 */
	private static function resolve_server_tools(): array {
		return array_values(
			array_filter(
				Mcp_Adapter_Ability_Registration::get_adapter_ability_names(),
				static fn( string $name ) => null !== Wp_Abilities_Support::get_registered_ability( $name )
			)
		);
	}
}
