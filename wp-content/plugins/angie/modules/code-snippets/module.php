<?php
namespace Angie\Modules\CodeSnippets;

use Angie\Classes\Module_Base;
use Angie\Modules\CodeSnippets\Classes\Post_Type_Manager;
use Angie\Modules\CodeSnippets\Classes\Taxonomy_Manager;
use Angie\Modules\CodeSnippets\Classes\Assets_Manager;
use Angie\Modules\CodeSnippets\Classes\Dev_Mode_Admin_UI;
use Angie\Modules\CodeSnippets\Classes\Files_Meta_Box;
use Angie\Modules\CodeSnippets\Classes\Deployment_Meta_Box;
use Angie\Modules\CodeSnippets\Classes\Cache_Manager;
use Angie\Modules\CodeSnippets\Classes\File_System_Handler;
use Angie\Modules\CodeSnippets\Classes\Dev_Mode_Manager;
use Angie\Modules\CodeSnippets\Classes\Rest_Api_Controller;
use Angie\Modules\CodeSnippets\Classes\List_Table_Manager;
use Angie\Modules\CodeSnippets\Classes\Fatal_Error_Handler;
use Angie\Modules\CodeSnippets\Classes\Abilities_Registrar;
use Angie\Modules\WpAbilities\Classes\Wp_Abilities_Support;
use Angie\Modules\CodeSnippets\Classes\Snippet_Preview_Template;
use Angie\Modules\CodeSnippets\PreviewSettings\Elementor_Preview_Document;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Module extends Module_Base {

	const CPT_NAME = 'angie_snippet';

	public function get_name(): string {
		return 'code-snippets';
	}

	public function __construct() {
		$this->maybe_exit_test_mode();

		Fatal_Error_Handler::init();

		$this->init_components();

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'wp_logout', [ Dev_Mode_Manager::class, 'clear_dev_mode_session' ] );

		Wp_Abilities_Support::register_hooks( Abilities_Registrar::class );

		if ( $this->should_load_snippets() ) {
			Dev_Mode_Manager::load_snippets();
		}
	}

	private function maybe_exit_test_mode() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not required for exiting test mode
		if ( ! isset( $_GET['angie-exit-test-mode'] ) ) {
			return;
		}

		Dev_Mode_Manager::clear_dev_mode_session();

		$redirect_url = remove_query_arg( 'angie-exit-test-mode' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function should_load_snippets() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return true;
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		// TODO: Find a better way to exclude the validation endpoint.
		$validation_patterns = [
			'/wp-json/angie/v1/snippets/validate',
			'rest_route=/angie/v1/snippets/validate',
			'rest_route=%2Fangie%2Fv1%2Fsnippets%2Fvalidate',
		];

		foreach ( $validation_patterns as $pattern ) {
			if ( strpos( $request_uri, $pattern ) !== false ) {
				return false;
			}
		}

		return true;
	}

	private function init_components() {
		require_once __DIR__ . '/utils.php';

		Elementor_Preview_Document::register_hooks();

		Post_Type_Manager::init();
		Taxonomy_Manager::init();
		Assets_Manager::init();
		Dev_Mode_Admin_UI::init();
		Files_Meta_Box::init();
		Deployment_Meta_Box::init();
		Cache_Manager::init();
		File_System_Handler::init();
		List_Table_Manager::init();
		Snippet_Preview_Template::init();
	}

	public function register_rest_routes() {
		$rest_api = new Rest_Api_Controller();
		$rest_api->register_routes();
	}

	public static function is_active(): bool {
		return true;
	}

	public static function current_user_can_manage_snippets(): bool {
		if ( is_multisite() ) {
			return current_user_can( 'manage_network_options' );
		}

		return current_user_can( 'manage_options' );
	}
}
