<?php

namespace Angie\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Angie Capability Manager
 *
 * Utility class for managing the use_angie capability
 */
class Angie_Capability_Manager {

	const USE_ANGIE_CAPABILITY = 'use_angie';

	/**
	 * Check if current user can manage Angie capabilities
	 *
	 * @return bool True if user can manage capabilities, false otherwise.
	 */
	public static function current_user_can_manage_capabilities() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Grant use_angie capability to a specific user
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public static function grant_capability_to_user( $user_id ) {
		if ( ! self::current_user_can_manage_capabilities() ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		$user->add_cap( self::USE_ANGIE_CAPABILITY );
		return true;
	}

	/**
	 * Revoke use_angie capability from a specific user
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public static function revoke_capability_from_user( $user_id ) {
		if ( ! self::current_user_can_manage_capabilities() ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		$user->add_cap( self::USE_ANGIE_CAPABILITY, false );
		return true;
	}

		/**
		 * Check if a user has the use_angie capability
		 *
		 * @param int $user_id User ID. If not provided, uses current user.
		 * @return bool True if user has the capability, false otherwise.
		 */
	public static function user_has_capability( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		return $user->has_cap( self::USE_ANGIE_CAPABILITY );
	}

	/**
	 * Grant use_angie capability to specified roles on plugin activation
	 *
	 * @return void
	 */
	public static function add_angie_capability_to_default_roles() {
		// Grant to administrator role on activation.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( self::USE_ANGIE_CAPABILITY );
		}
	}
}
