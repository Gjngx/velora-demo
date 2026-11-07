<?php

namespace Angie\Modules\CodeSnippets\PreviewSettings;

use Angie\Modules\CodeSnippets\Classes\Dev_Mode_Manager;
use Angie\Modules\CodeSnippets\Classes\Snippet_Repository;
use Angie\Modules\CodeSnippets\Classes\Widget_Name_Resolver;
use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor_Preview_Document {

	private static ?Elementor_Preview_Context $context = null;

	/**
	 * @var array<int, bool>
	 */
	private static $registered_for_preview = [];

	public static function register_hooks(): void {
		add_filter( 'pre_option_elementor_element_cache_ttl', static fn( $value ) => self::is_active() ? 'disable' : $value );
		add_filter( 'image_downsize', [ Elementor_Preview_Settings_Fallback::class, 'filter_image_downsize' ], 10, 3 );
	}

	/**
	 * @param int $post_id
	 */
	public static function activate( int $post_id ): void {
		self::deactivate();

		self::$context = new Elementor_Preview_Context( $post_id );
		self::$context->activate();
	}

	public static function deactivate(): void {
		if ( null === self::$context ) {
			return;
		}

		self::$context->deactivate();
		self::$context = null;
	}

	public static function is_active(): bool {
		return null !== self::$context && self::$context->is_active();
	}

	/**
	 * @param \WP_Post $snippet_post
	 */
	public static function bootstrap_frontend_preview( $snippet_post ): void {
		global $post;

		if ( $snippet_post instanceof \WP_Post ) {
			$post = $snippet_post;
		} else {
			$post = get_post( (int) $snippet_post );
		}

		if ( ! $post instanceof \WP_Post || Module::CPT_NAME !== $post->post_type ) {
			wp_die(
				esc_html__( 'Snippet preview is unavailable.', 'angie' ),
				esc_html__( 'Preview unavailable', 'angie' ),
				[ 'response' => 404 ]
			);
		}

		self::activate( (int) $post->ID );

		add_action( 'wp_head', [ __CLASS__, 'print_frontend_preview_head_tags' ], 0 );
		add_filter( 'body_class', [ __CLASS__, 'add_frontend_preview_body_class' ] );
		add_filter( 'template_include', [ __CLASS__, 'filter_frontend_preview_template' ], 999 );
		add_action( 'shutdown', [ __CLASS__, 'finish_frontend_preview_request' ], 0 );
	}

	public static function print_frontend_preview_head_tags(): void {
		if ( ! self::is_active() ) {
			return;
		}

		echo '<meta name="robots" content="noindex,nofollow">' . "\n";
	}

	/**
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function add_frontend_preview_body_class( array $classes ): array {
		if ( ! self::is_active() ) {
			return $classes;
		}

		$classes[] = 'angie-snippet-preview';

		return $classes;
	}

	/**
	 * @param string $template
	 * @return string
	 */
	public static function filter_frontend_preview_template( $template ) {
		if ( ! self::is_active() ) {
			return $template;
		}

		$canvas_template = self::get_elementor_canvas_template_path();

		return '' !== $canvas_template ? $canvas_template : $template;
	}

	public static function finish_frontend_preview_request(): void {
		self::deactivate();
	}

	/**
	 * @return string
	 */
	private static function get_elementor_canvas_template_path(): string {
		if ( ! defined( 'ELEMENTOR_PATH' ) ) {
			return '';
		}

		$template_path = ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php';

		return is_readable( $template_path ) ? $template_path : '';
	}

	public static function is_elementor_widget_snippet( int $post_id ): bool {
		return Snippet_Type_Resolver::is_elementor_widget_snippet( $post_id );
	}

	/**
	 * @param int $post_id
	 * @return bool
	 */
	public static function ensure_snippet_registered_for_preview( $post_id ) {
		$post_id = (int) $post_id;

		if ( isset( self::$registered_for_preview[ $post_id ] ) ) {
			return self::$registered_for_preview[ $post_id ];
		}

		$files = Snippet_Repository::get_snippet_files( $post_id );
		if ( empty( $files ) || ! Snippet_Repository::has_main_php_file( $files ) ) {
			self::$registered_for_preview[ $post_id ] = false;

			return false;
		}

		$environment = Dev_Mode_Manager::is_dev_mode_enabled() ? Dev_Mode_Manager::ENV_DEV : Dev_Mode_Manager::ENV_PROD;
		$main        = WP_CONTENT_DIR . '/angie-snippets/' . $environment . '/snippet-' . $post_id . '/main.php';
		if ( ! file_exists( $main ) || ! is_readable( $main ) ) {
			self::$registered_for_preview[ $post_id ] = false;

			return false;
		}

		require_once $main;

		if ( ! did_action( 'elementor/widgets/register' ) && class_exists( '\Elementor\Plugin' ) ) {
			do_action( 'elementor/widgets/register', \Elementor\Plugin::instance()->widgets_manager );
		}

		self::$registered_for_preview[ $post_id ] = true;

		return true;
	}

	/**
	 * @param int $post_id
	 * @return string
	 */
	public static function build_elementor_data_json( int $post_id ): string {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}

		$widget_type = self::get_widget_type_for_snippet( $post_id );
		if ( ! $widget_type || ! method_exists( $widget_type, 'get_name' ) ) {
			return '';
		}

		$widget_name = $widget_type->get_name();
		if ( ! is_string( $widget_name ) || '' === $widget_name ) {
			return '';
		}

		$settings = Elementor_Preview_Settings::resolve_settings( $post_id, $widget_type );

		$elements = self::build_elements_data( $post_id, $widget_name, $settings );
		$json     = wp_json_encode( $elements );

		return is_string( $json ) ? $json : '';
	}

	/**
	 * @param int $post_id
	 * @return object|null
	 */
	public static function get_widget_type_for_snippet( int $post_id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return null;
		}

		$widget_name = Widget_Name_Resolver::get_widget_name_for_snippet( $post_id );
		if ( ! is_string( $widget_name ) || '' === $widget_name ) {
			return null;
		}

		if ( ! self::ensure_snippet_registered_for_preview( $post_id ) ) {
			return null;
		}

		$plugin      = \Elementor\Plugin::instance();
		$widget_type = $plugin->widgets_manager->get_widget_types( $widget_name );
		if ( ! $widget_type && ! did_action( 'elementor/widgets/register' ) ) {
			do_action( 'elementor/widgets/register', $plugin->widgets_manager );
			$widget_type = $plugin->widgets_manager->get_widget_types( $widget_name );
		}

		return $widget_type ? $widget_type : null;
	}

	/**
	 * @param int                    $post_id
	 * @param string                 $widget_name
	 * @param array<string, mixed>   $settings
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_elements_data( int $post_id, string $widget_name, array $settings ): array {
		return [
			[
				'id'       => 'angie-snippet-' . $post_id . '-section',
				'elType'   => 'section',
				'isInner'  => false,
				'settings' => [],
				'elements' => [
					[
						'id'       => 'angie-snippet-' . $post_id . '-column',
						'elType'   => 'column',
						'settings' => [
							'_column_size' => 100,
						],
						'elements' => [
							[
								'id'         => 'angie-snippet-' . $post_id . '-widget',
								'elType'     => 'widget',
								'widgetType' => $widget_name,
								'settings'   => $settings,
							],
						],
					],
				],
			],
		];
	}
}
