<?php

namespace Angie\Modules\ThemeManager;

use Angie\Classes\Module_Base;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Angie\Modules\ThemeManager\Components\Theme_Installer;
use Angie\Modules\ThemeManager\Components\Theme_Activator;
use Angie\Modules\ThemeManager\Components\Theme_Searcher;
use Angie\Modules\ThemeManager\Components\Theme_Deleter;
use Angie\Modules\ThemeManager\Components\Theme_Updater;

class Module extends Module_Base {

	/**
	 * Theme Installer controller
	 *
	 * @var \Angie\Modules\ThemeManager\Components\Theme_Installer
	 */
	public $theme_installer;

	/**
	 * Theme Activator controller
	 *
	 * @var \Angie\Modules\ThemeManager\Components\Theme_Activator
	 */
	public $theme_activator;

	/**
	 * Theme Searcher controller
	 *
	 * @var \Angie\Modules\ThemeManager\Components\Theme_Searcher
	 */
	public $theme_searcher;

	/**
	 * Theme Updater controller
	 *
	 * @var \Angie\Modules\ThemeManager\Components\Theme_Updater
	 */
	public $theme_updater;

	/**
	 * Theme Deleter controller
	 *
	 * @var \Angie\Modules\ThemeManager\Components\Theme_Deleter
	 */
	public $theme_deleter;

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'theme-manager';
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
		$this->theme_installer = new Theme_Installer();
		$this->theme_activator = new Theme_Activator();
		$this->theme_searcher = new Theme_Searcher();
		$this->theme_updater = new Theme_Updater();
		$this->theme_deleter = new Theme_Deleter();
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
