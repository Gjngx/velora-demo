<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Angie_Ability_Permissions {

	/**
	 * Wrap a module-specific permission check with Angie MCP authentication (use_angie).
	 *
	 * @param callable(mixed): (bool|\WP_Error) $is_allowed Receives ability input when available.
	 */
	public static function gate( callable $is_allowed ): callable {
		return static function ( $input = null ) use ( $is_allowed ) {
			$auth = Mcp_Adapter_Authentication::check();
			if ( is_wp_error( $auth ) ) {
				return $auth;
			}

			$allowed = $is_allowed( $input );
			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}

			return (bool) $allowed;
		};
	}
}
