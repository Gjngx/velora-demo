<?php
namespace Angie\Modules\CodeSnippets\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class File_Validator {

	public static function sanitize_filename( $filename ): string {
		// Remove any HTML tags and special characters.
		$filename = sanitize_text_field( $filename );

		// Remove leading and trailing whitespace.
		$filename = trim( $filename );

		// Reject empty filenames.
		if ( empty( $filename ) ) {
			return '';
		}

		// Block null byte injection attacks.
		if ( strpos( $filename, "\0" ) !== false ) {
			return '';
		}

		// Block any path separators (forward slash or backslash).
		if ( strpos( $filename, '/' ) !== false || strpos( $filename, '\\' ) !== false ) {
			return '';
		}

		// Block parent directory references (..) to prevent path traversal.
		if ( strpos( $filename, '..' ) !== false ) {
			return '';
		}

		// Block absolute paths starting with / or \.
		if ( strpos( $filename, '/' ) === 0 || strpos( $filename, '\\' ) === 0 ) {
			return '';
		}

		// Verify that basename matches the original filename (no directory components).
		$basename = basename( $filename );

		if ( $basename !== $filename ) {
			return '';
		}

		return $filename;
	}

	public static function check_forbidden_functions( $code ): array {
		// List of dangerous PHP functions that can execute arbitrary code or system commands.
		$forbidden = [
			'eval',
			'exec',
			'shell_exec',
			'system',
			'passthru',
			'proc_open',
			'popen',
			'pcntl_exec',
			'assert',
			'create_function',
		];

		// Build a regex pattern to match any forbidden function call (word boundary + function name + optional whitespace + opening parenthesis).
		$pattern = '/\b(' . implode( '|', $forbidden ) . ')\s*\(/i';

		// Check if the code contains any forbidden function calls.
		if ( preg_match( $pattern, $code, $matches ) ) {
			return [
				'allowed'  => false,
				'function' => $matches[1],
			];
		}

		// No forbidden functions found - code is allowed.
		return [ 'allowed' => true ];
	}
}
