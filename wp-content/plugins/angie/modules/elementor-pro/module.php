<?php

namespace Angie\Modules\ElementorPro;

use Angie\Classes\Module_Base;
use Angie\Includes\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Pro Module
 *
 * Handles Pro plugin functionality and integrations
 */
class Module extends Module_Base {

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'elementor-pro';
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'angie_mcp_plugins', [ $this, 'add_elementor_pro_plugin_info' ] );
	}

	/**
	 * Add Elementor Pro plugin information to the plugins array
	 *
	 * @param array $plugins
	 * @return array
	 */
	public function add_elementor_pro_plugin_info( $plugins ) {
		$plugins['elementor_pro'] = [
			'isInstalled' => $this->is_plugin_installed( 'elementor-pro/elementor-pro.php' ),
			'isActive' => Utils::is_plugin_active( 'elementor-pro/elementor-pro.php' ),
			'version' => $this->get_plugin_version( 'elementor-pro/elementor-pro.php' ),
			'isVersionSupported' => null,
		];

		return $plugins;
	}

	/**
	 * Check if a plugin is installed
	 *
	 * @param string $plugin_path
	 * @return bool
	 */
	private function is_plugin_installed( $plugin_path ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins = get_plugins();
		return isset( $installed_plugins[ $plugin_path ] );
	}

	/**
	 * Get plugin version
	 *
	 * @param string $plugin_path
	 * @return string|null
	 */
	private function get_plugin_version( $plugin_path ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_path;
		if ( ! file_exists( $plugin_file ) ) {
			return null;
		}

		$plugin_data = get_plugin_data( $plugin_file );
		return isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : null;
	}

	/**
	 * Check if module is active
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return true; // Always active to provide plugin information.
	}
}
