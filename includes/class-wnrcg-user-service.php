<?php
/**
 * Create or update users on unlock.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_User_Service
 */
class WNRCG_User_Service {

	/**
	 * Roles that must never be changed by the gate.
	 *
	 * @var string[]
	 */
	private const PROTECTED_ROLES = array( 'administrator', 'editor' );

	/**
	 * Process unlock submission and return user ID.
	 *
	 * @param string $name         Display name.
	 * @param string $job_title    Job title meta.
	 * @param string $poem_usage   Usage meta.
	 * @param string $email        Email.
	 * @param bool   $comms_opt_in Opt-in flag.
	 * @return int|WP_Error User ID or error.
	 */
	public static function upsert_from_unlock( string $name, string $job_title, string $poem_usage, string $email, bool $comms_opt_in ) {
		$user_id = email_exists( $email );

		if ( $user_id ) {
			return self::update_existing_user( (int) $user_id, $name, $job_title, $poem_usage, $comms_opt_in );
		}

		return self::create_new_user( $name, $job_title, $poem_usage, $email, $comms_opt_in );
	}

	/**
	 * Update historic / existing user.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $name         Submitted name.
	 * @param string $job_title    Job title.
	 * @param string $poem_usage   Poem usage.
	 * @param bool   $comms_opt_in Opt-in.
	 * @return int|WP_Error
	 */
	private static function update_existing_user( int $user_id, string $name, string $job_title, string $poem_usage, bool $comms_opt_in ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error( 'wnrcg_user_missing', __( 'Account could not be found.', 'wp-no-registration-content-gate' ) );
		}

		$roles              = (array) $user->roles;
		$has_protected_role = (bool) array_intersect( self::PROTECTED_ROLES, $roles );

		if ( ! $has_protected_role && empty( $roles ) ) {
			$user->set_role( 'subscriber' );
		}

		/**
		 * Whether to overwrite display_name / first_name from the unlock form.
		 *
		 * @param bool   $update  Default false.
		 * @param int    $user_id User ID.
		 * @param string $name    Submitted name.
		 */
		$default_update = (bool) WNRCG_Settings::get( 'update_display_name', false );
		$update_display = (bool) apply_filters( 'wnrcg_update_display_name', $default_update, $user_id, $name );

		if ( $update_display ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name,
					'first_name'   => $name,
				)
			);
		} else {
			update_user_meta( $user_id, 'wnrcg_last_submitted_name', $name );
		}

		self::save_profile_meta( $user_id, $job_title, $poem_usage, $comms_opt_in, false );

		return $user_id;
	}

	/**
	 * Create subscriber with random password; no notification emails.
	 *
	 * @param string $name         Name.
	 * @param string $job_title    Job title.
	 * @param string $poem_usage   Usage.
	 * @param string $email        Email.
	 * @param bool   $comms_opt_in Opt-in.
	 * @return int|WP_Error
	 */
	private static function create_new_user( string $name, string $job_title, string $poem_usage, string $email, bool $comms_opt_in ) {
		wnrcg_suppress_new_user_emails();

		$username = self::unique_username_from_email( $email );
		$password = wp_generate_password( 24, true, true );

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $name,
				'first_name'   => $name,
				'role'         => 'subscriber',
			)
		);

		self::save_profile_meta( (int) $user_id, $job_title, $poem_usage, $comms_opt_in, true );

		return (int) $user_id;
	}

	/**
	 * Persist gate-specific user meta.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $job_title    Job title.
	 * @param string $poem_usage   Usage.
	 * @param bool   $comms_opt_in Opt-in.
	 * @param bool   $is_new       First unlock.
	 */
	private static function save_profile_meta( int $user_id, string $job_title, string $poem_usage, bool $comms_opt_in, bool $is_new ): void {
		update_user_meta( $user_id, 'wnrcg_job_title', $job_title );
		update_user_meta( $user_id, 'wnrcg_poem_usage', $poem_usage );
		update_user_meta( $user_id, 'wnrcg_comms_opt_in', $comms_opt_in ? 'yes' : 'no' );
		update_user_meta( $user_id, 'wnrcg_last_unlocked_at', gmdate( 'c' ) );

		if ( $is_new ) {
			update_user_meta( $user_id, 'wnrcg_unlocked_at', gmdate( 'c' ) );
		}
	}

	/**
	 * Generate a unique username from email local part.
	 *
	 * @param string $email Email.
	 */
	private static function unique_username_from_email( string $email ): string {
		$local = strstr( $email, '@', true );
		if ( $local === false ) {
			$local = $email;
		}

		$base = sanitize_user( $local, true );
		if ( $base === '' ) {
			$base = 'guest';
		}

		$username = $base;
		$suffix   = 1;

		while ( username_exists( $username ) ) {
			$username = $base . '_' . $suffix;
			++$suffix;
		}

		return $username;
	}
}