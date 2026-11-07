<?php

namespace Angie\Modules\PluginManager\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin Manager REST API Controller
 *
 * Provides access to WordPress native plugin REST API endpoints
 */
class Plugin extends Base {

	protected $namespace = 'angie/v1';

	protected $rest_base = 'plugins';

	public function register_routes() {
		register_rest_route( $this->namespace, $this->rest_base . '/update', [
			'methods' => 'POST',
			'callback' => [ $this, 'update_plugin' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args' => [
				'plugins' => [
					'required' => true,
					'type' => 'array',
					'sanitize_callback' => [ $this, 'sanitize_plugins_array' ],
					'description' => 'Plugins to update',
				],
			],
		] );
	}

	public function permissions_check() {
		return current_user_can( 'update_plugins' );
	}

	public function sanitize_plugins_array( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return [];
		}

		return array_map( 'sanitize_text_field', $plugins );
	}

	public function update_plugin( \WP_REST_Request $request ) {
		$plugins = $request->get_param( 'plugins' );

		if ( empty( $plugins ) ) {
			return new \WP_Error(
				'invalid_request',
				esc_html__( 'No plugins specified for update.', 'angie' ),
				[ 'status' => 400 ]
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		$skin = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );

		try {
			$results = $upgrader->bulk_upgrade( $plugins );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'plugin_update_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}

		if ( false === $results ) {
			if ( $skin->get_errors()->has_errors() ) {
				return new \WP_Error(
					'plugin_update_error',
					$skin->get_error_messages(),
					[ 'status' => 500 ]
				);
			}

			return new \WP_Error(
				'plugin_update_error',
				esc_html__( 'Plugin update failed.', 'angie' ),
				[ 'status' => 500 ]
			);
		}

		$plugin_results = [];
		$successful_updates = [];
		$failed_updates = [];

		foreach ( $results as $plugin => $result ) {
			if ( is_wp_error( $result ) ) {
				$error_message = $result->get_error_message();
				$plugin_results[ $plugin ] = [
					'error' => $error_message,
				];
				$failed_updates[] = [
					'plugin' => $plugin,
					'error' => $error_message,
				];

				continue;
			}

			if ( false === $result ) {
				$error_message = esc_html__( 'Update failed for an unknown reason.', 'angie' );
				$plugin_results[ $plugin ] = [
					'error' => $error_message,
				];
				$failed_updates[] = [
					'plugin' => $plugin,
					'error' => $error_message,
				];

				continue;
			}

			$plugin_results[ $plugin ] = [
				'success' => true,
			];
			$successful_updates[] = $plugin;
		}

		$total_plugins = count( $plugins );
		$success_count = count( $successful_updates );
		$failure_count = count( $failed_updates );

		if ( 0 === $success_count && $failure_count > 0 ) {
			$failed_messages = array_map( function( $f ) {
				return $f['plugin'] . ': ' . $f['error'];
			}, $failed_updates );

			return rest_ensure_response( [
				'success' => false,
				'message' => 'Plugin update failed: ' . implode( ', ', $failed_messages ),
				'failed_plugins' => $failed_updates,
				'update_count' => 0,
				'data' => $plugin_results,
			] );
		}

		$message = 0 === $failure_count
			? $success_count . ' out of ' . $total_plugins . ' plugins updated successfully'
			: $success_count . ' out of ' . $total_plugins . ' plugins updated successfully. ' . $failure_count . ' failed: ' . implode( ', ', array_column( $failed_updates, 'error' ) );

		return rest_ensure_response( [
			'success' => $success_count > 0,
			'message' => $message,
			'updated_plugins' => $successful_updates,
			'failed_plugins' => $failed_updates,
			'update_count' => $success_count,
			'data' => $plugin_results,
		] );
	}
}
