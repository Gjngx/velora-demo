<?php

namespace Angie\Modules\Mcp;

use Angie\Classes\Module_Base;
use Angie\Modules\ConsentManager\Module as ConsentManager;
use Angie\Modules\WpAbilities\Classes\Mcp_Adapter_Ability_Discovery;
use Angie\Modules\WpAbilities\Classes\Mcp_Adapter_Ability_Registration;
use Angie\Modules\WpAbilities\Classes\Wp_Abilities_Support;
use Elementor\MCP\Composer\Admin\Page;
use Elementor\MCP\Composer\Mcp\Registry as Shared_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Module extends Module_Base {

	public function get_name(): string {
		return 'mcp';
	}

	public static function is_active(): bool {
		return class_exists( Page::class ) && class_exists( Shared_Registry::class );
	}

	public function __construct() {
		// Must not run on `init`: wp_get_abilities() lazily fires wp_abilities_api_init,
		// and rest_api_init (where the adapter registers default meta-tools) now runs
		// after `init`. Early ability registration empties mcp-adapter-default-server.
		add_action( 'mcp_adapter_init', [ $this, 'bootstrap_shared_registry' ], 1 );
		add_action( 'wp_abilities_api_init', [ $this, 'register_shared_registry_slugs' ], 110 );
		add_filter( 'mcp_adapter_pre_tool_call', [ $this, 'require_consent_before_tool_call' ], 10, 1 );

		if ( is_admin() ) {
			$this->register_components( [
				'Mcp_Page',
			] );
		}
	}

	public function bootstrap_shared_registry(): void {
		if ( function_exists( 'wp_get_abilities' ) ) {
			wp_get_abilities();
		}

		$this->register_shared_registry_slugs();
	}

	public function register_shared_registry_slugs(): void {
		if ( ! Wp_Abilities_Support::should_expose_angie_on_site_mcp() ) {
			return;
		}

		$shared = Shared_Registry::instance();
		$shared->register_tools( $this->get_shared_tool_slugs() );
		$shared->register_resources( Mcp_Adapter_Ability_Discovery::get_mcp_resource_ability_names() );
	}

	/**
	 * @return string[]
	 */
	private function get_shared_tool_slugs(): array {
		$tools = [];

		if ( function_exists( 'wp_get_abilities' ) ) {
			foreach ( wp_get_abilities() as $ability ) {
				if ( Mcp_Adapter_Ability_Discovery::is_discoverable_mcp_tool( $ability ) ) {
					$tools[] = $ability->get_name();
				}
			}
		}

		foreach ( Mcp_Adapter_Ability_Registration::get_adapter_ability_names() as $name ) {
			if ( null !== Wp_Abilities_Support::get_registered_ability( $name ) ) {
				$tools[] = $name;
			}
		}

		return array_values( array_unique( $tools ) );
	}

	/**
	 * Block MCP tools/call when external-scripts consent is missing.
	 * tools/list is unaffected (filter is call-only).
	 *
	 * @param array|\WP_Error $args Tool arguments (or prior WP_Error).
	 * @return array|\WP_Error
	 */
	public function require_consent_before_tool_call( $args ) {
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		if ( ConsentManager::has_consent() ) {
			return $args;
		}

		$consent_url = admin_url( 'admin.php?page=angie-app' );

		return new \WP_Error(
			'angie_consent_required',
			sprintf(
				/* translators: %s: absolute URL to the Angie consent / welcome page */
				esc_html__( 'Angie is installed but external-scripts consent has not been granted. MCP tools cannot run until you approve them here: %s', 'angie' ),
				$consent_url
			)
		);
	}
}
