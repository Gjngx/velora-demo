<?php
namespace Angie\Modules\CodeSnippets\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dev_Mode_Admin_UI {

	public static function init() {
		add_filter( 'angie_config', [ __CLASS__, 'add_dev_mode_state_to_angie_config' ] );
	}

	public static function add_dev_mode_state_to_angie_config( $angie_config ) {
		$angie_config['isDevModeEnabled'] = Dev_Mode_Manager::is_dev_mode_enabled();
		return $angie_config;
	}
}
