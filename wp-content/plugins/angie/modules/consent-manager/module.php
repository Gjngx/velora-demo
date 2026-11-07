<?php

namespace Angie\Modules\ConsentManager;

use Angie\Classes\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Consent Manager Module
 *
 * Manages user consent for external scripts
 */
class Module extends Module_Base {

	/**
	 * @var string The consent option name in WordPress options table
	 */
	const CONSENT_OPTION_NAME = 'angie_external_scripts_consent';

	/**
	 * Get module name.
	 *
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'consent-manager';
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register components.
		$this->register_components([
			'Consent_Page',
			'Consent_Notice',
		]);
	}

	/**
	 * Check if user has given consent for external scripts
	 *
	 * @return bool
	 */
	public static function has_consent(): bool {
		return 'yes' === get_option( self::CONSENT_OPTION_NAME, 'no' );
	}
}
