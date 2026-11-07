<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Adapter_Ability_Permissions {

	/**
	 * @return bool|\WP_Error
	 */
	public static function check_read_permission() {
		return Mcp_Adapter_Authentication::check();
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function check_target_ability_permission( $input = [] ) {
		$auth_check = Mcp_Adapter_Authentication::check();
		if ( is_wp_error( $auth_check ) ) {
			return $auth_check;
		}

		$ability_name = is_array( $input ) ? ( $input['ability_name'] ?? '' ) : '';
		return self::validate_target_ability( (string) $ability_name );
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function check_execute_permission( $input = [] ) {
		$auth_check = Mcp_Adapter_Authentication::check();
		if ( is_wp_error( $auth_check ) ) {
			return $auth_check;
		}

		$ability_name = is_array( $input ) ? ( $input['ability_name'] ?? '' ) : '';
		$target_check = self::validate_target_ability( (string) $ability_name );
		if ( is_wp_error( $target_check ) ) {
			return $target_check;
		}

		$ability = Wp_Abilities_Support::get_ability( $ability_name );
		if ( ! $ability ) {
			return new \WP_Error( 'ability_not_found', sprintf( 'Ability "%s" not found.', $ability_name ) );
		}

		$parameters = is_array( $input ) ? ( $input['parameters'] ?? null ) : null;

		return $ability->check_permissions( $parameters );
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function check_read_resource_permission( $input = [] ) {
		$auth_check = Mcp_Adapter_Authentication::check();
		if ( is_wp_error( $auth_check ) ) {
			return $auth_check;
		}

		$uri = is_array( $input ) ? trim( (string) ( $input['uri'] ?? '' ) ) : '';
		if ( '' === $uri ) {
			return new \WP_Error( 'missing_uri', 'Resource URI is required.' );
		}

		$ability = Mcp_Adapter_Ability_Discovery::find_mcp_resource_ability_by_uri( $uri );
		if ( ! $ability ) {
			return new \WP_Error( 'resource_not_found', sprintf( 'MCP resource not found for URI "%s".', $uri ) );
		}

		return $ability->check_permissions();
	}

	/**
	 * @return bool|\WP_Error
	 */
	private static function validate_target_ability( string $ability_name ) {
		if ( '' === $ability_name ) {
			return new \WP_Error( 'missing_ability_name', 'Ability name is required.' );
		}

		if ( in_array( $ability_name, Mcp_Adapter_Ability_Registration::get_adapter_ability_names(), true ) ) {
			return new \WP_Error( 'ability_not_allowed', 'MCP adapter meta abilities cannot be used as execution targets.' );
		}

		$ability = Wp_Abilities_Support::get_ability( $ability_name );
		if ( ! $ability ) {
			return new \WP_Error( 'ability_not_found', sprintf( 'Ability "%s" not found.', $ability_name ) );
		}

		if ( ! Mcp_Adapter_Ability_Discovery::is_discoverable_mcp_tool( $ability ) ) {
			return new \WP_Error(
				'ability_not_public_mcp',
				sprintf( 'Ability "%s" is not exposed as an MCP tool.', $ability_name )
			);
		}

		return true;
	}
}
