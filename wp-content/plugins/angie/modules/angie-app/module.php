<?php

namespace Angie\Modules\AngieApp;

use Angie\Classes\Module_Base;

use Angie\Modules\ConsentManager\Module as ConsentManagerModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Angie App Module
 *
 * Manages the Angie application integration
 */
class Module extends Module_Base {

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'angie-app';
	}

	public static function is_active(): bool {
		return current_user_can( 'use_angie' );
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register components.
		$this->register_components([
			'Angie_App',
		]);
	}
}
