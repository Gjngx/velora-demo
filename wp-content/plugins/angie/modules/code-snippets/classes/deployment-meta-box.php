<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Deployment_Meta_Box {

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_deployment_meta_box' ] );
		add_action( 'save_post_' . Module::CPT_NAME, [ __CLASS__, 'save_deployment_meta' ], 5 );
		add_action( 'admin_post_angie_delete_environment', [ __CLASS__, 'handle_delete_environment' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'post_submitbox_misc_actions', [ __CLASS__, 'render_publish_box_toggle' ] );
		add_filter( 'angie_config', [ __CLASS__, 'add_config' ] );
	}

	public static function enqueue_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Module::CPT_NAME !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'angie-list-table-toggle',
			plugins_url( 'assets/css/list-table-toggle.css', dirname( __FILE__ ) ),
			[],
			ANGIE_VERSION
		);

		wp_add_inline_style(
			'angie-list-table-toggle',
			'#misc-publishing-actions .misc-pub-section:not(.angie-publish-toggle) { display: none !important; }
.angie-publish-toggle { display: flex !important; align-items: center; gap: 8px; padding: 6px 10px; }
.angie-publish-toggle[hidden] { display: none !important; }'
		);
	}

	public static function add_config( $config ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Module::CPT_NAME !== $screen->post_type ) {
			return $config;
		}

		if ( 'post' !== $screen->base && 'post-new' !== $screen->base ) {
			return $config;
		}

		$config['deploymentMetaBox'] = [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'angie_toggle_snippet_status' ),
			'artifactId' => get_post_meta( get_the_ID(), '_angie_snippet_artifact_id', true ),
		];

		return $config;
	}

	public static function render_publish_box_toggle( $post ) {
		if ( Module::CPT_NAME !== get_post_type( $post ) ) {
			return;
		}

		$is_published = 'publish' === $post->post_status;
		$checked      = $is_published ? 'checked' : '';

		echo '<div class="misc-pub-section angie-publish-toggle">';
		echo '<strong>' . esc_html__( 'Status:', 'angie' ) . '</strong>';
		printf(
			'<label class="angie-snippet-toggle">
				<input type="checkbox" class="angie-snippet-toggle-input" data-post-id="%d" %s />
				<span class="angie-snippet-toggle-slider"></span>
			</label>',
			absint( $post->ID ),
			esc_attr( $checked )
		);
		echo '<span class="angie-metabox-status-label">' . ( $is_published ? esc_html__( 'Active', 'angie' ) : esc_html__( 'Inactive', 'angie' ) ) . '</span>';
		echo '</div>';
	}

	public static function add_deployment_meta_box() {
		add_meta_box(
			'angie_snippet_deployment',
			esc_html__( 'Environment & Deployment', 'angie' ),
			[ __CLASS__, 'render_deployment_meta_box' ],
			Module::CPT_NAME,
			'side',
			'default'
		);
	}

	public static function render_deployment_meta_box( $post ) {
		wp_nonce_field( 'angie_snippet_deployment_save', 'angie_snippet_deployment_nonce' );

		$timestamps = Dev_Mode_Manager::get_snippet_environment_timestamps( $post->ID );
		$dev_time = $timestamps['dev'];
		$prod_time = $timestamps['prod'];
		$delete_url_base = admin_url( 'admin-post.php' );

		echo '<div style="padding: 10px 0;">';
		echo '<p><strong>' . esc_html__( 'Current Live Version:', 'angie' ) . '</strong><br>';
		if ( $prod_time > 0 ) {
			echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $prod_time ) );
			$delete_prod_url = wp_nonce_url(
				add_query_arg(
					[
						'action'      => 'angie_delete_environment',
						'post_id'     => $post->ID,
						'environment' => Dev_Mode_Manager::ENV_PROD,
					],
					$delete_url_base
				),
				'angie_delete_environment_' . $post->ID
			);
			echo '<br><a href="' . esc_url( $delete_prod_url ) . '" class="button-link-delete" onclick="return confirm(\'' . esc_js( esc_html__( 'Are you sure you want to delete the Live environment?', 'angie' ) ) . '\');">' . esc_html__( 'Delete Live', 'angie' ) . '</a>';
		} else {
			echo '<em>' . esc_html__( 'Not deployed yet', 'angie' ) . '</em>';
		}
		echo '</p>';

		echo '<p><strong>' . esc_html__( 'Current Work Version:', 'angie' ) . '</strong><br>';
		if ( $dev_time > 0 ) {
			echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $dev_time ) );
			$delete_dev_url = wp_nonce_url(
				add_query_arg(
					[
						'action'      => 'angie_delete_environment',
						'post_id'     => $post->ID,
						'environment' => Dev_Mode_Manager::ENV_DEV,
					],
					$delete_url_base
				),
				'angie_delete_environment_' . $post->ID
			);
			echo '<br><a href="' . esc_url( $delete_dev_url ) . '" class="button-link-delete" onclick="return confirm(\'' . esc_js( esc_html__( 'Are you sure you want to delete the Work environment?', 'angie' ) ) . '\');">' . esc_html__( 'Delete Work', 'angie' ) . '</a>';
		} else {
			echo '<em>' . esc_html__( 'Not saved yet', 'angie' ) . '</em>';
		}
		echo '</p>';

		echo '<p><strong>' . esc_html__( 'Sync Status:', 'angie' ) . '</strong><br>';
		List_Table_Manager::render_sync_status( $post->ID );
		echo '</p>';

		echo '<p>';
		$is_deploy_button_disabled = Dev_Mode_Manager::is_deploy_button_disabled( $dev_time, $prod_time );
		$deploy_action = ( $dev_time > 0 ) ? 'push-to-production' : 'publish-to-dev';
		$button_text = ( $dev_time > 0 ) ? esc_html__( 'Push to Production', 'angie' ) : esc_html__( 'Push to Test', 'angie' );
		$button_attrs = [ 'data-action' => $deploy_action ];
		if ( $is_deploy_button_disabled ) {
			$button_attrs['disabled'] = 'disabled';
		}
		submit_button( $button_text, 'primary', 'angie_push_to_production', false, $button_attrs );
		echo '</p>';
		echo '</div>';
	}

	public static function save_deployment_meta( $post_id ) {
		if ( ! isset( $_POST['angie_snippet_deployment_nonce'] ) ) {
			return;
		}

		if ( ! isset( $_POST['angie_push_to_production'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['angie_snippet_deployment_nonce'] ) ), 'angie_snippet_deployment_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! Module::current_user_can_manage_snippets() ) {
			return;
		}


		$timestamps = Dev_Mode_Manager::get_snippet_environment_timestamps( $post_id );
		$dev_time = $timestamps['dev'];

		if ( $dev_time > 0 ) {
			Dev_Mode_Manager::push_snippet_to_production( $post_id );
		} else {
			Dev_Mode_Manager::push_snippet_to_dev( $post_id );
		}
	}

	public static function handle_delete_environment() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$environment = isset( $_GET['environment'] ) ? sanitize_text_field( wp_unslash( $_GET['environment'] ) ) : '';

		if ( ! $post_id || ! $environment ) {
			wp_die( esc_html__( 'Invalid request.', 'angie' ) );
		}

		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'angie_delete_environment_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'angie' ) );
		}

		if ( ! Module::current_user_can_manage_snippets() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'angie' ) );
		}

		$valid_environments = [ Dev_Mode_Manager::ENV_DEV, Dev_Mode_Manager::ENV_PROD ];
		if ( ! in_array( $environment, $valid_environments, true ) ) {
			wp_die( esc_html__( 'Invalid environment.', 'angie' ) );
		}

		File_System_Handler::delete_snippet_files( $post_id, [ $environment ] );

		$redirect_url = get_edit_post_link( $post_id, 'raw' );
		wp_safe_redirect( $redirect_url );
		exit;
	}
}
