<?php

namespace Angie;

use Angie\Classes\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Manager {
	/**
	 * @var Module_Base[]
	 */
	private array $modules = [];

	public static function get_module_list(): array {
		return [
			'AngieApp', // must be first for admin menu.
			'Mcp',
			'PluginAvailability',
			'ElementorCore',
			'ConsentManager',
			'AngieAgents',
			'AcfRestApi',
			'ThemeManager',
			'PluginManager',
			'Navigation',
			'AngieSettings',
			'WpAbilities',
			'CodeSnippets',
			'ElementorPro',
			'Notifications',
			'Sidebar',
			'PageTemplates',
			'SuperAdmin',
		];
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		$modules = self::get_module_list();

		foreach ( $modules as $module_name ) {
			$class_name = str_replace( '-', ' ', $module_name );
			$class_name = str_replace( ' ', '', ucwords( $class_name ) );
			$class_name = __NAMESPACE__ . '\\Modules\\' . $class_name . '\Module';

			/** @var Module_Base $class_name */
			if ( $class_name::is_active() ) {
				$this->modules[ $module_name ] = $class_name::instance();
			}
		}
	}


	public function get_modules( string $module_name = '' ) {
		if ( $module_name ) {
			return $this->modules[ $module_name ] ?? null;
		}

		return $this->modules;
	}
}
