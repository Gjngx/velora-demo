<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Adapter_Ability_Discovery {

	public static function get_mcp_resource_ability_names(): array {
		$names = [];

		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return $names;
		}

		foreach ( wp_get_abilities() as $ability ) {
			if ( ! self::is_discoverable_mcp_resource( $ability ) ) {
				continue;
			}

			$names[] = $ability->get_name();
		}

		return $names;
	}

	public static function is_discoverable_mcp_tool( \WP_Ability $ability ): bool {
		return self::is_discoverable_ability_of_type( $ability, 'tool' );
	}

	public static function is_discoverable_mcp_resource( \WP_Ability $ability ): bool {
		return self::is_discoverable_ability_of_type( $ability, 'resource' );
	}

	/**
	 * Angie abilities stay opt-in via mcp.public; third-party abilities of the
	 * given mcp.type are always exposed. Adapter meta abilities are never exposed.
	 */
	private static function is_discoverable_ability_of_type( \WP_Ability $ability, string $type ): bool {
		if ( in_array( $ability->get_name(), Mcp_Adapter_Ability_Registration::get_adapter_ability_names(), true ) ) {
			return false;
		}

		$meta     = $ability->get_meta();
		$mcp_meta = is_array( $meta['mcp'] ?? null ) ? $meta['mcp'] : [];

		if ( ( $mcp_meta['type'] ?? 'tool' ) !== $type ) {
			return false;
		}

		if ( Wp_Abilities_Support::is_angie_ability_name( $ability->get_name() ) ) {
			return ! empty( $mcp_meta['public'] );
		}

		return true;
	}

	public static function resolve_ability_resource_uri( \WP_Ability $ability ): string {
		$meta     = $ability->get_meta();
		$mcp_meta = is_array( $meta['mcp'] ?? null ) ? $meta['mcp'] : [];
		$uri      = $mcp_meta['uri'] ?? ( $meta['uri'] ?? '' );

		return is_string( $uri ) ? trim( $uri ) : '';
	}

	public static function find_mcp_resource_ability_by_uri( string $uri ): ?\WP_Ability {
		if ( ! function_exists( 'wp_get_abilities' ) || '' === $uri ) {
			return null;
		}

		foreach ( wp_get_abilities() as $ability ) {
			if ( ! self::is_discoverable_mcp_resource( $ability ) ) {
				continue;
			}

			if ( self::resolve_ability_resource_uri( $ability ) === $uri ) {
				return $ability;
			}
		}

		return null;
	}

	public static function collect_mcp_guide_resources(): array {
		$guides = [];

		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return $guides;
		}

		foreach ( wp_get_abilities() as $ability ) {
			if ( ! self::is_discoverable_mcp_resource( $ability ) ) {
				continue;
			}

			$uri = self::resolve_ability_resource_uri( $ability );
			if ( '' === $uri ) {
				continue;
			}

			$guides[] = [
				'uri'         => $uri,
				'label'       => $ability->get_label(),
				'description' => $ability->get_description(),
			];
		}

		return $guides;
	}
}
