<?php

namespace Angie\Modules\Navigation;

use Angie\Classes\Module_Base;
use Angie\Modules\Navigation\Components\Menu_Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


class Module extends Module_Base {

	/**
	 * Menu Provider controller
	 *
	 * @var \Angie\Modules\Navigation\Components\Menu_Provider
	 */
	public $menu_provider;

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'navigation';
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_rest_controllers();
	}

	/**
	 * Initialize controllers
	 */
	private function init_rest_controllers() {
		$this->menu_provider = new Menu_Provider();
	}

	/**
	 * Check if module is active
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return current_user_can( 'use_angie' );
	}
}
