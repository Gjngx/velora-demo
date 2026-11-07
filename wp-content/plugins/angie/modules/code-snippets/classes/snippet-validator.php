<?php
namespace Angie\Modules\CodeSnippets\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Snippet_Validator {

	public static function validate_snippet_files( $files ) {
		foreach ( $files as $file ) {
			$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			if ( 'php' !== $extension ) {
				continue;
			}

			$content_clean = base64_decode( $file['content_b64'] );

			$check_result = File_Validator::check_forbidden_functions( $content_clean );
			if ( ! $check_result['allowed'] ) {
				return new \WP_Error(
					'forbidden_function',
					sprintf(
						/* translators: %s: forbidden function name */
						esc_html__( 'Forbidden function detected: %s', 'angie' ),
						esc_html( $check_result['function'] )
					)
				);
			}
		}

		$seen_names = [];
		foreach ( $files as $file ) {
			$name = $file['name'];
			if ( isset( $seen_names[ $name ] ) ) {
				return new \WP_Error(
					'duplicate_filename',
					esc_html__( 'Duplicate filenames are not allowed.', 'angie' )
				);
			}
			$seen_names[ $name ] = true;
		}

		$validation_files = [];
		foreach ( $files as $file ) {
			$validation_files[] = [
				'name'    => $file['name'],
				'content' => base64_decode( $file['content_b64'] ),
			];
		}

		$validate_url = self::get_loopback_url( 'angie/v1/snippets/validate' );
		$headers = [
			'Content-Type' => 'application/json',
			'X-WP-Nonce'   => wp_create_nonce( 'wp_rest' ),
		];

		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode(
				sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) ) . ':' .
				sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_PW'] ) )
			);
		} elseif ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$headers['Authorization'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		$response = wp_remote_post(
			$validate_url,
			[
				'headers' => $headers,
				'body'    => wp_json_encode( [ 'files' => $validation_files ] ),
				'timeout' => 30,
				'cookies' => $_COOKIE,
			]
		);

		if ( is_wp_error( $response ) ) {
			$is_allow_skip_validation = 'http_request_failed' === $response->get_error_code();
			if ( $is_allow_skip_validation ) {
				return true;
			}

			return new \WP_Error(
				'validation_request_failed',
				sprintf(
					/* translators: %s: error message */
					esc_html__( 'Snippet validation request failed: %s', 'angie' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$validation_result = json_decode( $response_body, true );

		if ( \WP_Http::OK !== $response_code || ! isset( $validation_result['valid'] ) || ! $validation_result['valid'] ) {
			$error_message = $validation_result['data']['error']['message']
				?? $validation_result['data']['details']['message']
				?? $validation_result['message']
				?? sprintf(
					/* translators: 1: HTTP status code, 2: truncated response body */
					esc_html__( 'Loopback validation returned HTTP %1$s: %2$s', 'angie' ),
					$response_code,
					mb_substr( wp_strip_all_tags( $response_body ), 0, 200, 'UTF-8' )
				);

			return new \WP_Error(
				'validation_failed',
				sprintf(
					/* translators: %s: error message */
					esc_html__( 'Snippet validation failed: %s', 'angie' ),
					esc_html( $error_message )
				)
			);
		}

		return true;
	}

	public static function validate_snippet_execution( $files ) {
		$temp_dir = self::create_temp_snippet_dir();

		if ( ! $temp_dir ) {
			return [
				'valid'   => false,
				'error'   => esc_html__( 'Failed to create temporary directory.', 'angie' ),
				'details' => [],
			];
		}

		foreach ( $files as $file ) {
			$file_path = $temp_dir . '/' . $file['name'];
			$content   = base64_decode( $file['content_b64'] );

			file_put_contents( $file_path, $content );
		}

		$main_file = $temp_dir . '/main.php';

		if ( ! file_exists( $main_file ) ) {
			self::cleanup_temp_dir( $temp_dir );
			return [
				'valid'   => false,
				'error'   => esc_html__( 'main.php file is required.', 'angie' ),
				'details' => [],
			];
		}

		$validation_result = self::execute_snippet_safely( $main_file );

		self::cleanup_temp_dir( $temp_dir );

		return $validation_result;
	}

	private static function execute_snippet_safely( $file_path ) {
		$original_time_limit = ini_get( 'max_execution_time' );
		$original_memory_limit = ini_get( 'memory_limit' );

		set_time_limit( 5 );

		$sandbox_memory_limit = '64M';
		$sandbox_memory_bytes = wp_convert_hr_to_bytes( $sandbox_memory_limit );
		$memory_limit_lowered   = false;
		$current_limit_bytes    = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );

		if ( wp_is_ini_value_changeable( 'memory_limit' )
			&& ( -1 === $current_limit_bytes || $current_limit_bytes > $sandbox_memory_bytes )
		) {
			$memory_limit_lowered = false !== ini_set( 'memory_limit', $sandbox_memory_limit );
		}

		ob_start();

		set_error_handler( function( $errno, $errstr, $errfile, $errline ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception parameters are not output, they are internal error data
			throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
		} );

		try {

			include $file_path;

			$output = ob_get_clean();
			restore_error_handler();

			set_time_limit( $original_time_limit );
			if ( $memory_limit_lowered ) {
				ini_set( 'memory_limit', $original_memory_limit );
			}

			return [
				'valid'   => true,
				'error'   => '',
				'details' => [
					'output' => $output,
				],
			];
		} catch ( \Throwable $e ) {
			ob_end_clean();
			restore_error_handler();

			set_time_limit( $original_time_limit );
			if ( $memory_limit_lowered ) {
				ini_set( 'memory_limit', $original_memory_limit );
			}

			return [
				'valid'   => false,
				'error'   => sprintf(
					/* translators: %s: error message */
					esc_html__( 'Snippet execution error: %s', 'angie' ),
					$e->getMessage()
				),
				'details' => [
					'message' => $e->getMessage(),
					'file'    => $e->getFile(),
					'line'    => $e->getLine(),
					'trace'   => $e->getTraceAsString(),
				],
			];
		}
	}

	// This loopback url is used to validate the snippet in the local environment (wp-env)
	private static function get_loopback_url( $rest_route ) {
		$url = rest_url( $rest_route );

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( 'localhost' !== $host && '127.0.0.1' !== $host ) {
			return $url;
		}

		return str_replace( $host, 'host.docker.internal', $url );
	}

	private static function create_temp_snippet_dir() {
		$temp_base = WP_CONTENT_DIR . '/angie-snippets/temp';

		if ( ! is_dir( $temp_base ) ) {
			wp_mkdir_p( $temp_base );
		}

		$temp_dir = $temp_base . '/validation-' . uniqid();

		if ( wp_mkdir_p( $temp_dir ) ) {
			return $temp_dir;
		}

		return false;
	}

	private static function cleanup_temp_dir( $temp_dir ) {
		if ( ! is_dir( $temp_dir ) ) {
			return;
		}

		$files = glob( $temp_dir . '/*' );

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$wp_filesystem->rmdir( $temp_dir );
	}
}
