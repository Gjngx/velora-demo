<?php
namespace Angie\Modules\SuperAdmin\Classes;

use Angie\Modules\SuperAdmin\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Api_Controller {

	const NAMESPACE = 'angie/v1';
	const MAX_LIST_ENTRIES = 1000;
	const MAX_READ_BYTES = 524288; // 512 KB.

	const ERROR_EMPTY_CODE    = 'empty_code';
	const ERROR_INVALID_PATH  = 'invalid_path';
	const ERROR_NOT_A_FILE    = 'not_a_file';
	const ERROR_NOT_A_DIR     = 'not_a_directory';
	const ERROR_FILE_TOO_LARGE = 'file_too_large';
	const ERROR_OPEN_FAILED   = 'open_failed';
	const ERROR_READ_FAILED   = 'read_failed';
	const ERROR_MISSING_EXEC_TOKEN = 'missing_exec_token';
	const ERROR_INVALID_EXEC_TOKEN = 'invalid_exec_token';

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/super-admin/execute',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'execute_php' ],
					'permission_callback' => [ $this, 'check_token_protected_permission' ],
					'args'                => [
						'code' => [
							'required' => true,
							'type'     => 'string',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/super-admin/list',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_directory' ],
					'permission_callback' => [ $this, 'check_token_protected_permission' ],
					'args'                => [
						'path' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/super-admin/read',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'read_file' ],
					'permission_callback' => [ $this, 'check_token_protected_permission' ],
					'args'                => [
						'path' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/super-admin/status',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_status' ],
					'permission_callback' => [ $this, 'check_status_permission' ],
				],
			]
		);

	}

	public function check_status_permission(): bool {
		return Module::current_user_can_use();
	}

	public function get_status() {
		return rest_ensure_response( [
			'enabled' => Module::is_enabled(),
		] );
	}

	/**
	 * @return bool|\WP_Error
	 */
	public function check_token_protected_permission() {
		if ( ! Module::is_enabled() ) {
			return false;
		}

		if ( ! Module::current_user_can_use() ) {
			return false;
		}

		$token = $this->get_exec_token_from_request();
		if ( empty( $token ) ) {
			return new \WP_Error( self::ERROR_MISSING_EXEC_TOKEN, esc_html__( 'Super-admin execution token is required.', 'angie' ), [ 'status' => \WP_Http::FORBIDDEN ] );
		}

		if ( ! Execution_Token::validate( $token ) ) {
			return new \WP_Error( self::ERROR_INVALID_EXEC_TOKEN, esc_html__( 'Execution token is invalid or expired.', 'angie' ), [ 'status' => \WP_Http::FORBIDDEN ] );
		}

		return true;
	}

	private function get_exec_token_from_request(): string {
		$token = isset( $_SERVER['HTTP_X_ANGIE_EXEC_TOKEN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ANGIE_EXEC_TOKEN'] ) ) : '';
		return $token;
	}

	public function execute_php( \WP_REST_Request $request ) {
		$code = (string) $request->get_param( 'code' );
		if ( empty( trim( $code ) ) ) {
			return new \WP_Error( self::ERROR_EMPTY_CODE, esc_html__( 'No PHP code provided.', 'angie' ), [ 'status' => \WP_Http::BAD_REQUEST ] );
		}

		$result = Php_Executor::execute( $code );
		return rest_ensure_response( $result );
	}

	public function list_directory( \WP_REST_Request $request ) {
		$raw_path = (string) $request->get_param( 'path' );
		$resolved = Path_Validator::resolve_under_abspath( $raw_path );
		if ( empty( $resolved ) ) {
			return new \WP_Error( self::ERROR_INVALID_PATH, esc_html__( 'Path must resolve under ABSPATH.', 'angie' ), [ 'status' => \WP_Http::BAD_REQUEST ] );
		}

		if ( ! is_dir( $resolved ) ) {
			return new \WP_Error( self::ERROR_NOT_A_DIR, esc_html__( 'Path is not a directory.', 'angie' ), [ 'status' => \WP_Http::NOT_FOUND ] );
		}

		$entries = [];
		$count = 0;
		$truncated = false;

		$dh = @opendir( $resolved ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $dh ) {
			return new \WP_Error( self::ERROR_OPEN_FAILED, esc_html__( 'Failed to open directory.', 'angie' ), [ 'status' => \WP_Http::INTERNAL_SERVER_ERROR ] );
		}

		while ( false !== ( $name = readdir( $dh ) ) ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			if ( $count >= self::MAX_LIST_ENTRIES ) {
				$truncated = true;
				break;
			}
			$full = trailingslashit( $resolved ) . $name;
			$is_dir = is_dir( $full );
			$entries[] = [
				'name' => $name,
				'type' => $is_dir ? 'dir' : 'file',
				'size' => $is_dir ? null : (int) @filesize( $full ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			];
			$count++;
		}
		closedir( $dh );

		return rest_ensure_response( [
			'path'      => $resolved,
			'entries'   => $entries,
			'truncated' => $truncated,
		] );
	}

	public function read_file( \WP_REST_Request $request ) {
		$raw_path = (string) $request->get_param( 'path' );
		$resolved = Path_Validator::resolve_under_abspath( $raw_path );
		if ( empty( $resolved ) ) {
			return new \WP_Error( self::ERROR_INVALID_PATH, esc_html__( 'Path must resolve under ABSPATH.', 'angie' ), [ 'status' => \WP_Http::BAD_REQUEST ] );
		}

		if ( ! is_file( $resolved ) ) {
			return new \WP_Error( self::ERROR_NOT_A_FILE, esc_html__( 'Path is not a file.', 'angie' ), [ 'status' => \WP_Http::NOT_FOUND ] );
		}

		$size = (int) @filesize( $resolved ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( $size > self::MAX_READ_BYTES ) {
			return new \WP_Error(
				self::ERROR_FILE_TOO_LARGE,
				sprintf(
					/* translators: %d: max bytes */
					esc_html__( 'File exceeds the %d byte read limit.', 'angie' ),
					self::MAX_READ_BYTES
				),
				[ 'status' => \WP_Http::REQUEST_ENTITY_TOO_LARGE ]
			);
		}

		$content = file_get_contents( $resolved ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content ) {
			return new \WP_Error( self::ERROR_READ_FAILED, esc_html__( 'Failed to read file.', 'angie' ), [ 'status' => \WP_Http::INTERNAL_SERVER_ERROR ] );
		}

		return rest_ensure_response( [
			'path'    => $resolved,
			'size'    => $size,
			'content' => $content,
		] );
	}

}
