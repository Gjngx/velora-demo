<?php

namespace Angie\Modules\AcfRestApi\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Legacy_Mcp_Plugin_Support {

	public static function should_register_legacy_acf_plugin(): bool {
		return ! self::has_registered_acf_abilities();
	}

	public static function has_registered_acf_abilities(): bool {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return false;
		}

		foreach ( wp_get_abilities() as $ability ) {
			$ability_name = $ability->get_name();

			if ( str_starts_with( $ability_name, 'acf/' ) ) {
				return true;
			}
		}

		return false;
	}
}
