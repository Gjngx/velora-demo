<?php
namespace Angie\Modules\SuperAdmin\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around wp_create_nonce / wp_verify_nonce scoped to the
 * 'angie_super_admin_exec' action. WordPress nonces are user-specific,
 * time-limited (24h default), and require no custom storage.
 */
class Execution_Token {

	const NONCE_ACTION = 'angie_super_admin_exec';

	public static function generate(): string {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	public static function validate( string $token ): bool {
		if ( empty( $token ) ) {
			return false;
		}
		return (bool) wp_verify_nonce( $token, self::NONCE_ACTION );
	}
}
