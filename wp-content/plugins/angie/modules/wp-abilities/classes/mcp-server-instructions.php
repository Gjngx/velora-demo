<?php

namespace Angie\Modules\WpAbilities\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Server_Instructions {

	public static function get_answer_format_instructions(): string {
		return rtrim( self::load_markdown( 'answer-format.md' ) );
	}

	public static function get_tool_instructions(): string {
		return rtrim( self::load_markdown( 'tool-instructions.md' ) );
	}

	public static function get(): string {
		return self::format_combined_instructions(
			self::get_answer_format_instructions(),
			self::get_tool_instructions()
		);
	}

	private static function load_markdown( string $filename ): string {
		$path = dirname( __DIR__ ) . '/instructions/' . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local markdown instruction file.
		return (string) file_get_contents( $path );
	}

	private static function format_combined_instructions( string $answer_format_instructions, string $tool_instructions ): string {
		return rtrim( $answer_format_instructions )
			. "\n\n---\n\n# Angie tool instructions\n\n"
			. rtrim( $tool_instructions );
	}
}
