<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Args_Schema_Builder {

	public static function from_rest_args( array $args ): array {
		if ( empty( $args ) ) {
			return [
				'type'                 => 'object',
				'properties'           => [],
				'additionalProperties' => false,
				'default'              => (object) [],
			];
		}

		$properties = [];
		$required   = [];

		foreach ( $args as $name => $definition ) {
			if ( ! is_string( $name ) || ! is_array( $definition ) ) {
				continue;
			}

			$property = self::map_rest_arg_to_property( $definition );
			if ( null === $property ) {
				continue;
			}

			$properties[ $name ] = $property;

			if ( ! empty( $definition['required'] ) ) {
				$required[] = $name;
			}
		}

		$schema = [
			'type'       => 'object',
			'properties' => $properties,
		];

		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}

		return $schema;
	}

	private static function map_rest_arg_to_property( array $definition ): ?array {
		$type = $definition['type'] ?? 'string';

		$property = [
			'type' => $type,
		];

		if ( ! empty( $definition['description'] ) && is_string( $definition['description'] ) ) {
			$property['description'] = $definition['description'];
		}

		if ( isset( $definition['default'] ) ) {
			$property['default'] = $definition['default'];
		}

		if ( 'array' === $type && isset( $definition['items'] ) && is_array( $definition['items'] ) ) {
			$property['items'] = $definition['items'];
		}

		if ( ! empty( $definition['enum'] ) && is_array( $definition['enum'] ) ) {
			$property['enum'] = $definition['enum'];
		}

		return $property;
	}
}
