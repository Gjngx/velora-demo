<?php

namespace Angie\Modules\PluginManager\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Base class for Plugin Manager REST API endpoints
 */
abstract class Base {
	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	protected $namespace = 'wp/v2';

	/**
	 * REST API base
	 *
	 * @var string
	 */
	protected $rest_base;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Check if a user has permission to access the API
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'install_plugins' );
	}

	/**
	 * Register REST API routes
	 */
	abstract public function register_routes();
}
