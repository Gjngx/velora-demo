<?php
namespace Angie\Modules\CodeSnippets\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fatal_Error_Handler {

	private const ERROR_CODE_ADDITIONAL_MESSAGE = 'angie_additional_message';

	public static function init() {
		if ( ! self::should_handle_errors() ) {
			return;
		}

		add_filter( 'wp_die_handler', function() {
			return [ __CLASS__, 'handle_wp_die' ];
		}, 9999 );
	}

	private static function get_additional_error_message( $message ): string {

		$error_message = self::format_error_message( $message );
		$exit_url = self::get_exit_test_mode_url();
		$snippet_id = self::extract_snippet_id( $error_message );
		$prompt = $snippet_id
			? 'Please fix the error in snippet ID ' . $snippet_id . ' - Use the snippet slug: ' . $error_message
			: 'Please fix this error: ' . $error_message;
		$fix_with_angie_url = $exit_url . '#angie-prompt=' . rawurlencode( $prompt ) . '&angie-new-chat=true';

		ob_start();
		?>
		<style>
			body#error-page {
				border: none !important;
				max-width: none !important;
				margin: 0 !important;
				padding: 0 !important;
				font-size: 0 !important;
				height: 100vh !important;
				display: flex !important;
				justify-content: center !important;
				align-items: center !important;
			}
			body#error-page .wp-die-message ul {
				margin: 0 !important;
				padding: 0 !important;
			}
			body#error-page .wp-die-message ul > li {
				display: none !important;
			}
			body#error-page .wp-die-message ul > li:first-child {
				display: block !important;
			}

			.angie-fatal-notice {
				width: 550px;
				max-width: 90vw;
				padding: 24px;
				border-radius: 16px;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				color: #1d2327;
				border: 1px solid #e0e0e0;
			}
			.angie-fatal-notice h2 {
				font-size: 20px;
				margin: 0 0 12px 0;
			}
			#error-page .angie-fatal-notice p {
				font-size: 16px;
				color: #50575e;
				margin: 0 0 4px 0;
			}
			.angie-fatal-notice .angie-help-link {
				display: inline-block;
				font-size: 16px;
				color: #2271b1;
				margin-top: 12px;
				margin-bottom: 24px;
			}
			.angie-fatal-notice .angie-help-link:hover {
				color: #135e96;
			}
			.angie-fatal-buttons {
				display: flex;
				justify-content: flex-end;
				align-items: center;
				gap: 16px;
			}
			.angie-btn {
				display: inline-block;
				padding: 8px 16px;
				font-size: 14px;
				font-weight: 500;
				text-decoration: none;
				border-radius: 10px;
				cursor: pointer;
				text-align: center;
				background: transparent;
				color: #1d2327;
				border: none;
				transition: all 0.2s ease;
			}
			.angie-btn:hover {
				background: #f0f0f1;
			}
			.angie-fix-with-angie-btn {
				background: #1d2327;
				color: #fff;
				border: 1px solid #1d2327;
			}
			.angie-fix-with-angie-btn:hover {
				background: #3a3f44;
				color: #fff;
				border-color: #3a3f44;
			}
		</style>
		<div class="angie-fatal-notice">
			<h2><?php echo esc_html__( 'Something went wrong while working on your site', 'angie' ); ?></h2>
			<p><?php echo esc_html__( 'A change made by Angie caused an error in this preview.', 'angie' ); ?></p>
			<p><?php echo esc_html__( "Your live site is not affected - you're viewing changes in Test Mode.", 'angie' ); ?></p>
			<a href="https://wordpress.org/documentation/article/faq-troubleshooting/" target="_blank" rel="noopener noreferrer" class="angie-help-link">
				<?php echo esc_html__( 'Learn how to troubleshoot WordPress errors', 'angie' ); ?>
			</a>
			<div class="angie-fatal-buttons">
				<a href="<?php echo esc_url( $exit_url ); ?>" class="angie-btn">
					<?php echo esc_html__( 'Exit Test Mode', 'angie' ); ?>
				</a>
				<a href="<?php echo esc_url( $fix_with_angie_url ); ?>" class="angie-btn angie-fix-with-angie-btn">
					<?php echo esc_html__( 'Fix with Angie', 'angie' ); ?>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function handle_wp_die( $message, $title = '', $args = [] ): void {
		$additional_message = static::get_additional_error_message( $message );

		$message = is_wp_error( $message )
			? self::merge_wp_errors( $message, $additional_message )
			: $additional_message . $message;

		_default_wp_die_handler( $message, $title, $args );
	}

	private static function merge_wp_errors( \WP_Error $original, string $additional_message ): \WP_Error {
		$new_error = new \WP_Error( self::ERROR_CODE_ADDITIONAL_MESSAGE, $additional_message );

		foreach ( $original->get_error_codes() as $code ) {
			foreach ( $original->get_error_messages( $code ) as $error_message ) {
				$new_error->add( $code, $error_message );
			}

			$error_data = $original->get_error_data( $code );

			if ( $error_data ) {
				$new_error->add_data( $error_data, $code );
			}
		}

		return $new_error;
	}

	private static function should_handle_errors(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		return Dev_Mode_Manager::is_dev_mode_enabled();
	}

	private static function get_exit_test_mode_url(): string {
		$current_url = home_url( add_query_arg( null, null ) );
		return add_query_arg( 'angie-exit-test-mode', '1', $current_url );
	}

	private static function extract_snippet_id( string $error_message ): ?string {
		if ( preg_match( '/snippet-(\d+)/', $error_message, $matches ) ) {
			return $matches[1];
		}
		return null;
	}

	private static function format_error_message( $message ): string {

		if ( is_wp_error( $message ) ) {
			if( isset( $message->error_data['internal_server_error']['error']['message'] ) ) {
				return $message->error_data['internal_server_error']['error']['message'];
			}
			return $message->get_error_message();
		}

		if ( is_string( $message ) ) {
			return $message;
		}

		return wp_json_encode( $message ) ?? '';
	}
}
