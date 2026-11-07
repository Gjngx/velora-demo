<?php

namespace Angie\Modules\Sidebar;

use Angie\Classes\Module_Base;

use Angie\Modules\ConsentManager\Module as ConsentManagerModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sidebar Module
 *
 * Manages the toggleable sidebar that pushes WordPress content to the right
 * with RTL support, accessibility compliance, and iframe integration.
 */
class Module extends Module_Base {

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'sidebar';
	}

	/**
	 * Check if module is active.
	 *
	 * Sidebar is only active for administrators who have given consent and enabled Angie.
	 *
	 * @return bool True if module should be active.
	 */
	public static function is_active(): bool {
		return ConsentManagerModule::has_consent() && current_user_can( 'use_angie' );
	}

	/**
	 * Constructor
	 *
	 * Registers all sidebar components for automatic loading.
	 */
	public function __construct() {
		$this->register_components([
			'Sidebar_HTML',
			'Sidebar_Css_Injector',
			'Sidebar_Admin_Bar',
		]);
	}
}
