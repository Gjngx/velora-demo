<?php

namespace Angie\Modules\Notifications;

use Angie\Classes\Module_Base;
use Elementor\WPNotificationsPackage\V120\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Notifications Module
 *
 * Manages WordPress notifications using Elementor's WPNotifications package
 */
class Module extends Module_Base {

	/**
	 * @var Notifications|null
	 */
	public ?Notifications $notifications = null;

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'notifications';
	}

	/**
	 * Check if module should be active
	 * Only load in admin area and if the WPNotifications class is available
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return is_admin() && class_exists( 'Elementor\WPNotificationsPackage\V120\Notifications' );
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_notifications();
	}

	/**
	 * Initialize the WPNotifications package
	 */
	private function init_notifications() {
		// Double check the class exists before instantiating.
		if ( ! class_exists( 'Elementor\WPNotificationsPackage\V120\Notifications' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Angie Notifications Module: WPNotifications package not found. Make sure composer dependencies are installed.' );
			}
			return;
		}

		try {
			$this->notifications = new Notifications([
				'app_name' => 'angie',
				'app_version' => ANGIE_VERSION,
				'short_app_name' => 'angie',
				'app_data' => [
					'plugin_basename' => plugin_basename( ANGIE_PATH . 'angie.php' ),
				],
			]);
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Angie Notifications Module: Failed to initialize notifications - ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Get notifications instance
	 *
	 * @return Notifications|null
	 */
	public function get_notifications(): ?Notifications {
		return $this->notifications;
	}

	/**
	 * Check if notifications are available
	 *
	 * @return bool
	 */
	public function has_notifications(): bool {
		return null !== $this->notifications;
	}
}
