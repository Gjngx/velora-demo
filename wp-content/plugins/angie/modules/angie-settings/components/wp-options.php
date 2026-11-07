<?php

namespace Angie\Modules\AngieSettings\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Options
 *
 * Handles WordPress options storage and retrieval via REST API
 * This component allows updating only whitelisted WordPress options for security
 * Only site settings, commenting, media, and permalink options are allowed
 */
class WP_Options {

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	protected $namespace = 'angie/v1';

	/**
	 * REST API base
	 *
	 * @var string
	 */
	protected $rest_base = 'wp-options';

	/**
	 * Whitelisted WordPress options that can be read and updated
	 *
	 * @var array
	 */
	protected $whitelisted_options = [
		// General Settings.
		'blogname',
		'blogdescription',
		'siteurl',
		'admin_email',
		'timezone_string',
		'date_format',
		'time_format',
		'start_of_week',
		'WPLANG',

		// Writing Settings.
		'use_smilies',
		'default_category',
		'default_post_format',
		'default_pingback_flag',
		'ping_sites',

		// Reading Settings.
		'posts_per_page',
		'posts_per_rss',
		'rss_use_excerpt',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'blog_public',

		// Discussion Settings.
		'default_ping_status',
		'default_comment_status',
		'comment_registration',
		'require_name_email',
		'comment_previously_approved',
		'close_comments_for_old_posts',
		'close_comments_days_old',
		'thread_comments',
		'thread_comments_depth',
		'page_comments',
		'comments_per_page',
		'default_comments_page',
		'comment_order',
		'comments_notify',
		'moderation_notify',
		'comment_moderation',
		'comment_max_links',

		// Avatar Settings.
		'show_avatars',
		'avatar_rating',
		'avatar_default',

		// Media Settings.
		'thumbnail_size_w',
		'thumbnail_size_h',
		'thumbnail_crop',
		'medium_size_w',
		'medium_size_h',
		'large_size_w',
		'large_size_h',
		'uploads_use_yearmonth_folders',

		// Permalink Settings.
		'permalink_structure',
		'category_base',
		'tag_base',

		// User Settings.
		'users_can_register',
		'default_role',

		// Privacy Settings.
		'wp_page_for_privacy_policy',

		// Site Identity.
		'site_logo',
		'site_icon',
	];

