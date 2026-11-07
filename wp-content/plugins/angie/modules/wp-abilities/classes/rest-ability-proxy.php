<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Ability_Proxy {

	/**
	 * @return mixed
	 */
	public static function execute( string $namespace, string $method, string $route, array $input = [] ) {
		foreach ( self::extract_path_param_names( $route ) as $param_name ) {
			if ( ! array_key_exists( $param_name, $input ) ) {
				return new \WP_Error(
					'missing_path_param',
					sprintf( 'Missing required path parameter: %s', $param_name )
				);
			}
		}

		$resolved_route = self::resolve_route( $route, $input );
		$path           = '/' . trim( $namespace, '/' ) . $resolved_route;
		$request        = new \WP_REST_Request( $method, $path );

		$path_param_names = self::extract_path_param_names( $route );
		foreach ( $input as $key => $value ) {
			if ( in_array( $key, $path_param_names, true ) ) {
				continue;
			}
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	public static function resolve_route( string $route, array $input ): string {
		return (string) preg_replace_callback(
			'/\(\?P<(\w+)>([^)]+)\)/',
			static function ( array $matches ) use ( $input ) {
				$name = $matches[1];
				if ( ! array_key_exists( $name, $input ) ) {
					return '';
				}

				$value = $input[ $name ];
				if ( is_int( $value ) || is_float( $value ) ) {
					return (string) $value;
				}

				return rawurlencode( (string) $value );
			},
			$route
		);
	}

	public static function extract_path_param_names( string $route ): array {
		if ( ! preg_match_all( '/\(\?P<(\w+)>/', $route, $matches ) ) {
			return [];
		}

		return $matches[1];
	}
}
