<?php

namespace Angie\Modules\AngieAgents;

use Angie\Classes\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Angie Agents admin module (coming-soon Agents screen; platform iframe via `angie_agents_platform` gate).
 */
class Module extends Module_Base {

	public function get_name(): string {
		return 'angie-agents';
	}

	public static function is_active(): bool {
		return is_admin();
	}

	public function __construct() {
		$this->register_components( [
			'Agents_Page',
		] );
	}
}
