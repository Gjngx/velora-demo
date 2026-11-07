<?php

namespace Angie\Modules\ConsentManager\Components;

use Angie\Modules\ConsentManager\Module as ConsentManager;
use Angie\Modules\SuperAdmin\Module as SuperAdmin;
use Angie\Includes\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Consent Page Component
 *
 * Creates a welcome page for managing consent settings with OAuth integration
 */
class Consent_Page {
	private $consent_manager_module_file;

	public function __construct() {
		$this->consent_manager_module_file = dirname( __DIR__ ) . '/module.php';

		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'admin_init', [ $this, 'handle_reset_action' ] );
		add_action( 'admin_post_angie_update_super_admin', [ $this, 'handle_super_admin_toggle' ] );
	}

	public function register_admin_menu() {
		$consent = get_option( ConsentManager::CONSENT_OPTION_NAME, 'no' );

		// Only show submenu when consent is already granted (for settings)
		if ( ConsentManager::has_consent() ) {
			add_submenu_page(
				'angie-app', // Parent slug.
				esc_html__( 'Angie Settings', 'angie' ),
				esc_html__( 'Settings', 'angie' ),
				'manage_options',
				'angie-consent',
				[ $this, 'render_consent_page' ],
				20 // Lower priority for settings
			);
		}
	}

	/**
	 * Enqueue scripts for the consent page
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_angie-app' !== $hook && 'angie-app_page_angie-consent' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External Google Fonts resource, version not applicable
		wp_enqueue_style(
			'angie-google-fonts',
			'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
			[],
			null
		);

		$consent = get_option( ConsentManager::CONSENT_OPTION_NAME, 'no' );
		
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-i18n' );
		
		// Set up wp.apiFetch configuration
		wp_add_inline_script( 'wp-api-fetch', sprintf(
			'wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( %s ) );' .
			'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( %s ) );',
			wp_json_encode( rest_url() ),
			wp_json_encode( wp_create_nonce( 'wp_rest' ) )
		), 'after' );
		
		wp_localize_script( 'wp-api-fetch', 'angieConsent', [
			'restUrl' => rest_url( 'angie/v1/consent' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'hasConsent' => $consent === 'yes',
		] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		register_rest_route( 'angie/v1', '/consent', [
			'methods' => 'POST',
			'callback' => [ $this, 'handle_consent_grant_rest' ],
			'permission_callback' => [ $this, 'check_consent_permissions' ],
			'args' => [
				'return_to' => [
					'type' => 'string',
					'required' => false,
					'sanitize_callback' => 'esc_url_raw',
				],
			],
		] );

		register_rest_route( 'angie/v1', '/super-admin/opt-in', [
			'methods' => 'POST',
			'callback' => [ $this, 'handle_super_admin_opt_in' ],
			'permission_callback' => [ SuperAdmin::class, 'current_user_can_use' ],
		] );
	}

	public function register_settings() {
		register_setting(
			'angie_consent_settings',
			ConsentManager::CONSENT_OPTION_NAME,
			[
				'type'              => 'string',
				'default'           => 'no',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
	}

	/**
	 * Check permissions for consent REST endpoint
	 */
	public function check_consent_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle REST API request to grant consent and start OAuth
	 */
	public function handle_consent_grant_rest( $request ) {
		// Grant consent
		update_option( ConsentManager::CONSENT_OPTION_NAME, 'yes' );

		// Prefer bouncing back to the wp-admin page the user came from before
		// landing on the consent page (e.g. the Elementor editor or any 3rd-party
		// plugin screen). The client passes that URL as `return_to` because
		// `wp_get_referer()` here resolves to the consent page itself (the apiFetch
		// origin), not the original referrer.
		$return_to = $request->get_param( 'return_to' );
		$return_to = is_string( $return_to ) ? esc_url_raw( $return_to ) : '';

		$redirect_url = \Angie::resolve_post_install_target( $return_to, [ 'start-oauth' => '1' ] );

		return new \WP_REST_Response( [
			'message' => 'Consent granted successfully',
			'redirect' => $redirect_url,
		], \WP_Http::OK );
	}

	/**
	 * Handle REST API request to opt-in to Super Admin mode.
	 * Sets the `angie_super_admin_enabled` WP option so super-admin REST
	 * endpoints are unlocked. Called from the iframe confirmation banner
	 * via postMessage -> parent -> REST.
	 */
	public function handle_super_admin_opt_in() {
		if ( SuperAdmin::has_explicit_setting() && ! SuperAdmin::is_enabled() ) {
			return new \WP_Error(
				'super_admin_explicitly_disabled',
				esc_html__( 'Super Admin mode was explicitly disabled by an administrator.', 'angie' ),
				[ 'status' => \WP_Http::FORBIDDEN ]
			);
		}

		update_option( SuperAdmin::FEATURE_FLAG_OPTION, true );

		return new \WP_REST_Response( [
			'success' => true,
			'message' => 'Super Admin mode enabled',
		], \WP_Http::OK );
	}

	public function handle_reset_action() {
		$page    = Utils::get_sanitized_query_var( 'page' );
		$action  = Utils::get_sanitized_query_var( 'action' );
		$wpnonce = Utils::get_sanitized_query_var( '_wpnonce' );

		if ( 'angie-consent' !== $page ) {
			return;
		}

		if ( 'reset' !== $action ) {
			return;
		}

		if ( ! $wpnonce || ! wp_verify_nonce( $wpnonce, 'angie_reset_consent' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'angie' ) );
		}

		// Reset the consent setting.
		delete_option( ConsentManager::CONSENT_OPTION_NAME );

		// Bounce back to the originating wp-admin page (with `open-angie=1`) when
		// possible; otherwise fall back to the Angie welcome page.
		wp_safe_redirect( \Angie::resolve_post_install_target( wp_get_referer() ) );
		exit;
	}

	/**
	 * Handles the Super Admin toggle form submission from the Angie
	 * Settings page. Writes the `angie_super_admin_enabled` WP option
	 * after verifying nonce + admin capability.
	 */
	public function handle_super_admin_toggle() {
		if ( ! SuperAdmin::current_user_can_use() ) {
			wp_die( esc_html__( 'You do not have permission to change this setting.', 'angie' ) );
		}

		check_admin_referer( 'angie_update_super_admin' );

		$raw_state = isset( $_POST['angie_super_admin_state'] ) ? sanitize_key( wp_unslash( $_POST['angie_super_admin_state'] ) ) : 'default';

		if ( 'default' === $raw_state ) {
			delete_option( SuperAdmin::FEATURE_FLAG_OPTION );
		} else {
			update_option( SuperAdmin::FEATURE_FLAG_OPTION, 'active' === $raw_state );
		}

		$redirect = add_query_arg(
			[
				'page' => 'angie-consent',
			],
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public function render_consent_page() {
		$consent = get_option( ConsentManager::CONSENT_OPTION_NAME, 'no' );
		
		// If consent is already granted, show settings page
		if ( 'yes' === $consent ) {
			$this->render_settings_page();
			return;
		}
		
		?>
		<style>
			body {
				background-color: #FFFFFF;
			}
		</style>
		
		<div class="angie-welcome-page" data-testid="angie-welcome-page">
			<div class="angie-welcome-layout" data-testid="angie-welcome-layout">
				<div class="angie-welcome-hero" data-testid="angie-welcome-hero">
					<div class="angie-welcome-left" data-testid="angie-welcome-left">
						<div class="angie-title-container">
							<img src="<?php echo esc_url( Utils::get_asset_url( 'angieIcon.svg', $this->consent_manager_module_file ) ); ?>"
								alt="" class="angie-title-icon" />
							<h4>
								<span class="angie-title-gradient" aria-hidden="true"><?php esc_html_e( 'Angie', 'angie' ); ?></span><?php esc_html_e( ': Agentic AI For WordPress.', 'angie' ); ?>
							</h4>
						</div>
						<p class="angie-subtitle">
							<?php esc_html_e( 'Angie turns your ideas, screenshots, or URLs into functional pages and components.', 'angie' ); ?>
						</p>
						<div class="angie-consent-section" data-testid="angie-consent-section">
							<label class="angie-consent-checkbox" data-testid="angie-consent-checkbox">
								<input type="checkbox" id="angie-terms-consent" />
								<span class="checkmark"></span>
								<span class="consent-text">
									<?php esc_html_e( 'I agree to the ', 'angie' ); ?>
									<a href="https://go.elementor.com/angie-terms" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Terms', 'angie' ); ?></a>
									<?php esc_html_e( ' & ', 'angie' ); ?>
									<a href="https://go.elementor.com/ai-privacy-policy/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy Policy', 'angie' ); ?></a>.
								</span>
							</label>
						</div>
						<button class="angie-signin-button" id="angie-signin-btn" disabled data-testid="angie-signin-btn">
							<?php esc_html_e( 'Sign in', 'angie' ); ?>
						</button>
					</div>
					<div class="angie-welcome-right" data-testid="angie-welcome-right">
					
							<img src="<?php echo esc_url( Utils::get_asset_url( 'angieHeroImage.png', $this->consent_manager_module_file ) ); ?>"
								alt="<?php esc_attr_e( 'Ask Angie AI Assistant', 'angie' ); ?>"
								class="angie-ask-image" data-testid="angie-ask-image" />
					
					</div>
				</div>
				<?php
				$feature_cards = $this->get_feature_cards();
				$bullet_star_url = Utils::get_asset_url( 'bulletStar.svg', $this->consent_manager_module_file );
				?>
				<div class="angie-feature-cards" data-testid="angie-feature-cards">
					<?php foreach ( $feature_cards as $card ) : ?>
						<div class="angie-feature-card">
							<div class="angie-feature-card-header">
								<img src="<?php echo esc_url( $bullet_star_url ); ?>" alt="" class="angie-feature-card-bullet" />
								<h5 class="angie-feature-card-title"><?php echo esc_html( $card['title'] ); ?></h5>
							</div>
							<div class="angie-feature-card-body">
								<p class="angie-feature-card-description"><?php echo esc_html( $card['description'] ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		
		<?php $this->render_welcome_styles_and_scripts(); ?>
		<?php
	}



	private function render_settings_page() {
		$consent = get_option( ConsentManager::CONSENT_OPTION_NAME, 'no' );
		$settings_class = new \Angie\Modules\AngieSettings\Components\Settings();
		$website_uuid = $settings_class->get_website_uuid();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Angie Settings', 'angie' ); ?></h1>
			
			<div class="card" style="max-width: 800px;">
				<h2><?php esc_html_e( 'External Script Loading', 'angie' ); ?></h2>
				<p><strong><?php esc_html_e( 'Status:', 'angie' ); ?></strong> 
					<?php if ( 'yes' === $consent ) : ?>
						<span style="color: green;"><?php esc_html_e( 'Approved', 'angie' ); ?></span>
					<?php else : ?>
						<span style="color: red;"><?php esc_html_e( 'Not Approved', 'angie' ); ?></span>
					<?php endif; ?>
				</p>
				<p><?php esc_html_e( 'You have approved the loading of external scripts required for Angie functionality.', 'angie' ); ?></p>
				<hr style="border: none; height: 1px; background-color: lightgray;">
				<div style="margin-top: 20px;">
					<p><?php esc_html_e( 'Want to revoke your approval for any reason?', 'angie' ); ?></p>
					<p><?php esc_html_e( 'Please note that this action will revoke the permission for all users to use Angie on this website.', 'angie' ); ?></p>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'reset' ), 'angie_reset_consent' ) ); ?>" class="button button-secondary" style="color: #2271b1; background: white; border-color: #2271b1;" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to deactivate Angie?', 'angie' ); ?>');">
						<?php esc_html_e( 'Deactivate Angie on this website', 'angie' ); ?>
					</a>
				</div>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2><?php esc_html_e( 'Website Information', 'angie' ); ?></h2>
				<table class="form-table" style="table-layout: auto;">
					<tr>
						<th scope="row" style="padding-left: 0; width: auto;">
							<?php esc_html_e( 'Website Unique ID:', 'angie' ); ?>
						</th>
						<td style="padding-left: 20px;">
							<code style="background: #f7f7f7; padding: 4px 8px; border-radius: 3px; font-family: monospace; display: inline-block;"><?php echo esc_html( $website_uuid ); ?></code>
							<p class="description" style="margin-top: 5px;">
								<?php esc_html_e( 'This is your website\'s unique identifier used by Angie services.', 'angie' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<?php $this->render_additional_settings_card(); ?>

			<?php do_action( 'angie_settings_page_cards' ); ?>
		</div>
		<?php
	}

	/**
	 * Renders the "Additional Settings" card, currently hosting the Super
	 * Admin toggle. The dropdown is the source of truth for the
	 * `angie_super_admin_enabled` WP option that super-admin REST endpoints
	 * consult. Default is Disabled; the Angie consent flow also flips this
	 * option via REST in a follow-up.
	 */
	private function render_additional_settings_card() {
		if ( ! SuperAdmin::current_user_can_use() ) {
			return;
		}

		$super_admin_enabled = SuperAdmin::is_enabled();
		$has_explicit        = SuperAdmin::has_explicit_setting();

		if ( ! $has_explicit ) {
			$current_state = 'default';
		} elseif ( $super_admin_enabled ) {
			$current_state = 'active';
		} else {
			$current_state = 'disabled';
		}
		?>
		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php esc_html_e( 'Additional Settings', 'angie' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="angie_update_super_admin" />
				<?php wp_nonce_field( 'angie_update_super_admin' ); ?>

				<p><strong><?php esc_html_e( 'Super Admin mode', 'angie' ); ?></strong></p>
				<p>
					<?php esc_html_e( 'Enable this to allow Angie to execute server-side PHP. This grants the power to perform one-time operations like bulk page creation and direct root-level edits. Handle with care.', 'angie' ); ?>
				</p>

				<p>
					<label for="angie_super_admin_state" class="screen-reader-text">
						<?php esc_html_e( 'Super Admin mode state', 'angie' ); ?>
					</label>
					<select id="angie_super_admin_state" name="angie_super_admin_state" style="min-width: 200px;">
						<option value="default" <?php selected( 'default', $current_state ); ?>>
							<?php esc_html_e( 'Default (Disabled)', 'angie' ); ?>
						</option>
						<option value="disabled" <?php selected( 'disabled', $current_state ); ?>>
							<?php esc_html_e( 'Disabled', 'angie' ); ?>
						</option>
						<option value="active" <?php selected( 'active', $current_state ); ?>>
							<?php esc_html_e( 'Active', 'angie' ); ?>
						</option>
					</select>
					<button type="submit" class="button button-primary" style="margin-left: 10px;">
						<?php esc_html_e( 'Save', 'angie' ); ?>
					</button>
				</p>

				<p style="color: #b26500; margin-top: 10px;">
					<?php esc_html_e( 'Warning: Executing PHP directly from the root can bypass safety filters. Ensure your backups are current.', 'angie' ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	private function render_welcome_styles_and_scripts() {
		// Enqueue styles
		wp_enqueue_style(
			'angie-consent-page',
			Utils::get_asset_url( 'consent-page-styles.css', $this->consent_manager_module_file ),
			[],
			ANGIE_VERSION
		);
		
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				const checkbox = document.getElementById('angie-terms-consent');
				const button = document.getElementById('angie-signin-btn');
				const hasConsent = <?php echo json_encode( get_option( ConsentManager::CONSENT_OPTION_NAME, 'no' ) === 'yes' ); ?>;
				let isProcessing = false;
				
				checkbox.addEventListener('change', function() {
					button.disabled = !this.checked;
				});
				
				button.addEventListener('click', function() {
					if (button.disabled || isProcessing) return;
					
					isProcessing = true;
					
					button.classList.add('loading');
					button.textContent = 'Processing...';
					button.disabled = true;
					
					wp.apiFetch({
						path: '/angie/v1/consent',
						method: 'POST',
						data: { return_to: document.referrer || '' },
					})
					.then(function(response) {
						console.log('Consent granted successfully');
						button.textContent = 'Redirecting...';
						window.location.href = response.redirect;
					})
					.catch(function(error) {
						console.error('Request failed:', error);
						resetButton();
					});
				});
				
				function resetButton() {
					isProcessing = false;
					button.classList.remove('loading');
					button.textContent = 'Sign in to continue';
					button.disabled = !checkbox.checked;
				}
			});
		</script>
		<?php
	}

	private function get_feature_cards() {
		return [
			[
				'title'       => esc_html__( 'Describe what you want', 'angie' ),
				'description' => esc_html__( 'Use plain language, a screenshot, or a URL to describe your vision.', 'angie' ),
			],
			[
				'title'       => esc_html__( 'Angie builds it', 'angie' ),
				'description' => esc_html__( 'Get ready-to-use pages, widgets, and WordPress snippets.', 'angie' ),
			],
			[
				'title'       => esc_html__( 'Review before it goes live', 'angie' ),
				'description' => esc_html__( 'Built in test mode first, so your website stays safe and you stay in control.', 'angie' ),
			],
		];
	}
}
