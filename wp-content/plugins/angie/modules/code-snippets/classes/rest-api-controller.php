<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;
use Angie\Modules\CodeSnippets\PreviewSettings\Elementor_Preview_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Api_Controller {

	const NAMESPACE = 'angie/v1';
	const MAX_FILES_PER_REQUEST = 100;
	const MAX_FILE_SIZE_BYTES = 102400;
	const ERROR_ARTIFACT_SNIPPET_EXISTS = 'artifact_snippet_exists';

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/snippets',
			[
				[
					'methods' => \WP_REST_Server::READABLE,
					'callback' => [ $this, 'list_snippets' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'type' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'deployment_status' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				[
					'methods' => \WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'create_snippet_post' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'title' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'type' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					'artifact_id' => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'version' => [
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<id>\d+)',
			[
				[
					'methods' => \WP_REST_Server::DELETABLE,
					'callback' => [ $this, 'delete_snippet' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'id' => [
							'required' => true,
							'type' => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/artifacts/(?P<artifact_id>[a-f0-9\-]+)/rename',
			[
				[
					'methods' => \WP_REST_Server::EDITABLE,
					'callback' => [ $this, 'rename_snippet_by_artifact' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'artifact_id' => [
							'required' => true,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'title' => [
							'required' => true,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<id>\d+)/artifact',
			[
				[
					'methods' => \WP_REST_Server::EDITABLE,
					'callback' => [ $this, 'link_artifact_to_snippet' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'id' => [
							'required' => true,
							'type' => 'integer',
							'sanitize_callback' => 'absint',
						],
						'artifact_id' => [
							'required' => true,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<id>\d+)/files',
			[
				[
					'methods' => \WP_REST_Server::READABLE,
					'callback' => [ $this, 'list_snippet_files' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'id' => [
							'required' => true,
							'type' => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods' => 'PUT, PATCH',
					'callback' => [ $this, 'update_snippet_files' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'id' => [
							'required' => true,
							'type' => 'integer',
							'sanitize_callback' => 'absint',
						],
						'files' => [
							'required' => true,
							'type' => 'array',
						],
						'type' => [
							'required' => false,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'elementor_preview_settings' => [
							'required' => false,
							'type'     => 'object',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/artifacts/(?P<artifact_id>[a-f0-9\-]+)/files',
			[
				[
					'methods' => 'PUT, PATCH',
					'callback' => [ $this, 'update_snippet_files_by_artifact' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'artifact_id' => [
							'required' => true,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'files' => [
							'required' => true,
							'type' => 'array',
						],
						'elementor_preview_settings' => [
							'required' => false,
							'type'     => 'object',
						],
						'version' => [
							'required' => false,
							'type' => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/artifacts/(?P<artifact_id>[a-f0-9\-]+)',
			[
				[
					'methods' => \WP_REST_Server::DELETABLE,
					'callback' => [ $this, 'delete_snippet_by_artifact' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'artifact_id' => [
							'required' => true,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				[
					'methods' => \WP_REST_Server::EDITABLE,
					'callback' => [ $this, 'publish_snippet_by_artifact' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'artifact_id' => [
							'required' => true,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<id>\d+)/files/(?P<filename>.+)',
			[
				[
					'methods' => \WP_REST_Server::READABLE,
					'callback' => [ $this, 'get_snippet_file' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'id' => [
							'required' => true,
							'type' => 'integer',
							'sanitize_callback' => 'absint',
						],
						'filename' => [
							'required' => true,
							'type' => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<id>\d+)/publish',
			[
				[
					'methods' => \WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'publish_snippet' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'id' => [
							'required' => true,
							'type' => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/dev-mode',
			[
				[
					'methods' => \WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'set_dev_mode' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'enabled' => [
							'required' => true,
							'type' => 'boolean',
							'sanitize_callback' => 'rest_sanitize_boolean',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/validate',
			[
				[
					'methods' => \WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'validate_snippet' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args' => [
						'files' => [
							'required' => true,
							'type' => 'array',
						],
					],
				],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/dev-mode/status',
			[
				[
					'methods' => \WP_REST_Server::READABLE,
					'callback' => [ $this, 'is_dev_mode' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);
	}

	public function check_permission() {
		return Module::current_user_can_manage_snippets();
	}

	public function list_snippets( $request ) {
		$type = $request->get_param( 'type' );
		$deployment_status_param = $request->get_param( 'deployment_status' );
		$deployment_statuses = $deployment_status_param ? array_map( 'trim', explode( ',', $deployment_status_param ) ) : [];

		$posts = Snippet_Repository::get_all_snippets( $type );

		$snippets = [];
		foreach ( $posts as $post ) {
			$snippet_data = Snippet_Repository::get_snippet_data( $post );

			if ( ! empty( $deployment_statuses ) && ! in_array( $snippet_data['deploymentStatus'], $deployment_statuses, true ) ) {
				continue;
			}

			$snippets[] = $snippet_data;
		}

		return rest_ensure_response( [
			'snippets' => $snippets,
			'total'    => count( $snippets ),
		] );
	}

	public function list_snippet_files( $request ) {
		$post = $this->resolve_snippet_post( $request );

		if ( ! $post ) {
			return new \WP_Error(
				'snippet_not_found',
				esc_html__( 'Snippet not found.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		$include_content = filter_var( $request->get_param( 'include_content' ), FILTER_VALIDATE_BOOLEAN );

		if ( $include_content ) {
			$files_with_content = Snippet_Repository::get_snippet_files_by_post( $post );
			$file_list = array_map( function ( $file ) {
				return [
					'name'    => $file['name'],
					'content' => $file['content'],
					'size'    => strlen( $file['content'] ),
				];
			}, $files_with_content );
		} else {
			$file_list = Snippet_Repository::get_snippet_file_list( $post->ID );
		}

		return rest_ensure_response( [
			'files'                  => $file_list,
			'total'                  => count( $file_list ),
			'isOwnedByCurrentUser'   => (int) $post->post_author === get_current_user_id(),
		] );
	}

	public function get_snippet_file( $request ) {
		$post = $this->resolve_snippet_post( $request );
		$filename = $request->get_param( 'filename' );

		if ( ! $post ) {
			return new \WP_Error(
				'snippet_not_found',
				esc_html__( 'Snippet not found.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		$file = Snippet_Repository::get_file_by_name( $post->ID, $filename );

		if ( ! $file ) {
			return new \WP_Error(
				'file_not_found',
				esc_html__( 'File not found in snippet.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		$content = '';
		if ( isset( $file['content_b64'] ) && is_string( $file['content_b64'] ) ) {
			$decoded = base64_decode( $file['content_b64'], true );
			$content = ( false === $decoded ) ? '' : $decoded;
		}

		return rest_ensure_response( [
			'name'    => $file['name'],
			'content' => $content,
			'size'    => strlen( $content ),
		] );
	}

	public function delete_snippet( $request ) {
		$post = $this->resolve_snippet_post( $request );

		if ( ! $post ) {
			return new \WP_Error(
				'snippet_not_found',
				esc_html__( 'Snippet not found.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		return $this->perform_delete( $post );
	}

	public function set_dev_mode( $request ) {
		$enabled = $request->get_param( 'enabled' );

		if ( $enabled ) {
			$session = Dev_Mode_Manager::create_dev_mode_session();

			if ( ! $session ) {
				return new \WP_Error( 'session_creation_failed',
					esc_html__( 'Failed to create test mode session. User must be logged in.', 'angie' ),
					[ 'status' => 403 ]
				);
			}

			return rest_ensure_response( [
				'success' => true,
				'message' => esc_html__( 'Test mode enabled.', 'angie' ),
				'enabled' => true,
				'expiry'  => $session['expiry'],
			] );
		} else {
			Dev_Mode_Manager::clear_dev_mode_session();

			return rest_ensure_response( [
				'success' => true,
				'message' => esc_html__( 'Test mode disabled.', 'angie' ),
				'enabled' => false,
			] );
		}
	}

	public function publish_snippet( $request ) {
		$post = $this->resolve_snippet_post( $request );

		if ( ! $post ) {
			return new \WP_Error(
				'snippet_not_found',
				esc_html__( 'Snippet not found.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		return $this->perform_publish( $post );
	}

	public function link_artifact_to_snippet( $request ) {
		$post = $this->resolve_snippet_post( $request );

		if ( ! $post ) {
			return new \WP_Error(
				'snippet_not_found',
				esc_html__( 'Snippet not found.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		if ( (int) $post->post_author !== get_current_user_id() ) {
			return new \WP_Error(
				'snippet_not_owned',
				esc_html__( 'This snippet was created by another user. You can only manage snippets you created. Shared snippet editing will be supported in the future.', 'angie' ),
				[ 'status' => 403 ]
			);
		}

		$artifact_id = $request->get_param( 'artifact_id' );

		$current_artifact_id = get_post_meta( $post->ID, '_angie_snippet_artifact_id', true );
		if ( ! empty( $current_artifact_id ) && $current_artifact_id !== $artifact_id ) {
			return new \WP_Error(
				'snippet_already_linked',
				esc_html__( 'This snippet is already linked to a cloud artifact. It cannot be re-linked.', 'angie' ),
				[ 'status' => 409 ]
			);
		}

		$existing = Snippet_Repository::find_snippet_post_by_artifact_id( $artifact_id );
		if ( $existing && $existing->ID !== $post->ID ) {
			return new \WP_Error(
				self::ERROR_ARTIFACT_SNIPPET_EXISTS,
				sprintf(
					/* translators: %s: artifact ID */
					esc_html__( 'A different snippet already uses artifact ID %s.', 'angie' ),
					$artifact_id
				),
				[ 'status' => 409 ]
			);
		}

		update_post_meta( $post->ID, '_angie_snippet_artifact_id', $artifact_id );

		return rest_ensure_response( [
			'success'     => true,
			'post_id'     => $post->ID,
			'artifact_id' => $artifact_id,
		] );
	}

	public function create_snippet_post( $request ) {
		$title = $request->get_param( 'title' );
		$type = $request->get_param( 'type' );
		$artifact_id = $request->get_param( 'artifact_id' );

		if ( empty( $title ) ) {
			return new \WP_Error(
				'missing_title',
				esc_html__( 'Title is required.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! empty( $type ) && ! Taxonomy_Manager::is_valid_type( $type ) ) {
			return new \WP_Error(
				'invalid_type',
				sprintf(
					/* translators: %s: provided type value */
					esc_html__( 'Invalid snippet type: %s.', 'angie' ),
					$type
				),
				[ 'status' => 400 ]
			);
		}

		if ( ! empty( $artifact_id ) ) {
			$existing = Snippet_Repository::find_snippet_post_by_artifact_id( $artifact_id );
			if ( $existing ) {
				return new \WP_Error(
					self::ERROR_ARTIFACT_SNIPPET_EXISTS,
					sprintf(
						/* translators: %s: artifact ID */
						esc_html__( 'A snippet already exists for artifact ID %s.', 'angie' ),
						$artifact_id
					),
					[ 'status' => 409 ]
				);
			}
		}

		$post_id = Snippet_Repository::create_snippet( $title );

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error(
				'snippet_create_failed',
				esc_html__( 'Failed to create snippet.', 'angie' ),
				[ 'status' => 500 ]
			);
		}

		if ( ! empty( $type ) ) {
			wp_set_object_terms( $post_id, $type, Taxonomy_Manager::TAXONOMY_NAME );
		}

		if ( ! empty( $artifact_id ) ) {
			update_post_meta( $post_id, '_angie_snippet_artifact_id', $artifact_id );
		}

		$version = $request->get_param( 'version' );
		if ( ! empty( $version ) ) {
			update_post_meta( $post_id, '_angie_snippet_version', $version );
		}

		return rest_ensure_response( [
			'id'    => $post_id,
			'title' => sanitize_text_field( $title ),
		] );
	}

	public function update_snippet_files( $request ) {
		$post = $this->resolve_snippet_post( $request );

		if ( ! $post ) {
			return new \WP_Error(
				'snippet_not_found',
				esc_html__( 'Snippet not found.', 'angie' ),
				[ 'status' => 404 ]
			);
		}

		$files = $request->get_param( 'files' );
		$type = $request->get_param( 'type' );

		$existing_files = Snippet_Repository::get_snippet_files_by_post( $post );
		$merged_files = Snippet_Repository::merge_snippet_files( $existing_files, $files );

		$sanitized_files = $this->sanitize_uploaded_files( $merged_files );
		if ( is_wp_error( $sanitized_files ) ) {
			return $sanitized_files;
		}

		if ( ! Snippet_Repository::has_main_php_file( $sanitized_files ) ) {
			return new \WP_Error(
				'main_php_required',
				esc_html__( 'Snippet must have a main.php file.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! empty( $type ) && ! Taxonomy_Manager::is_valid_type( $type ) ) {
			return new \WP_Error(
				'invalid_type',
				sprintf(
					/* translators: %s: provided type value */
					esc_html__( 'Invalid snippet type: %s.', 'angie' ),
					$type
				),
				[ 'status' => 400 ]
			);
		}

		if ( ! empty( $type ) ) {
			wp_set_object_terms( $post->ID, $type, Taxonomy_Manager::TAXONOMY_NAME );
		}

		Snippet_Repository::update_snippet_files( $post->ID, $sanitized_files );
		File_System_Handler::write_snippet_files_to_disk( Dev_Mode_Manager::ENV_DEV, $post->ID, $sanitized_files );
		Cache_Manager::clear_published_snippet_cache();

		$settings_error = Elementor_Preview_Settings::save_from_request( $post->ID, $request );
		if ( is_wp_error( $settings_error ) ) {
			return $settings_error;
		}

		return rest_ensure_response(
			Elementor_Preview_Settings::enrich_files_update_response(
				$post,
				[
					'success' => true,
					'message' => esc_html__( 'Snippet files updated successfully.', 'angie' ),
					'files'   => count( $sanitized_files ),
				]
			)
		);
	}

	public function rename_snippet_by_artifact( $request ) {
		$post = $this->resolve_snippet_post_by_artifact( $request );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $this->perform_rename( $post, $request->get_param( 'title' ) );
	}

	public function delete_snippet_by_artifact( $request ) {
		$post = $this->resolve_snippet_post_by_artifact( $request );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $this->perform_delete( $post );
	}

	public function publish_snippet_by_artifact( $request ) {
		$post = $this->resolve_snippet_post_by_artifact( $request );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $this->perform_publish( $post );
	}

	public function update_snippet_files_by_artifact( $request ) {
		$post = $this->resolve_snippet_post_by_artifact( $request );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$artifact_id = $request->get_param( 'artifact_id' );

		$files = $request->get_param( 'files' );

		$existing_files = Snippet_Repository::get_snippet_files_by_post( $post );
		$merged_files = Snippet_Repository::merge_snippet_files( $existing_files, $files );

		$sanitized_files = $this->sanitize_uploaded_files( $merged_files );
		if ( is_wp_error( $sanitized_files ) ) {
			return $sanitized_files;
		}

		if ( ! Snippet_Repository::has_main_php_file( $sanitized_files ) ) {
			return new \WP_Error(
				'main_php_required',
				esc_html__( 'Snippet must have a main.php file.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		$version = $request->get_param( 'version' );
		if ( ! empty( $version ) ) {
			update_post_meta( $post->ID, '_angie_snippet_version', $version );
		}

		Snippet_Repository::update_snippet_files( $post->ID, $sanitized_files );
		File_System_Handler::write_snippet_files_to_disk( Dev_Mode_Manager::ENV_DEV, $post->ID, $sanitized_files );
		Cache_Manager::clear_published_snippet_cache();

		$settings_error = Elementor_Preview_Settings::save_from_request( $post->ID, $request );
		if ( is_wp_error( $settings_error ) ) {
			return $settings_error;
		}

		return rest_ensure_response(
			Elementor_Preview_Settings::enrich_files_update_response(
				$post,
				[
					'success'     => true,
					'message'     => esc_html__( 'Snippet files updated successfully.', 'angie' ),
					'artifact_id' => $artifact_id,
					'files'       => count( $sanitized_files ),
				]
			)
		);
	}

	private function resolve_snippet_post( $request ) {
		$id = $request->get_param( 'id' );

		return Snippet_Repository::find_snippet_post_by_id( $id );
	}

	private function perform_rename( $post, $title ) {
		$result = wp_update_post( [
			'ID'         => $post->ID,
			'post_title' => sanitize_text_field( $title ),
		], true );

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				'rename_failed',
				esc_html__( 'Failed to rename snippet.', 'angie' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response( [
			'success' => true,
			'message' => esc_html__( 'Snippet renamed successfully.', 'angie' ),
			'post_id' => $post->ID,
			'title'   => $title,
		] );
	}

	private function perform_delete( $post ) {
		$environments = [ Dev_Mode_Manager::ENV_DEV, Dev_Mode_Manager::ENV_PROD ];
		File_System_Handler::delete_snippet_files( $post->ID, $environments );

		$result = Snippet_Repository::delete_snippet( $post->ID );

		if ( ! $result ) {
			return new \WP_Error(
				'delete_failed',
				esc_html__( 'Failed to delete snippet.', 'angie' ),
				[ 'status' => 500 ]
			);
		}

		Cache_Manager::clear_published_snippet_cache();

		return rest_ensure_response( [
			'success' => true,
			'message' => esc_html__( 'Snippet deleted successfully.', 'angie' ),
			'post_id' => $post->ID,
		] );
	}

	private function perform_publish( $post ) {
		$files = Snippet_Repository::get_snippet_files( $post->ID );

		if ( empty( $files ) ) {
			return new \WP_Error(
				'no_files',
				esc_html__( 'Snippet has no files to publish.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		File_System_Handler::write_snippet_files_to_disk( Dev_Mode_Manager::ENV_PROD, $post->ID, $files );
		Cache_Manager::clear_published_snippet_cache();

		$terms = wp_get_object_terms( $post->ID, Taxonomy_Manager::TAXONOMY_NAME, [ 'fields' => 'slugs' ] );
		$type = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0] : null;

		return rest_ensure_response( [
			'success' => true,
			'message' => esc_html__( 'Snippet published to production successfully.', 'angie' ),
			'post_id' => $post->ID,
			'type'    => $type,
			'files'   => count( $files ),
		] );
	}

	private function resolve_snippet_post_by_artifact( $request ) {
		$artifact_id = $request->get_param( 'artifact_id' );
		$post = Snippet_Repository::find_snippet_post_by_artifact_id( $artifact_id );

		if ( ! $post ) {
			return new \WP_Error(
				'snippet_not_found',
				sprintf(
					/* translators: %s: artifact ID */
					esc_html__( 'No snippet found for artifact: %s', 'angie' ),
					$artifact_id
				),
				[ 'status' => 404 ]
			);
		}

		return $post;
	}

	private function sanitize_uploaded_files( $files ) {
		if ( ! is_array( $files ) || empty( $files ) ) {
			return new \WP_Error(
				'invalid_files',
				esc_html__( 'Files parameter must be a non-empty array.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		if ( count( $files ) > self::MAX_FILES_PER_REQUEST ) {
			return new \WP_Error(
				'too_many_files',
				sprintf(
					/* translators: %d: maximum number of files */
					esc_html__( 'Cannot process more than %d files per request.', 'angie' ),
					self::MAX_FILES_PER_REQUEST
				),
				[ 'status' => 400 ]
			);
		}

		$sanitized_files = [];

		foreach ( $files as $file ) {
			if ( ! isset( $file['name'] ) || ! isset( $file['content'] ) ) {
				return new \WP_Error(
					'invalid_file_format',
					esc_html__( 'Each file must have "name" and "content" properties.', 'angie' ),
					[ 'status' => 400 ]
				);
			}

			$name = File_Validator::sanitize_filename( $file['name'] );
			$content = $file['content'];

			if ( empty( $name ) ) {
				return new \WP_Error(
					'invalid_filename',
					esc_html__( 'File name cannot be empty or contains invalid characters.', 'angie' ),
					[ 'status' => 400 ]
				);
			}

			$allowed_extensions = [ 'php', 'css', 'js' ];
			$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, $allowed_extensions, true ) ) {
				return new \WP_Error(
					'invalid_file_type',
					sprintf(
						/* translators: %s: filename */
						esc_html__( 'Invalid file type for: %s. Only PHP, CSS, and JS files are allowed.', 'angie' ),
						$name
					),
					[ 'status' => 400 ]
				);
			}

			if ( strlen( $content ) > self::MAX_FILE_SIZE_BYTES ) {
				return new \WP_Error(
					'file_too_large',
					sprintf(
						/* translators: %s: filename */
						esc_html__( 'File too large: %s. Maximum size is 100KB.', 'angie' ),
						$name
					),
					[ 'status' => 400 ]
				);
			}

			$sanitized_files[] = [
				'name'        => $name,
				'content_b64' => base64_encode( $content ),
			];
		}

		$validation_result = Snippet_Validator::validate_snippet_files( $sanitized_files );

		if ( is_wp_error( $validation_result ) ) {
			return new \WP_Error(
				$validation_result->get_error_code(),
				$validation_result->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		return $sanitized_files;
	}

	public function validate_snippet( $request ) {
		$files = $request->get_param( 'files' );

		if ( ! is_array( $files ) || empty( $files ) ) {
			return new \WP_Error(
				'invalid_files',
				esc_html__( 'Files parameter must be a non-empty array.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		$sanitized_files = [];

		foreach ( $files as $file ) {
			if ( ! isset( $file['name'] ) || ! isset( $file['content'] ) ) {
				return new \WP_Error(
					'invalid_file_format',
					esc_html__( 'Each file must have "name" and "content" properties.', 'angie' ),
					[ 'status' => 400 ]
				);
			}

			$name    = sanitize_text_field( $file['name'] );
			$content = $file['content'];

			$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( 'php' === $extension ) {
				$check_result = File_Validator::check_forbidden_functions( $content );
				if ( ! $check_result['allowed'] ) {
					return new \WP_Error(
						'forbidden_function',
						sprintf(
							/* translators: %s: function name */
							esc_html__( 'Forbidden function detected: %s', 'angie' ),
							$check_result['function']
						),
						[ 'status' => 400 ]
					);
				}
			}

			$sanitized_files[] = [
				'name'        => $name,
				'content'     => $content,
				'content_b64' => base64_encode( $content ),
			];
		}

		$validation_result = Snippet_Validator::validate_snippet_execution( $sanitized_files );

		if ( ! $validation_result['valid'] ) {
			return new \WP_Error(
				'validation_failed',
				$validation_result['error'],
				[
					'status'  => 400,
					'details' => $validation_result['details'],
				]
			);
		}

		return rest_ensure_response( [
			'valid'   => true,
			'message' => esc_html__( 'Snippet validation passed.', 'angie' ),
		] );
	}

	public function is_dev_mode( $request ) {
		return rest_ensure_response( [
			'enabled' => Dev_Mode_Manager::is_dev_mode_enabled(),
		] );
	}
}
