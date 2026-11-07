<?php

namespace Angie\Modules\AngieAgents\Components;

use Angie\Includes\Utils;
use Angie\Modules\ConsentManager\Module as ConsentManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin "Agents" screen — coming-soon by default; platform iframe when `angie_agents_platform` gate is open.
 */
class Agents_Page {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function register_admin_menu(): void {
		if ( ! ConsentManager::has_consent() ) {
			return;
		}

		add_submenu_page(
			'angie-app',
			esc_html__( 'Agents', 'angie' ),
			esc_html__( 'Agents', 'angie' ),
			'manage_options',
			'angie-agents',
			[ $this, 'render_agents_page' ],
			2
		);
	}

	/**
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		$current_page = Utils::get_sanitized_query_var( 'page' );
		$is_agents_screen = ( 'angie-agents' === $current_page )
			|| ( 'angie-app_page_angie-agents' === $hook_suffix );

		if ( ! $is_agents_screen ) {
			return;
		}

		$plugin_root_file = ANGIE_PATH . 'angie.php';
		$agents_css_url = plugins_url( 'modules/angie-agents/assets/agents-page.css', $plugin_root_file );
		$agents_js_url = plugins_url( 'modules/angie-agents/assets/agents-page.js', $plugin_root_file );

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External Google Fonts resource, version not applicable
		wp_enqueue_style(
			'angie-google-fonts',
			'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
			[],
			null
		);

		wp_enqueue_style(
			'angie-agents-page',
			$agents_css_url,
			[],
			ANGIE_VERSION
		);

		wp_enqueue_script(
			'angie-agents-page',
			$agents_js_url,
			[],
			ANGIE_VERSION,
			true
		);

		wp_localize_script(
			'angie-agents-page',
			'angieAgentsPageConfig',
			[
				'wpUsername' => wp_get_current_user()->user_login,
			]
		);
	}

	public function render_agents_page(): void {
		$icon_url = plugins_url( 'modules/consent-manager/assets/angieIcon.svg', ANGIE_PATH . 'angie.php' );
		?>
		<div class="angie-agents-page" id="angie-agents-page">
			<div class="angie-agents-platform-loading" id="angie-agents-platform-loading" aria-live="polite" aria-busy="true" hidden>
				<span class="angie-agents-platform-loading-spinner" aria-hidden="true"></span>
				<p class="angie-agents-platform-loading-text"><?php esc_html_e( 'Loading agents…', 'angie' ); ?></p>
			</div>
			<div class="angie-agents-platform-host" id="angie-agents-platform-host" hidden></div>
			<div class="angie-agents-content" id="angie-agents-coming-soon">
				<div class="angie-agents-logo">
					<img src="<?php echo esc_url( $icon_url ); ?>"
						alt="" class="angie-agents-logo-icon" />
					<span class="angie-agents-logo-text"><?php esc_html_e( 'angie', 'angie' ); ?></span>
				</div>
				<h1 class="angie-agents-headline">
					<?php esc_html_e( 'Agents are coming ', 'angie' ); ?><span class="angie-agents-headline-soon"><?php esc_html_e( 'soon', 'angie' ); ?></span>
				</h1>
				<p class="angie-agents-subtitle">
					<?php esc_html_e( 'Run agents powered by real WordPress expertise and full context of your site.', 'angie' ); ?>
					<br />
					<?php esc_html_e( 'More gets done while your attention stays where it belongs.', 'angie' ); ?>
				</p>
				<button class="angie-agents-notify-btn" type="button" id="angie-agents-notify-btn">
					<?php esc_html_e( "Notify me when it's ready", 'angie' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
