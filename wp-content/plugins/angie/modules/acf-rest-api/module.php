<?php

namespace Angie\Modules\AcfRestApi;

use Angie\Classes\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Angie\Modules\AcfRestApi\Components\Post_Type;
use Angie\Modules\AcfRestApi\Components\Field;
use Angie\Modules\AcfRestApi\Components\FieldGroup;
use Angie\Modules\AcfRestApi\Components\Taxonomy;
use Angie\Modules\AcfRestApi\Classes\Legacy_Mcp_Plugin_Support;

class Module extends Module_Base {

	/**
	 * Post Type controller
	 *
	 * @var \Angie\Modules\AcfRestApi\Components\Post_Type
	 */
	public $post_type;

	/**
	 * Field controller
	 *
	 * @var \Angie\Modules\AcfRestApi\Components\Field
	 */
	public $field;

	/**
	 * Field Group controller
	 *
	 * @var \Angie\Modules\AcfRestApi\Components\FieldGroup
	 */
	public $field_group;

	/**
	 * Taxonomy controller
	 *
	 * @var \Angie\Modules\AcfRestApi\Components\Taxonomy
	 */
	public $taxonomy;

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'acf-rest-api';
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_rest_controllers();
		add_filter( 'angie_mcp_plugins', function ( $plugins ) {
			if ( Legacy_Mcp_Plugin_Support::should_register_legacy_acf_plugin() ) {
				$plugins['acf'] = [];
			}

			return $plugins;
		} );
	}

	/**
	 * Initialize controllers
	 */
	private function init_rest_controllers() {
		$this->post_type = new Post_Type();
		$this->field = new Field();
		$this->field_group = new FieldGroup();
		$this->taxonomy = new Taxonomy();
	}

	/**
	 * Check if module is active
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( 'ACF' );
	}
}
