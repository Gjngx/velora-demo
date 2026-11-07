<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;
use Angie\Modules\CodeSnippets\PreviewSettings\Elementor_Preview_Document;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class List_Table_Manager {

	public static function init() {
		add_filter( 'manage_' . Module::CPT_NAME . '_posts_columns', [ __CLASS__, 'add_custom_columns' ] );
		add_action( 'manage_' . Module::CPT_NAME . '_posts_custom_column', [ __CLASS__, 'render_custom_columns' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_filter( 'angie_config', [ __CLASS__, 'add_config' ] );
		add_action( 'wp_ajax_angie_toggle_snippet_status', [ __CLASS__, 'ajax_toggle_status' ] );
		add_action( 'wp_ajax_angie_push_to_production', [ __CLASS__, 'ajax_push_to_production' ] );
		add_filter( 'post_row_actions', [ __CLASS__, 'filter_post_row_actions' ], 10, 2 );
	}

	/**
	 * @param array<string, string> $actions
	 * @param \WP_Post              $post
	 * @return array<string, string>
	 */
	public static function filter_post_row_actions( $actions, $post ) {
		if ( Module::CPT_NAME !== $post->post_type ) {
			return $actions;
		}

		unset( $actions['inline hide-if-no-js'], $actions['view'] );

		$permalink = get_permalink( $post );
		if ( $permalink && Elementor_Preview_Document::is_elementor_widget_snippet( (int) $post->ID ) ) {
			$actions['angie_preview'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $permalink ),
				esc_html__( 'Preview', 'angie' )
			);
		}

		return $actions;
	}

	public static function add_custom_columns( $columns ) {
		$new_columns = [
			'cb' => $columns['cb'],
			'status_toggle' => esc_html__( 'Status', 'angie' ),
			'title' => $columns['title'],
			'environment' => esc_html__( 'Environment', 'angie' ),
			'revisions' => esc_html__( 'Revisions', 'angie' ),
			'actions' => esc_html__( 'Actions', 'angie' ),
			'last_modified' => esc_html__( 'Last Modified', 'angie' ),
		];

		$taxonomy_key = 'taxonomy-' . Taxonomy_Manager::TAXONOMY_NAME;

		if ( isset( $columns[ $taxonomy_key ] ) ) {
			$title_pos = array_search( 'title', array_keys( $new_columns ) ) + 1;

			$new_columns = array_merge(
				array_slice( $new_columns, 0, $title_pos, true ),
				[ $taxonomy_key => $columns[ $taxonomy_key ] ],
				array_slice( $new_columns, $title_pos, null, true )
			);
		}

		return $new_columns;
	}

	public static function render_custom_columns( $column, $post_id ) {
		if ( 'status_toggle' === $column ) {
			self::render_status_toggle( $post_id );
		} elseif ( 'revisions' === $column ) {
			self::render_revision_column( $post_id );
		} elseif ( 'environment' === $column ) {
			self::render_environment_column( $post_id );
		} elseif ( 'actions' === $column ) {
			self::render_actions_column( $post_id );
		} elseif ( 'last_modified' === $column ) {
			self::render_last_modified_column( $post_id );
		}
	}

	private static function render_status_toggle( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$is_published = 'publish' === $post->post_status;
		$checked = $is_published ? 'checked' : '';

		printf(
			'<label class="angie-snippet-toggle">
				<input type="checkbox" class="angie-snippet-toggle-input" data-post-id="%d" %s />
				<span class="angie-snippet-toggle-slider"></span>
			</label>',
			absint( $post_id ),
			esc_attr( $checked )
		);
	}

	private static function render_last_modified_column( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		if ( '0000-00-00 00:00:00' === $post->post_modified ) {
			$t_time = esc_html__( 'Unpublished', 'angie' );
		} else {
			$t_time = sprintf(
				/* translators: 1: Post date, 2: Post time. */
				esc_html__( '%1$s at %2$s', 'angie' ),
				/* translators: Post date format. See https://www.php.net/manual/datetime.format.php */
				get_the_modified_time( esc_html__( 'Y/m/d', 'angie' ), $post ),
				/* translators: Post time format. See https://www.php.net/manual/datetime.format.php */
				get_the_modified_time( esc_html__( 'g:i a', 'angie' ), $post )
			);
		}

		echo esc_html( $t_time );
	}

	private static function render_revision_column( $post_id ) {
		$version = get_post_meta( $post_id, '_angie_snippet_version', true );
		$revision_count = $version ? (int) $version : 1;

		echo '<span class="angie-revision-count">';
		echo esc_html( (string) $revision_count );
		echo '</span>';
	}

	private static function render_environment_column( $post_id ) {
		self::render_sync_status( $post_id );
	}

	public static function render_sync_status( $post_id ) {
		$timestamps = Dev_Mode_Manager::get_snippet_environment_timestamps( $post_id );
		$sync_status = $timestamps['status'];

		if ( Dev_Mode_Manager::SYNC_STATUS_NOT_DEPLOYED === $sync_status ) {
			$tooltip = esc_attr__( 'Snippet has not been deployed to test or live yet', 'angie' );
			echo '<span class="angie-env-status angie-env-not-deployed">';
			echo '<span class="angie-env-badge angie-env-badge-test"></span>';
			echo esc_html__( 'Not Deployed', 'angie' );
			self::render_tooltip_icon( $tooltip );
			echo '</span>';
		} elseif ( Dev_Mode_Manager::SYNC_STATUS_CHANGES_PENDING === $sync_status ) {
			$tooltip = esc_attr__( 'Snippet is live but has unpublished changes in test', 'angie' );
			echo '<span class="angie-env-status angie-env-not-synced">';
			echo '<span class="angie-env-badge angie-env-badge-warning"></span>';
			echo esc_html__( 'Live (out of sync)', 'angie' );
			self::render_tooltip_icon( $tooltip );
			echo '</span>';
		} elseif ( Dev_Mode_Manager::SYNC_STATUS_TEST_ONLY === $sync_status ) {
			$tooltip = esc_attr__( 'Snippet is only deployed to sandbox/test, not to live', 'angie' );
			echo '<span class="angie-env-status angie-env-test-only">';
			echo '<span class="angie-env-badge angie-env-badge-test"></span>';
			echo esc_html__( 'Sandbox only', 'angie' );
			self::render_tooltip_icon( $tooltip );
			echo '</span>';
		} else {
			$tooltip = esc_attr__( 'Snippet is deployed to live and matches the latest version', 'angie' );
			echo '<span class="angie-env-status angie-env-synced">';
			echo '<span class="angie-env-badge angie-env-badge-success"></span>';
			echo esc_html__( 'Live (synced)', 'angie' );
			self::render_tooltip_icon( $tooltip );
			echo '</span>';
		}
	}

	private static function render_tooltip_icon( $tooltip_text ) {
		printf(
			'<span class="angie-snippet-tooltip-trigger" data-tooltip="%s" aria-label="%s">',
			esc_attr( $tooltip_text ),
			esc_attr( $tooltip_text )
		);
		echo '<span class="angie-snippet-info-icon">i</span>';
		echo '</span>';
	}

	private static function render_actions_column( $post_id ) {
		$timestamps = Dev_Mode_Manager::get_snippet_environment_timestamps( $post_id );
		$dev_time = $timestamps['dev'];
		$prod_time = $timestamps['prod'];
		$is_deploy_button_disabled = Dev_Mode_Manager::is_deploy_button_disabled( $dev_time, $prod_time );

		$post = get_post( $post_id );
		$snippet_slug = $post ? $post->post_name : '';
		$artifact_id = get_post_meta( $post_id, '_angie_snippet_artifact_id', true );

		$disabled_attr = $is_deploy_button_disabled ? ' disabled' : '';
		$deploy_action = ( $dev_time > 0 ) ? 'push-to-production' : 'publish-to-dev';
		$button_text = ( $dev_time > 0 ) ? esc_html__( 'Push to Live', 'angie' ) : esc_html__( 'Push to Test', 'angie' );

		printf(
			'<button type="button" class="button angie-push-to-production" data-post-id="%d" data-snippet-slug="%s" data-artifact-id="%s" data-action="%s"%s>%s</button>',
			absint( $post_id ),
			esc_attr( $snippet_slug ),
			esc_attr( $artifact_id ),
			esc_attr( $deploy_action ),
			esc_attr( $disabled_attr ),
			esc_html( $button_text )
		);
	}

	public static function enqueue_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . Module::CPT_NAME !== $screen->id ) {
			return;
		}

		if ( ! Module::current_user_can_manage_snippets() ) {
			return;
		}

		$style_url = plugins_url( 'assets/css/list-table-toggle.css', dirname( __FILE__ ) );
		wp_enqueue_style(
			'angie-list-table-toggle',
			$style_url,
			[],
			ANGIE_VERSION
		);
	}

	public static function add_config( $config ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . Module::CPT_NAME !== $screen->id ) {
			return $config;
		}

		if ( ! Module::current_user_can_manage_snippets() ) {
			return $config;
		}

		$config['listTable'] = [
			'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
			'nonce'                 => wp_create_nonce( 'angie_toggle_snippet_status' ),
			'pushToProductionNonce' => wp_create_nonce( 'angie_push_to_production' ),
		];

		return $config;
	}

	public static function ajax_toggle_status() {
		check_ajax_referer( 'angie_toggle_snippet_status', 'nonce' );

		if ( ! Module::current_user_can_manage_snippets() ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Insufficient permissions', 'angie' ) ] );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid post ID', 'angie' ) ] );
		}

		$post = get_post( $post_id );
		if ( ! $post || Module::CPT_NAME !== $post->post_type ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid snippet', 'angie' ) ] );
		}

		$new_status = 'publish' === $post->post_status ? 'draft' : 'publish';

		$updated = wp_update_post(
			[
				'ID' => $post_id,
				'post_status' => $new_status,
			],
			true
		);

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( [ 'message' => $updated->get_error_message() ] );
		}

		wp_send_json_success(
			[
				'status'  => $new_status,
				'message' => esc_html__( 'Status updated successfully', 'angie' ),
			]
		);
	}

	public static function ajax_push_to_production() {
		check_ajax_referer( 'angie_push_to_production', 'nonce' );

		if ( ! Module::current_user_can_manage_snippets() ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Insufficient permissions', 'angie' ) ] );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid post ID', 'angie' ) ] );
		}

		$post = get_post( $post_id );
		if ( ! $post || Module::CPT_NAME !== $post->post_type ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid snippet', 'angie' ) ] );
		}

		$timestamps = Dev_Mode_Manager::get_snippet_environment_timestamps( $post_id );
		$dev_time = $timestamps['dev'];

		if ( $dev_time > 0 ) {
			$success = Dev_Mode_Manager::push_snippet_to_production( $post_id );
			$success_message = esc_html__( 'Successfully pushed to production', 'angie' );
		} else {
			$success = Dev_Mode_Manager::push_snippet_to_dev( $post_id );
			$success_message = esc_html__( 'Successfully published to test', 'angie' );
		}

		if ( ! $success ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No files to deploy', 'angie' ) ] );
		}

		wp_send_json_success(
			[
				'message' => $success_message,
			]
		);
	}
}
