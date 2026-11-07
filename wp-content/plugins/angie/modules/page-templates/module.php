<?php
namespace Angie\Modules\PageTemplates;

use Angie\Classes\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Angie page templates module.
 *
 * Page templates module handler class is responsible for registering
 * and managing Angie page templates for Gutenberg.
 *
 */
class Module extends Module_Base {

	const TEMPLATE_ANGIE_SLUG = 'angie-canvas';
	const TEMPLATE_ANGIE_NAME = 'Blank template (Angie)';

	public function __construct() {
		add_filter( 'get_block_templates', [ $this, 'add_canvas_template' ], 10, 3 );
		add_filter( 'theme_page_templates', [ $this, 'add_canvas_template_to_list' ], 11, 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'add_script_for_angie_canvas_template' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'add_script_for_angie_canvas_template' ] );
	}

	public function get_name(): string {
		return 'page-templates';
	}

	public function add_script_for_angie_canvas_template() {
		$data = [
			'templateSlug' => self::TEMPLATE_ANGIE_SLUG,
		];
	
		wp_register_script( 'angie-canvas-template', false, [], ANGIE_VERSION, true );
		wp_enqueue_script( 'angie-canvas-template' );
		wp_add_inline_script( 
			'angie-canvas-template', 
			'angieCanvasTemplateData = ' . wp_json_encode( $data ) . ';', 
			'before' 
		);
	}

	function add_canvas_template_to_list( $post_templates ) {
		$post_templates[  self::TEMPLATE_ANGIE_SLUG ] = self::TEMPLATE_ANGIE_NAME;
		return $post_templates;
	}

	public function add_canvas_template( $query_result, $query, $template_type ) {
		if ( 'wp_template' !== $template_type ) {
			return $query_result;
		}

		if ( isset( $query['slug__in'] ) && ! in_array( self::TEMPLATE_ANGIE_SLUG, $query['slug__in'], true ) ) {
			return $query_result;
		}

		$template_exists = false;
		foreach ( $query_result as $template ) {
			if ( self::TEMPLATE_ANGIE_SLUG === $template->slug ) {
				$template_exists = true;
				break;
			}
		}

		if ( $template_exists ) {
			return $query_result;
		}

		$canvas_template = new \WP_Block_Template();
		$canvas_template->type = 'wp_template';
		$canvas_template->theme = 'angie';
		$canvas_template->slug = self::TEMPLATE_ANGIE_SLUG;
		$canvas_template->id = 'angie//' . self::TEMPLATE_ANGIE_SLUG;
		$canvas_template->title = self::TEMPLATE_ANGIE_NAME;
		$canvas_template->description = esc_html__( 'Angie Blank template for Gutenberg', 'angie' );
		$canvas_template->content = '<!-- wp:post-content {"style":{"spacing":{"blockGap":"0"},"dimensions":{"minHeight":"0px"}},"layout":{"type":"constrained","contentSize":"1280px","justifyContent":"center"}} /-->';
		$canvas_template->source = 'plugin';
		$canvas_template->status = 'publish';
		$canvas_template->is_custom = true;
		$canvas_template->area = 'uncategorized';
		$canvas_template->author = null;
		$canvas_template->has_theme_file = false;
		$canvas_template->origin = 'plugin';
		$canvas_template->post_types = [];
		$canvas_template->wp_id = null;

		$query_result[] = $canvas_template;

		return $query_result;
	}
}


