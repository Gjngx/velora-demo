<?php

namespace Angie\Modules\AngieApp\Components;

use Angie\Modules\AngieApp\Classes\Legacy_Woocommerce_Mcp_Plugin_Support;
use Angie\Modules\ConsentManager\Module as ConsentManager;
use Angie\Modules\ConsentManager\Components\Consent_Page;
use Angie\Includes\Utils;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Angie App Component
 *
 * Creates a page to load the external Angie app via script
 */
class Angie_App {

	const OPTION_CONNECT_SITE_KEY = 'elementor_connect_site_key';


	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 1 );
	}

	/**
	 * Register the main menu for Angie App
	 *
	 * @param callable|null $callback Callback function to render the page.
	 */
	public static function register_main_menu( $callback = null ) {
		// Custom SVG icon for Angie menu
		$svg_icon = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
<path d="M21.7142 12.0059L21.0316 12.2611C19.4275 12.8645 18.4491 14.1524 17.8575 15.7883L17.6072 16.4845L17.3569 15.7883C16.7653 14.1524 15.73 12.8645 14.1259 12.2611L13.4433 12.0059L14.1259 11.7506C15.73 11.1473 16.7653 9.85935 17.3569 8.22337L17.6072 7.52721L17.8575 8.22337C18.4491 9.85935 19.4275 11.1473 21.0316 11.7506L21.7142 12.0059Z" fill="#ABAFB2"/>
<path d="M3 11.1104C8.94295 11.1104 13.707 16.0116 13.707 22L10.707 22C10.707 17.6126 7.23077 14.1104 3 14.1104L3 11.1104Z" fill="#ABAFB2"/>
<path d="M3 9.88965C7.23076 9.88965 10.707 6.38737 10.707 2L13.707 2C13.707 7.98835 8.94295 12.8896 3 12.8896L3 9.88965Z" fill="#ABAFB2"/>
</svg>'
		);

		add_menu_page(
			esc_html__( 'Angie', 'angie' ),
			esc_html__( 'Angie', 'angie' ),
			'manage_options',
			'angie-app', // Set the default page to the Angie App.
			$callback,
			$svg_icon,
			3
		);
	}


	public function register_admin_menu() {
		$has_consent = ConsentManager::has_consent();

		if ( ! $has_consent ) {
			// No consent: main menu shows welcome page
			$welcome_component = new Consent_Page();
			self::register_main_menu( [ $welcome_component, 'render_consent_page' ] );
		} else {
			// Has consent: main menu shows app page
			self::register_main_menu( [ $this, 'render_app_page' ] );

		// Add the Angie App as the first submenu item explicitly to ensure it's labeled correctly.
		add_submenu_page(
			'angie-app',
			esc_html__( 'Home', 'angie' ),
			esc_html__( 'Home', 'angie' ),
			'manage_options',
			'angie-app',
			[ $this, 'render_app_page' ],
			1 // Lower priority to ensure it appears first.
		);
		}
	}

	public function enqueue_scripts() {
		if ( ! current_user_can( 'use_angie' ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External Google Fonts resource, version not applicable
		wp_enqueue_style(
			'angie-google-fonts',
			'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
			[],
			null
		);

		// Exclude Site Planner Connect page from loading Angie app script.
		$excluded_pages = [
			'e-site-planner-password-generator',
		];
		// PHPcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = Utils::get_sanitized_query_var( 'page' );
		if ( $current_page && in_array( $current_page, $excluded_pages, true ) ) {
			return;
		}

		// Check if user has given consent.
		if ( ! ConsentManager::has_consent() ) {
			return;
		}

		// Register and enqueue the external script.
		wp_enqueue_script(
			'angie-app',
			'https://editor-static-bucket.elementor.com/angie.umd.cjs',
			[ 'wp-api-request' ],
			ANGIE_VERSION,
			false
		);

		$plugins = apply_filters( 'angie_mcp_plugins', [] );

		// Is WooCommerce active?
		if ( Utils::is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$plugins['woocommerce'] = [];
			$plugins['woocommerce']['isLegacyMcpEnabled'] = Legacy_Woocommerce_Mcp_Plugin_Support::should_register_legacy_woocommerce_plugin();

			// Only check for single product edit page in admin area where get_current_screen() is available.
			$is_single_product_edit_page = false;
			if ( is_admin() && function_exists( 'get_current_screen' ) ) {
				$screen = get_current_screen();
				if ( $screen ) {
					$is_single_product_edit_page = 'post' === $screen->base && 'product' === $screen->post_type;
				}
			}

			$plugins['woocommerce']['isSingleProductEdit'] = $is_single_product_edit_page;
		}

		if ( Utils::is_plugin_active( 'elementor/elementor.php' ) ) {
			$plugins['elementor'] = [];
		}

		if ( Utils::is_plugin_active( 'angie-acf-mcp/angie-acf-mcp.php' ) ) {
			$plugins['angie-acf-mcp'] = [];
		}

		$installed_plugins = $this->get_installed_plugins_info();


		$post_types_names = array_keys( get_post_types( [
			'show_in_menu' => true,
			'show_in_rest' => true,
		] ) );

		// Get current user data
		$current_user = wp_get_current_user();
		$wp_username = $current_user->exists() ? $current_user->display_name : null;
		$wp_user_role = $current_user->exists() && !empty($current_user->roles) ? $current_user->roles[0] : null;


		wp_add_inline_script(
			'angie-app',
			'window.angieConfig = ' . wp_json_encode( apply_filters( 'angie_config', [
				'plugins' => $plugins,
				'installedPlugins' => $installed_plugins,
				'postTypesNames' => $post_types_names,
				'version' => ANGIE_VERSION,
				'wpVersion' => get_bloginfo( 'version' ),
				'wpUsername' => $wp_username,
				'untrusted__wpUserRole' => $wp_user_role, // Used only for analytics - Never use for auth decisions
				'siteKey' => $this->get_site_key(),
				'isElementorOneConnected' => $this->is_elementor_one_connected(),
			] ) ),
			'before'
		);
	}

	private function is_oauth_flow_active() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_oauth_return = isset( $_GET['oauth_code'] ) || isset( $_GET['oauth_state'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_oauth_starting = isset( $_GET['start-oauth'] );
		return [
			'is_starting' => $is_oauth_starting,
			'is_returning' => $is_oauth_return,
			'is_active' => $is_oauth_starting || $is_oauth_return,
		];
	}

	private function get_site_key() {
		$site_key = \get_option( static::OPTION_CONNECT_SITE_KEY );

		if ( ! $site_key ) {
			$site_key = md5( uniqid( \wp_generate_password() ) );
			\update_option( static::OPTION_CONNECT_SITE_KEY, $site_key );
		}

		return $site_key;
	}

	private function get_installed_plugins_info() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );

		$plugins_info = [];

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$is_active = in_array( $plugin_file, $active_plugins, true );
			$plugin_name = $plugin_data['Name'];

			$plugins_info[ $plugin_name ] = [
				'version' => $plugin_data['Version'],
				'status' => $is_active ? 'active' : 'inactive',
			];
		}

		return $plugins_info;
	}


	private function is_elementor_one_connected() {
		if ( ! class_exists( '\ElementorOne\Connect\Facade' ) ) {
			return false;
		}
		$facade = \ElementorOne\Connect\Facade::get( 'elementor-one' );
		return $facade && $facade->utils()->is_connected();
	}

	public function render_app_page() {
		$oauth_state = $this->is_oauth_flow_active();
		$is_oauth_starting = $oauth_state['is_starting'];
		$is_oauth_return = $oauth_state['is_returning'];
		$is_in_oauth_flow = $oauth_state['is_active'];

		?>
		<style>
			body {
				background-color: #FFFFFF;
			}
		</style>

		<?php if ( ConsentManager::has_consent() ) : ?>
			<?php if ( $is_in_oauth_flow ) : ?>
				<div class="angie-loading-state" data-testid="angie-loading-state">
					<?php if ( $is_oauth_return ) : ?>
						<p><?php esc_html_e( 'Completing authentication...', 'angie' ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Redirecting to sign in...', 'angie' ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="angie-app-page" data-testid="angie-app-page"></div>
				<?php $this->render_app_styles(); ?>
			<?php endif; ?>
		<?php else : ?>
			<div class="wrap">
				<div class="angie-consent-required">
					<span class="dashicons dashicons-lock"></span>
					<h2><?php esc_html_e( 'External Scripts Consent Required', 'angie' ); ?></h2>
					<p><?php esc_html_e( 'To use the Angie App, you need to approve loading external scripts.', 'angie' ); ?></p>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=angie-consent' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Get Started with Angie', 'angie' ); ?>
						</a>
					</p>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	private function render_app_styles() {
		wp_enqueue_style(
			'angie-app',
			Utils::get_asset_url( 'app-styles.css', __DIR__ ),
			[],
			ANGIE_VERSION
		);
	}
}
