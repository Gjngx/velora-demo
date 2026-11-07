<?php
namespace Angie\Modules\SuperAdmin;

use Angie\Classes\Module_Base;
use Angie\Modules\SuperAdmin\Classes\Rest_Api_Controller;
use Angie\Modules\SuperAdmin\Classes\Abilities_Registrar;
use Angie\Modules\WpAbilities\Classes\Wp_Abilities_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Module extends Module_Base {

	const FEATURE_FLAG_OPTION = 'angie_super_admin_enabled';

	public function get_name(): string {
		return 'super-admin';
	}

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_filter( 'angie_config', [ $this, 'inject_super_admin_config' ] );

		Wp_Abilities_Support::register_hooks( Abilities_Registrar::class );
	}

	public function inject_super_admin_config( array $config ): array {
		$is_enabled = self::is_enabled() && self::current_user_can_use();
		$config['superAdmin'] = [
			'enabled' => $is_enabled,
			'execNonce' => $is_enabled ? Classes\Execution_Token::generate() : null,
		];
		return $config;
	}

	public function register_rest_routes() {
		( new Rest_Api_Controller() )->register_routes();
	}

	public static function is_active(): bool {
		return true;
	}

	public static function is_enabled(): bool {
		return (bool) get_option( self::FEATURE_FLAG_OPTION, false );
	}

	/**
	 * Whether an admin has explicitly set the option (Active or Disabled).
	 * Returns false on fresh installs where the row doesn't exist yet.
	 */
	public static function has_explicit_setting(): bool {
		$value = get_option( self::FEATURE_FLAG_OPTION, null );

		return null !== $value;
	}

	public static function current_user_can_use(): bool {
		if ( is_multisite() ) {
			return current_user_can( 'manage_network_options' );
		}
		return current_user_can( 'manage_options' );
	}
}
