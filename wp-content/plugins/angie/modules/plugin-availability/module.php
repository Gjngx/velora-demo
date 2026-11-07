<?php

namespace Angie\Modules\PluginAvailability;

use Angie\Classes\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin Availability Module
 *
 * Exposes a synchronous window.angiePlugin marker for SDK consumers.
 */
class Module extends Module_Base {

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'plugin-availability';
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_components([
			'Availability_Marker',
		]);
	}
}
