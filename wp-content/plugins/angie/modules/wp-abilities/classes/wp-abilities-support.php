<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wp_Abilities_Support {

	public static function is_supported(): bool {
		return function_exists( 'wp_register_ability' ) && function_exists( 'wp_register_ability_category' );
	}

	public static function is_mcp_adapter_supported(): bool {
		return class_exists( 'WP\MCP\Core\McpAdapter' ) && class_exists( 'WP\MCP\Transport\HttpTransport' );
	}

	/**
	 * Site-wide Elementor MCP switch (same option as Elementor Core / elementor-mcp-composer).
	 */
	public static function is_elementor_mcp_enabled(): bool {
		if ( class_exists( '\Elementor\MCP\Composer\Admin\McpSettingsController' ) ) {
			return \Elementor\MCP\Composer\Admin\McpSettingsController::is_enabled();
		}

		$value = get_option( 'elementor_mcp_enabled', null );

		if ( null === $value ) {
			return false;
		}

		return (bool) $value;
	}

	public static function should_expose_angie_on_site_mcp(): bool {
		return self::is_elementor_mcp_enabled();
	}

	public static function tool_mcp_meta(): array {
		return [
			'public' => self::is_elementor_mcp_enabled(),
		];
	}

	public static function resource_mcp_meta( string $mime_type = 'text/markdown' ): array {
		return [
			'public'   => self::is_elementor_mcp_enabled(),
			'type'     => 'resource',
			'mimeType' => $mime_type,
		];
	}

	public static function is_angie_ability_name( string $ability_name ): bool {
		return str_starts_with( $ability_name, 'angie/' );
	}

	/**
	 * Look up a registered ability without triggering WP 6.9+ incorrect-usage notices.
	 */
	public static function get_registered_ability( string $ability_name ): ?\WP_Ability {
		if ( ! function_exists( 'wp_get_abilities' ) || '' === $ability_name ) {
			return null;
		}

		foreach ( wp_get_abilities() as $ability ) {
			if ( $ability->get_name() === $ability_name ) {
				return $ability;
			}
		}

		return null;
	}

	public static function get_ability( string $name ): ?\WP_Ability {
		return self::get_registered_ability( $name );
	}

	public static function register_hooks( string $registrar_class ): void {
		if ( ! self::is_supported() ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', [ $registrar_class, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $registrar_class, 'register_abilities' ] );
	}

	public static function register_mcp_hooks(): void {
		if ( ! self::is_supported() || ! self::is_mcp_adapter_supported() ) {
			return;
		}

		if ( self::should_expose_angie_on_site_mcp() ) {
			add_action( 'wp_abilities_api_categories_init', [ Mcp_Adapter_Ability_Registration::class, 'register_category' ] );
			add_action( 'wp_abilities_api_init', [ Mcp_Adapter_Ability_Registration::class, 'register_abilities' ], 100 );
			add_action( 'mcp_adapter_init', [ Mcp_Server_Registrar::class, 'register_servers' ], 99 );
		}

		// Bootstrap ensures the legacy /mcp/angie route is registered; the advertised endpoint
		// is the unified /elementor/mcp server, fed Angie's abilities via modules/mcp.
		\WP\MCP\Core\McpAdapter::instance();
	}
}
