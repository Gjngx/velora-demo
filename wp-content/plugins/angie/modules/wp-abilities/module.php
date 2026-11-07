<?php
namespace Angie\Modules\WpAbilities;

use Angie\Classes\Module_Base;
use Angie\Modules\WpAbilities\Classes\Wp_Abilities_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Module extends Module_Base {

	public function get_name(): string {
		return 'wp-abilities';
	}

	public function __construct() {
		Wp_Abilities_Support::register_mcp_hooks();
	}

	public static function is_active(): bool {
		return Wp_Abilities_Support::is_supported()
			&& Wp_Abilities_Support::is_mcp_adapter_supported();
	}
}
