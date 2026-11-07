<?php

namespace Angie\Modules\PluginAvailability\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Availability Marker Component
 *
 * Prints an early inline script so consumers can detect Angie synchronously.
 */
class Availability_Marker {

	/**
	 * Whether the marker has already been printed for this request.
	 *
	 * @var bool
	 */
	private bool $marker_printed = false;

	public function __construct() {
		add_action( 'admin_head', [ $this, 'print_marker' ], 1 );
		add_action( 'wp_head', [ $this, 'print_marker' ], 1 );
	}

	public function print_marker(): void {
		if ( $this->marker_printed ) {
			return;
		}

		$this->marker_printed = true;

		$marker = $this->build_marker();

		echo '<script>Object.defineProperty(window,"angiePlugin",{value:' . wp_json_encode( $marker ) . ',writable:false,configurable:false});</script>';
	}

	/**
	 * Build the marker payload with an allowlisted schema.
	 *
	 * @return array{schemaVersion: int, available: bool, version: string}
	 */
	private function build_marker(): array {
		$defaults = [
			'schemaVersion' => 1,
			'available' => true,
			'version' => ANGIE_VERSION,
		];

		$filtered = apply_filters( 'angie_plugin_availability_marker', $defaults );

		if ( ! is_array( $filtered ) ) {
			$filtered = $defaults;
		}

		return [
			'schemaVersion' => (int) ( $filtered['schemaVersion'] ?? $defaults['schemaVersion'] ),
			'available' => (bool) ( $filtered['available'] ?? $defaults['available'] ),
			'version' => (string) ( $filtered['version'] ?? $defaults['version'] ),
		];
	}
}