	public function __construct() {
		\add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		\register_rest_route( $this->namespace, '/' . $this->rest_base, [
			'methods' => 'GET',
			'callback' => [ $this, 'get_all_options' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		\register_rest_route( $this->namespace, '/' . $this->rest_base, [
			'methods' => 'POST',
			'callback' => [ $this, 'update_multiple_options' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'options' => [
					'required' => true,
					'type' => 'object',
					'description' => 'Object containing option_name => option_value pairs (only whitelisted options allowed)',
				],
			],
		] );

		\register_rest_route( $this->namespace, '/' . $this->rest_base . '/health', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_site_health' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );
	}

	/**
	 * Check if user has permission to access the endpoint
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Check if an option is in the whitelist
	 *
	 * @param string $option_name
	 * @return bool
	 */
	protected function is_option_whitelisted( $option_name ) {
		return in_array( $option_name, $this->whitelisted_options, true );
	}

	/**
	 * Handle language pack installation for WPLANG option
	 *
	 * @param string $value The language code to install.
	 * @return true|\WP_Error Returns true on success, WP_Error on failure
	 */
	private function handle_language_pack_installation( $value ) {
		$available_languages = \get_available_languages();

		if ( ! in_array( $value, $available_languages, true ) ) {
			if ( ! \current_user_can( 'install_languages' ) ) {
				return new \WP_Error(
					'language_install_permission_denied',
					esc_html__( 'Sorry, you are not allowed to install languages.', 'angie' ),
					[ 'status' => 403 ]
				);
			}

			// Handle translation installation.
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';

			if ( ! \wp_can_install_language_pack() ) {
				return new \WP_Error(
					'language_install_filesystem_locked',
					esc_html__( 'Sorry, the file system is locked.', 'angie' ),
					[ 'status' => 403 ]
				);
			}

			$language = \wp_download_language_pack( $value );

			if ( ! $language ) {
				return new \WP_Error(
					'language_install_download_failed',
					esc_html__( "Sorry, I can't download the language file.", 'angie' ),
					[ 'status' => 404 ]
				);
			}
		}

		return true;
	}

	/**
	 * Get whitelisted WordPress options
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_all_options() {
		$safe_options = [];
		$registered_options = \get_registered_settings();

		$safe_options = array_merge( $this->whitelisted_options, array_keys( $registered_options ) );
		$return_options = [];

		foreach ( $safe_options as $option_name ) {
			$return_options[ $option_name ] = \get_option( $option_name );
		}

		$response = [
			'success' => true,
			'message' => \esc_html__( 'WordPress settings retrieved successfully.', 'angie' ),
			'options' => $return_options,
		];

		return \rest_ensure_response( $response );
	}

	/**
	 * Update multiple WordPress options at once
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_multiple_options( $request ) {
		$json_params = $request->get_json_params();
		if ( $json_params ) {
			$request->set_body_params( $json_params );
		}

		$options = $request->get_param( 'options' );

		if ( empty( $options ) || ! is_array( $options ) ) {
			return new \WP_Error(
				'invalid_options',
				esc_html__( 'Options parameter must be a non-empty object/array.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		$results = [];
		$errors = [];

		foreach ( $options as $option_name => $option_value ) {
			if ( ! $this->is_option_whitelisted( $option_name ) ) {
				$errors[] = sprintf(
					// translators: %s is the option name
					esc_html__( 'Option "%s" is not allowed to be updated.', 'angie' ),
					$option_name
				);
				continue;
			}

			// Special handling for WPLANG option - install language pack if needed.
			if ( 'WPLANG' === $option_name && ! empty( $option_value ) ) {
				$lang_result = $this->handle_language_pack_installation( $option_value );
				if ( is_wp_error( $lang_result ) ) {
					$errors[] = $lang_result->get_error_message();
					continue;
				}
			}

			$updated = \update_option( $option_name, $option_value );

			if ( ! $updated && \get_option( $option_name ) !== $option_value ) {
				$errors[] = sprintf(
					// translators: %s is the option name
					esc_html__( 'Failed to update option "%s".', 'angie' ),
					$option_name
				);
			} else {
				$results[ $option_name ] = $option_value;
			}
		}

		$response = [
			'success' => empty( $errors ),
			'message' => empty( $errors ) ?
				esc_html__( 'All WordPress options updated successfully.', 'angie' ) :
				esc_html__( 'Some options failed to update.', 'angie' ),
			'updated_options' => $results,
		];

		if ( ! empty( $errors ) ) {
			$response['errors'] = $errors;
		}

		return \rest_ensure_response( $response );
	}

	/**
	 * Get Site Health Status information (from Tools -> Site Health)
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_site_health() {
		// Load required admin files.
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}

		$site_health = new \WP_Site_Health();
		$tests = $site_health->get_tests();
		$results = [];

		// Run direct tests.
		foreach ( $tests['direct'] as $test_name => $test ) {
			if ( isset( $test['test'] ) ) {
				$result = null;

				if ( is_string( $test['test'] ) ) {
					$test_method = 'get_test_' . $test['test'];
					if ( method_exists( $site_health, $test_method ) ) {
						$result = $site_health->$test_method();
					}
				} elseif ( is_callable( $test['test'] ) ) {
					$result = call_user_func( $test['test'] );
				}

				if ( $result ) {
					$results[ $test_name ] = $result;
				}
			}
		}

		foreach ( $tests['async'] as $test_name => $test ) {
			if ( isset( $test['async_direct_test'] ) && is_callable( $test['async_direct_test'] ) ) {
				$result = call_user_func( $test['async_direct_test'] );
				if ( $result ) {
					$results[ $test_name ] = $result;
				}
			}
		}

		return \rest_ensure_response( $results );
	}
}
