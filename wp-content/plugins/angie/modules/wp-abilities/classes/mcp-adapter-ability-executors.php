<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Adapter_Ability_Executors {

	/**
	 * @return array|\WP_Error
	 */
	public static function execute_discover() {
		$abilities = [];

		foreach ( wp_get_abilities() as $ability ) {
			if ( ! Mcp_Adapter_Ability_Discovery::is_discoverable_mcp_tool( $ability ) ) {
				continue;
			}

			$abilities[] = [
				'name'        => $ability->get_name(),
				'label'       => $ability->get_label(),
				'description' => $ability->get_description(),
			];
		}

		$result = [
			'angie_answer_format_instructions' => Mcp_Server_Instructions::get_answer_format_instructions(),
			'angie_instructions'               => Mcp_Server_Instructions::get_tool_instructions(),
			'abilities'                        => $abilities,
			'guides'                           => Mcp_Adapter_Ability_Discovery::collect_mcp_guide_resources(),
		];

		$output_validation = self::validate_ability_output(
			$result,
			Mcp_Adapter_Ability_Registration::DISCOVER_OUTPUT_SCHEMA,
			'angie/discover-abilities'
		);
		if ( is_wp_error( $output_validation ) ) {
			return $output_validation;
		}

		return $result;
	}

	public static function execute_get_ability_info( $input = [] ): array {
		$ability_name = is_array( $input ) ? ( $input['ability_name'] ?? '' ) : '';
		$ability      = Wp_Abilities_Support::get_ability( (string) $ability_name );

		if ( ! $ability ) {
			return [
				'success' => false,
				'error'   => sprintf( 'Ability "%s" not found.', $ability_name ),
			];
		}

		$info = [
			'name'         => $ability->get_name(),
			'label'        => $ability->get_label(),
			'description'  => $ability->get_description(),
			'input_schema' => $ability->get_input_schema(),
		];

		$output_schema = $ability->get_output_schema();
		if ( ! empty( $output_schema ) ) {
			$info['output_schema'] = $output_schema;
		}

		$meta = $ability->get_meta();
		if ( ! empty( $meta ) ) {
			$info['meta'] = $meta;
		}

		return $info;
	}

	public static function execute_ability( $input = [] ): array {
		$ability_name = is_array( $input ) ? ( $input['ability_name'] ?? '' ) : '';
		$ability      = Wp_Abilities_Support::get_ability( (string) $ability_name );

		if ( ! $ability ) {
			return [
				'success' => false,
				'error'   => sprintf( 'Ability "%s" not found.', $ability_name ),
			];
		}

		$parameters = is_array( $input ) ? ( $input['parameters'] ?? null ) : null;
		$parameters = self::normalize_parameters( $ability, $parameters );

		$result = $ability->execute( $parameters );
		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'error'   => $result->get_error_message(),
			];
		}

		$output_validation = self::validate_ability_output( $result, $ability->get_output_schema(), $ability_name );
		if ( is_wp_error( $output_validation ) ) {
			return [
				'success' => false,
				'error'   => $output_validation->get_error_message(),
			];
		}

		$response = [
			'success' => true,
			'data'    => $result,
		];

		$output_validation = self::validate_ability_output(
			$response,
			Mcp_Adapter_Ability_Registration::EXECUTE_ABILITY_OUTPUT_SCHEMA,
			$ability_name
		);
		if ( is_wp_error( $output_validation ) ) {
			return [
				'success' => false,
				'error'   => $output_validation->get_error_message(),
			];
		}

		return $response;
	}

	public static function execute_read_resource( $input = [] ): array {
		$uri = is_array( $input ) ? trim( (string) ( $input['uri'] ?? '' ) ) : '';
		if ( '' === $uri ) {
			return [
				'success' => false,
				'error'   => 'Resource URI is required.',
			];
		}

		$ability = Mcp_Adapter_Ability_Discovery::find_mcp_resource_ability_by_uri( $uri );
		if ( ! $ability ) {
			return [
				'success' => false,
				'error'   => sprintf( 'MCP resource not found for URI "%s".', $uri ),
			];
		}

		$result = $ability->execute();
		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'error'   => $result->get_error_message(),
			];
		}

		$content           = is_string( $result ) ? $result : (string) $result;
		$output_validation = self::validate_ability_output( $content, $ability->get_output_schema(), $uri );
		if ( is_wp_error( $output_validation ) ) {
			return [
				'success' => false,
				'error'   => $output_validation->get_error_message(),
			];
		}

		$meta     = $ability->get_meta();
		$mcp_meta = is_array( $meta['mcp'] ?? null ) ? $meta['mcp'] : [];

		$response = [
			'success'  => true,
			'uri'      => $uri,
			'mimeType' => $mcp_meta['mimeType'] ?? 'text/markdown',
			'content'  => $content,
		];

		$output_validation = self::validate_ability_output(
			$response,
			Mcp_Adapter_Ability_Registration::READ_RESOURCE_OUTPUT_SCHEMA,
			$uri
		);
		if ( is_wp_error( $output_validation ) ) {
			return [
				'success' => false,
				'error'   => $output_validation->get_error_message(),
			];
		}

		return $response;
	}

	/**
	 * @param mixed $parameters
	 * @return mixed
	 */
	private static function normalize_parameters( \WP_Ability $ability, $parameters ) {
		if ( class_exists( '\WP\MCP\Domain\Utils\AbilityArgumentNormalizer' ) ) {
			return \WP\MCP\Domain\Utils\AbilityArgumentNormalizer::normalize( $ability, $parameters );
		}

		return $parameters;
	}

	/**
	 * @param mixed $result
	 * @return bool|\WP_Error
	 */
	private static function validate_ability_output( $result, array $schema, string $context ) {
		if ( empty( $schema ) || ! function_exists( 'rest_validate_value_from_schema' ) ) {
			return true;
		}

		$validation = rest_validate_value_from_schema( $result, $schema, $context );
		if ( is_wp_error( $validation ) ) {
			return new \WP_Error(
				'invalid_ability_output',
				sprintf( 'Ability output failed schema validation: %s', $validation->get_error_message() )
			);
		}

		return true;
	}
}
