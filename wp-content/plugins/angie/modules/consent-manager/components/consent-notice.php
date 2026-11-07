<?php

namespace Angie\Modules\ConsentManager\Components;

use Angie\Modules\ConsentManager\Module as ConsentManager;
use Angie\Includes\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Consent Notice Component
 *
 * Handles displaying consent notices and processing responses
 */
class Consent_Notice {


	public function __construct() {
		add_action( 'admin_init', [ $this, 'handle_consent_response' ] );
	}


	public function display_consent_notice() {
		// Don't show if user has already responded.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['angie_consent_set'] ) || ConsentManager::has_consent() ) {
			return;
		}

		// Show the notice.
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Angie', 'angie' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'Do you allow loading external scripts?', 'angie' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'angie_consent', 'yes' ), 'angie_consent_action' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Yes, I approve', 'angie' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'angie_consent', 'no' ), 'angie_consent_action' ) ); ?>" class="button">
					<?php esc_html_e( 'No, I deny', 'angie' ); ?>
				</a>
			</p>
		</div>
		<?php
	}


	public function handle_consent_response() {
		// Check if consent response is provided and nonce is valid.
		if ( ! isset( $_GET['angie_consent'], $_GET['_wpnonce'] ) ) {
			return;
		}

		// Check if user has permission to manage plugin settings.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'angie' ) );
		}

		// Use Utils::get_sanitized_query_var for sanitization.
		$nonce = Utils::get_sanitized_query_var( '_wpnonce' );
		if ( ! wp_verify_nonce( $nonce, 'angie_consent_action' ) ) {
			return;
		}

		// Validate and sanitize response.
		$response = Utils::get_sanitized_query_var( 'angie_consent' );

		if ( ! in_array( $response, [ 'yes', 'no' ], true ) ) {
			return;
		}

		// Save consent to options.
		update_option( ConsentManager::CONSENT_OPTION_NAME, $response );

		// Redirect to remove query args.
		wp_safe_redirect( add_query_arg( 'angie_consent_set', 'true', remove_query_arg( [ 'angie_consent', '_wpnonce' ] ) ) );
		exit;
	}
}
