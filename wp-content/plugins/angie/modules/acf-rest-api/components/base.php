<?php

namespace Angie\Modules\AcfRestApi\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Base class for ACF REST API endpoints
 */
abstract class Base {
	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	protected $namespace = 'acf/v1';

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
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register REST API routes
	 */
	abstract public function register_routes();
}
