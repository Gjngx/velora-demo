<?php
namespace Angie;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Plugin
 * Main Plugin class
 */
class Plugin {
	/**
	 * @var Plugin The single instance of the class.
	 */
	public static $instance = null;

	/**
	 * Modules Manager
	 *
	 * @var null|Manager
	 */
	public $modules_manager = null;

	/**
	 * @var array
	 */
	private array $classes_aliases = [];

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return Plugin An instance of the class.
	 */
	public static function instance(): ?Plugin {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, __NAMESPACE__ ) ) {
			return;
		}

		$has_class_alias = isset( $this->classes_aliases[ $class_name ] );

		// Backward Compatibility: Save old class name for set an alias after the new class is loaded.
		if ( $has_class_alias ) {
			$class_alias_name = $this->classes_aliases[ $class_name ];
			$class_to_load = $class_alias_name;
		} else {
			$class_to_load = $class_name;
		}

		if ( ! class_exists( $class_to_load ) ) {
			$filename = strtolower(
				preg_replace(
					[ '/^' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
					[ '', '$1-$2', '-', DIRECTORY_SEPARATOR ],
					$class_to_load
				)
			);
			$filename = ANGIE_PATH . $filename . '.php';

			if ( is_readable( $filename ) ) {
				include $filename;
			}
		}

		if ( $has_class_alias ) {
			class_alias( $class_alias_name, $class_name );
		}
	}

	private function init() {
		require_once ANGIE_PATH . 'includes/modules-manager.php';
		$this->modules_manager = new Manager();
	}


	/**
	 * Register plugin action hooks and filters
	 */
	public function __construct() {
		static $autoloader_registered = false;
		if ( ! $autoloader_registered ) {
			$autoloader_registered = spl_autoload_register( [ $this, 'autoload' ] );
		}
		$this->init();
		$this->register_heartbeat_nonce_refresh();
		$this->register_angie_default_body_class();
	}

	private function register_angie_default_body_class() {
		add_filter( 'body_class', [ $this, 'filter_body_class_angie_default' ] );
	}

	public function filter_body_class_angie_default( $classes ) {
		$classes[] = 'angie-default';
		return $classes;
	}

	private function register_heartbeat_nonce_refresh() {
		add_filter( 'heartbeat_received', [ $this, 'refresh_angie_nonce_on_heartbeat' ], 10, 2 );
	}

	public function refresh_angie_nonce_on_heartbeat( array $response, array $data ): array {
		if ( ! is_user_logged_in() ) {
			return $response;
		}
		$response['angie_nonce'] = wp_create_nonce( 'wp_rest' );
		return $response;
	}
}
// Instantiate Plugin Class.
Plugin::instance();
