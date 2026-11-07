<?php

namespace Angie\Modules\AngieSettings\Components;

use Angie\Classes\Angie_Capability_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Preferences {

	const ENABLE_ANGIE = 'angie_enabled';

	/**
	 * Initialize the preferences component
	 */
	public function __construct() {
		$this->register();
	}

	/**
	 * Register actions and hooks.
	 *
	 * @return void
	 */
	private function register() {
		add_action( 'personal_options', function ( \WP_User $user ) {
			$this->add_personal_options_settings( $user );
		} );

		add_action( 'personal_options_update', function ( $user_id ) {
			$this->update_personal_options_settings( $user_id );
		} );

		add_action( 'edit_user_profile_update', function ( $user_id ) {
			$this->update_personal_options_settings( $user_id );
		} );
	}

	/**
	 * Add settings to the "Personal Options".
	 *
	 * @param \WP_User $user - User object.
	 *
	 * @return void
	 */
	protected function add_personal_options_settings( \WP_User $user ) {
		if ( ! $this->has_permissions_to_edit_user( $user->ID ) ) {
			return;
		}

		// Only admins with angie capability can toggle angie preferences.
		if ( ! current_user_can( 'use_angie' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_angie_enabled = user_can( $user->ID, 'use_angie' );
		?>
			<tr>
				<th style="padding:0px">
					<h2><?php echo esc_html__( 'Angie - AI Assistant', 'angie' ); ?></h2>
				</th>
			</tr>
			<tr>
				<th>
					<label for="<?php echo esc_attr( self::ENABLE_ANGIE ); ?>">
						<?php echo esc_html__( 'Status', 'angie' ); ?>
					</label>
				</th>
				<td>
					<label for="<?php echo esc_attr( self::ENABLE_ANGIE ); ?>">
						<input name="<?php echo esc_attr( self::ENABLE_ANGIE ); ?>"
							id="<?php echo esc_attr( self::ENABLE_ANGIE ); ?>"
							type="checkbox"
							value="1"<?php checked( true, $is_angie_enabled ); ?> />
						<?php echo esc_html__( 'Display Angie', 'angie' ); ?>
					</label>
				</td>
			</tr>
		<?php
	}

	/**
	 * Save the settings in the "Personal Options".
	 *
	 * @param int $user_id - User ID.
	 *
	 * @return void
	 */
	protected function update_personal_options_settings( $user_id ) {
		// Check if current user is admin.
		$is_admin = current_user_can( 'manage_options' );

		// Only admins can toggle Angie preferences.
		if ( ! $is_admin ) {
			return;
		}

		// Verify nonce for security.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id ) ) {
			return;
		}

		$current_has_capability = Angie_Capability_Manager::user_has_capability( $user_id );

		$requested_angie_value = empty( $_POST[ self::ENABLE_ANGIE ] ) ? '0' : '1';
		$requested_to_enable = '1' === $requested_angie_value;

		// Update capability based on the requested value.
		if ( $requested_to_enable && ! $current_has_capability ) {
			// Grant capability.
			Angie_Capability_Manager::grant_capability_to_user( $user_id );
		} elseif ( ! $requested_to_enable && $current_has_capability ) {
			// Revoke capability.
			Angie_Capability_Manager::revoke_capability_from_user( $user_id );
		}
		// If no change needed (both true or both false), do nothing.
	}

	/**
	 * Determine if the current user has permission to view/change preferences of a user.
	 *
	 * @param int $user_id
	 *
	 * @return bool
	 */
	protected function has_permissions_to_edit_user( $user_id ) {
		return current_user_can( 'edit_user', $user_id );
	}
}
