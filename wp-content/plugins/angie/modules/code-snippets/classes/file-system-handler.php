<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class File_System_Handler {

	public static function init() {
		add_action( 'wp_trash_post', [ __CLASS__, 'handle_trash_post' ] );
	}

	public static function handle_trash_post( $post_id ) {
		$environments = [ Dev_Mode_Manager::ENV_DEV, Dev_Mode_Manager::ENV_PROD ];
		self::delete_snippet_files( $post_id, $environments );
	}

	public static function get_wp_filesystem() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem;
	}

	public static function write_snippet_files_to_disk( $environment, $post_id, $files ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$snippet_name = 'snippet-' . $post_id;

		$base_dir = WP_CONTENT_DIR . '/angie-snippets/' . $environment . '/' . $snippet_name;
		$wp_filesystem = self::get_wp_filesystem();

		if ( ! $wp_filesystem ) {
			return;
		}

		if ( ! $wp_filesystem->is_dir( $base_dir ) ) {
			wp_mkdir_p( $base_dir );
			$index_file = $base_dir . '/index.php';
			$wp_filesystem->put_contents( $index_file, '<?php' . PHP_EOL . '// Silence is golden.' . PHP_EOL, FS_CHMOD_FILE );
		}

		foreach ( $files as $file ) {
			$filename = File_Validator::sanitize_filename( $file['name'] );
			if ( empty( $filename ) ) {
				continue;
			}

			$content  = base64_decode( $file['content_b64'], true );
			if ( false === $content ) {
				continue;
			}

			$file_path = $base_dir . '/' . $filename;
			$wp_filesystem->put_contents( $file_path, $content, FS_CHMOD_FILE );

			self::maybe_invalidate_opcache( $file_path );
		}
	}

	private static function maybe_invalidate_opcache( $filepath ) {
		if ( ! function_exists( 'wp_opcache_invalidate' ) ) {
			return;
		}

		wp_opcache_invalidate( $filepath, true );
	}

	public static function delete_snippet_files( $post_id, $environments ) {
		$post = get_post( $post_id );
		if ( ! $post || Module::CPT_NAME !== $post->post_type ) {
			return;
		}

		$snippet_name = 'snippet-' . $post_id;

		$wp_filesystem = self::get_wp_filesystem();
		if ( ! $wp_filesystem ) {
			return;
		}

		foreach ( $environments as $environment ) {
			$snippet_dir = WP_CONTENT_DIR . '/angie-snippets/' . $environment . '/' . $snippet_name;
			if ( $wp_filesystem->is_dir( $snippet_dir ) ) {
				$wp_filesystem->delete( $snippet_dir, true );
			}
		}
	}
}
