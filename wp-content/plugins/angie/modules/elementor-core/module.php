<?php

namespace Angie\Modules\ElementorCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Angie\Classes\Module_Base;
use Angie\Modules\ConsentManager\Module as ConsentManager;
use Angie\Plugin;
use Angie\Modules\ElementorCore\Components\Kit_Provider;
use Angie\Modules\ElementorCore\Components\Component_Preview_Fix;
use Angie\Modules\ElementorCore\Components\Elementor_Library_Type_Sync;
use Angie\Modules\ElementorCore\Components\Elementor_Library_Template_Types;
use Angie\Modules\ElementorCore\Components\Abilities_Registrar;
use Angie\Modules\WpAbilities\Classes\Wp_Abilities_Support;
use Angie\Includes\Utils;
/**
 * Module `Elementor Editor`
 *
 * A module is responsible over a specific part of the app logic,
 * Typically it is constructed by a main Module class (the class in this file) and components (e.g. `A_Component)
 * depending on its role, it may have additional parts such as `database` or `rest` etc'
 *
 * Please describe the role of your module.
 */
class Module extends Module_Base {

	/**
	 * Kit Provider controller
	 *
	 * @var \Angie\Modules\ElementorCore\Components\Kit_Provider
	 */
	public $kit_provider;

	public function get_name(): string {
		return 'elementor-core';
	}

	public static function is_active(): bool {
		return ConsentManager::has_consent() && Utils::is_plugin_active( 'elementor/elementor.php' );
	}

	protected function __construct() {
		new Component_Preview_Fix();
		$this->init_rest_controllers();
		Wp_Abilities_Support::register_hooks( Abilities_Registrar::class );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'elementor/elements/categories_registered', [ $this, 'register_widget_categories' ] );
		add_action( 'elementor/editor/templates/panel/category', [ $this, 'render_angie_category_generate_button' ] );
		add_action( 'elementor/editor/templates/panel/category/content', [ $this, 'render_angie_category_empty_state' ] );
		add_filter( 'angie_mcp_plugins', function ( $plugins ) {
			$plugins['elementor'] = [];
			return $plugins;
		} );
	}

	public function register_widget_categories( $elements_manager ) {
		$elements_manager->add_category(
			'angie-widgets',
			[
				'title' => esc_html__( 'Custom widgets', 'angie' ),
				'icon' => 'eicon-ai',
				'hideIfEmpty' => false,
				'active' => true,
			]
		);
	}

	public function render_angie_category_generate_button() {
		?><# if ( 'angie-widgets' === name ) { #>
		<span class="angie-category-generate" data-angie-generate-widget style="display: inline-flex; align-items: center; gap: 4px; margin-inline-start: auto; color: #C00BB9; color: light-dark(#C00BB9, #F0ABFC); font-size: 12px; font-weight: 500; cursor: pointer;">
			<?php echo esc_html__( 'Create', 'angie' ); ?>
		</span>
		<# } #><?php
	}

	public function render_angie_category_empty_state() {
		if ( $this->has_angie_widgets() ) {
			return;
		}
		?><# if ( 'angie-widgets' === name ) { #>
		<div class="angie-category-empty-state" data-angie-category-empty-state style="grid-column: 1 / -1; width: 100%; padding: 12px 20px;">
			<p style="color: #A4AFB7; font-size: 12px; margin: 0; line-height: 1.4;"><?php echo esc_html__( 'Create custom widgets by describing what you need.', 'angie' ); ?></p>
		</div>
		<# } #><?php
	}

	private function has_angie_widgets(): bool {
		$widgets = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();

		foreach ( $widgets as $widget ) {
			if ( in_array( 'angie-widgets', $widget->get_categories(), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Elementor library type sync controller
	 *
	 * @var \Angie\Modules\ElementorCore\Components\Elementor_Library_Type_Sync
	 */
	public $elementor_library_type_sync;

	public $elementor_library_template_types;

	private function init_rest_controllers() {
		$this->kit_provider = new Kit_Provider();
		$this->elementor_library_type_sync = new Elementor_Library_Type_Sync();
		$this->elementor_library_template_types = new Elementor_Library_Template_Types();
	}

	public function enqueue_scripts() {
		/**
		 * @var \Angie\Modules\AngieApp\Module
		 */
		$app_module = Plugin::instance()->modules_manager->get_modules( 'AngieApp' );

		if ( ! $app_module ) {
			return;
		}

		/**
		 * @var \Angie\Modules\AngieApp\Components\Angie_App
		 */
		$app_component = $app_module->get_component( 'Angie_App' );

		if ( ! $app_component ) {
			return;
		}

		$app_component->enqueue_scripts();
	}
}
