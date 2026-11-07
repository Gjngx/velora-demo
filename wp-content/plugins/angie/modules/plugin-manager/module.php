<?php

namespace Angie\Modules\PluginManager;

use Angie\Classes\Module_Base;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Angie\Modules\PluginManager\Components\Plugin;
use Angie\Modules\PluginManager\Components\Plugin_Searcher;

/**
 * Plugin Manager Module
 *
 * Provides access to WordPress native plugin REST API endpoints
 */
class Module extends Module_Base {

	/**
	 * Plugin controller
	 *
	 * @var \Angie\Modules\PluginManager\Components\Plugin
	 */
	public $plugin;

	/**
	 * Plugin searcher
	 *
	 * @var \Angie\Modules\PluginManager\Components\Plugin_Searcher
	 */
	public $plugin_searcher;

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'plugin-manager';
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
		$this->plugin = new Plugin();
		$this->plugin_searcher = new Plugin_Searcher();
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
