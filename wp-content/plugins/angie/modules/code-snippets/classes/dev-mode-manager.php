<?php
namespace Angie\Modules\CodeSnippets\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dev_Mode_Manager {

	const ENV_PROD = 'prod';
	const ENV_DEV = 'dev';
	const DEV_MODE_COOKIE_NAME = 'angie_snippet_dev_mode';

	const SYNC_STATUS_NOT_DEPLOYED = 'not-deployed';
	const SYNC_STATUS_CHANGES_PENDING = 'changes-pending';
	const SYNC_STATUS_DEPLOYED = 'deployed';
	const SYNC_STATUS_TEST_ONLY = 'test-only';

	private static function get_dev_mode_secret_token() {
		$option_key = '_angie_snippets_dev_mode_secret_token';
		$token = get_option( $option_key );

		if ( ! $token ) {
			$token = wp_generate_password( 64, false );
			update_option( $option_key, $token, false );
		}

		return $token;
	}

	private static function generate_session_token( $user_id, $ip_address ) {
		$secret = self::get_dev_mode_secret_token();
		return hash_hmac( 'sha256', $user_id . '|' . $ip_address, $secret );
	}

	private static function get_client_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return $ip;
	}

	private static function set_dev_mode_cookie( $user_id, $ip_address ) {
		$token = self::generate_session_token( $user_id, $ip_address );
		$expiry = time() + YEAR_IN_SECONDS;
		$ip_hash = hash( 'sha256', $ip_address );
		$cookie_value = $token . '|' . $expiry . '|' . $user_id . '|' . $ip_hash;

		setcookie(
			self::DEV_MODE_COOKIE_NAME,
			$cookie_value,
			$expiry,
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);

		return $expiry;
	}

	private static function extend_dev_mode_session( $user_id, $ip_address ) {
		if ( headers_sent() ) {
			return;
		}

		self::set_dev_mode_cookie( $user_id, $ip_address );
	}

	public static function create_dev_mode_session() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$ip_address = self::get_client_ip();
		if ( empty( $ip_address ) ) {
			return false;
		}

		$expiry = self::set_dev_mode_cookie( $user_id, $ip_address );

		return [
			'expiry'  => $expiry,
			'user_id' => $user_id,
		];
	}

	public static function clear_dev_mode_session() {
		setcookie(
			self::DEV_MODE_COOKIE_NAME,
			'',
			time() - 3600,
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);
	}

	public static function is_dev_mode_enabled() {
		if ( ! isset( $_COOKIE[ self::DEV_MODE_COOKIE_NAME ] ) ) {
			return false;
		}

		$cookie_value = sanitize_text_field( wp_unslash( $_COOKIE[ self::DEV_MODE_COOKIE_NAME ] ) );
		$parts = explode( '|', $cookie_value );

		if ( count( $parts ) !== 4 ) {
			return false;
		}

		list( $token, $expiry, $user_id, $ip_hash ) = $parts;

		if ( time() > (int) $expiry ) {
			return false;
		}

		$current_user_id = get_current_user_id();
		if ( (int) $user_id !== $current_user_id ) {
			return false;
		}

		$current_ip = self::get_client_ip();
		$expected_ip_hash = hash( 'sha256', $current_ip );
		if ( $ip_hash !== $expected_ip_hash ) {
			return false;
		}

		$expected_token = self::generate_session_token( $user_id, $current_ip );
		if ( ! hash_equals( $expected_token, $token ) ) {
			return false;
		}

		self::extend_dev_mode_session( $user_id, $current_ip );

		return true;
	}

	public static function get_snippet_environment_timestamps( $post_id ) {
		$snippet_name = 'snippet-' . $post_id;
		$dev_dir = WP_CONTENT_DIR . '/angie-snippets/' . self::ENV_DEV . '/' . $snippet_name;
		$prod_dir = WP_CONTENT_DIR . '/angie-snippets/' . self::ENV_PROD . '/' . $snippet_name;

		$dev_time = 0;
		if ( is_dir( $dev_dir ) ) {
			$dev_main = $dev_dir . '/main.php';
			if ( file_exists( $dev_main ) ) {
				$dev_time = filemtime( $dev_main );
			}
		}

		$prod_time = 0;
		if ( is_dir( $prod_dir ) ) {
			$prod_main = $prod_dir . '/main.php';
			if ( file_exists( $prod_main ) ) {
				$prod_time = filemtime( $prod_main );
			}
		}

		if ( $prod_time === 0 && $dev_time === 0 ) {
		   $sync_status = self::SYNC_STATUS_NOT_DEPLOYED;
		} elseif ( $prod_time > 0 && $dev_time > $prod_time ) {
		   $sync_status = self::SYNC_STATUS_CHANGES_PENDING;
		} elseif ( $prod_time === 0 && $dev_time > 0 ) {
		   $sync_status = self::SYNC_STATUS_TEST_ONLY;
		} else {
		   $sync_status = self::SYNC_STATUS_DEPLOYED;
		}

		return [
			'dev' => $dev_time,
			'prod' => $prod_time,
			'status' => $sync_status,
		];
	}

	public static function is_deploy_button_disabled( $dev_time, $prod_time ) {
		return $dev_time > 0 && $prod_time >= $dev_time;
	}

	public static function push_snippet_to_dev( $post_id ) {
		$files = get_post_meta( $post_id, '_angie_snippet_files', true );
		if ( ! is_array( $files ) || empty( $files ) ) {
			return false;
		}

		File_System_Handler::write_snippet_files_to_disk( self::ENV_DEV, $post_id, $files );

		return true;
	}

	public static function push_snippet_to_production( $post_id ) {
		$files = get_post_meta( $post_id, '_angie_snippet_files', true );
		if ( ! is_array( $files ) || empty( $files ) ) {
			return false;
		}

		File_System_Handler::write_snippet_files_to_disk( self::ENV_PROD, $post_id, $files );
		File_System_Handler::write_snippet_files_to_disk( self::ENV_DEV, $post_id, $files );

		return true;
	}

	public static function load_snippets() {
		$environment = self::is_dev_mode_enabled() ? self::ENV_DEV : self::ENV_PROD;
		$snippets_dir = WP_CONTENT_DIR . '/angie-snippets/' . $environment;

		if ( ! is_dir( $snippets_dir ) ) {
			return;
		}

		$snippet_dirs = glob( $snippets_dir . '/*', GLOB_ONLYDIR );

		if ( ! $snippet_dirs ) {
			return;
		}

		$published_ids = Cache_Manager::get_published_snippet_ids();

		foreach ( $snippet_dirs as $snippet_dir ) {
			$snippet_name = basename( $snippet_dir );

			if ( preg_match( '/^snippet-(\d+)$/', $snippet_name, $matches ) ) {
				$post_id = (int) $matches[1];

				if ( ! in_array( $post_id, $published_ids, true ) ) {
					continue;
				}
			}

			$main_file = $snippet_dir . '/main.php';

			if ( file_exists( $main_file ) && is_readable( $main_file ) ) {
				try {
					require_once $main_file;
				} catch ( \Throwable $e ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[Angie Snippets] Failed to load ' . $snippet_name . ': ' . $e->getMessage() );
				}
			}
		}
	}
}
