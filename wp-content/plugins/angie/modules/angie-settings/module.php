<?php

namespace Angie\Modules\AngieSettings;

use Angie\Classes\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Angie\Modules\AngieSettings\Components\Settings;
use Angie\Modules\AngieSettings\Components\Page_Templates;
use Angie\Modules\AngieSettings\Components\WP_Options;
use Angie\Modules\AngieSettings\Components\Preferences;
use Angie\Modules\AngieSettings\Components\Elementor_Settings;
use Angie\Modules\AngieSettings\Components\Token_Provider;

class Module extends Module_Base {

	/**
	 * Settings controller
	 *
	 * @var \Angie\Modules\AngieSettings\Components\Settings
	 */
	public $settings;

	/**
	 * Page Templates controller
	 *
	 * @var \Angie\Modules\AngieSettings\Components\Page_Templates
	 */
	public $page_templates;

	/**
	 * WordPress Options controller
	 *
	 * @var \Angie\Modules\AngieSettings\Components\WP_Options
	 */
	public $wp_options;

	/**
	 * Preferences controller
	 *
	 * @var \Angie\Modules\AngieSettings\Components\Preferences
	 */
	public $preferences;

	/**
	 * Elementor Settings controller
	 *
	 * @var \Angie\Modules\AngieSettings\Components\Elementor_Settings
	 */
	public $elementor_settings;

	/**
	 * Token Provider controller
	 *
	 * @var \Angie\Modules\AngieSettings\Components\Token_Provider
	 */
	public $token_provider;

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'angie-settings';
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
		$this->settings = new Settings();
		$this->page_templates = new Page_Templates();
		$this->wp_options = new WP_Options();
		$this->preferences = new Preferences();
		$this->elementor_settings = new Elementor_Settings();
		$this->token_provider = new Token_Provider();
	}

	/**
	 * Check if module is active
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return true; // Always active.
	}
}
