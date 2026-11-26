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
	 * @var string[]
	 */
	private const PROTECTED_ROLES = array( 'administrator', 'editor' );

	/**
	 * @param array<string, mixed> $values Parsed field submission keyed by field id.
	 * @return int|WP_Error
	 */
	public static function upsert_from_unlock( array $values ) {
		$email = self::normalize_email(
			self::value_for_storage( $values, WNRCG_Fields::STORAGE_USER_EMAIL )
		);
		if ( $email === '' || ! is_email( $email ) ) {
			return new WP_Error( 'wnrcg_invalid_email', __( 'A valid email address is required.', 'wp-no-registration-content-gate' ) );
		}

		$name = self::value_for_storage( $values, WNRCG_Fields::STORAGE_DISPLAY_NAME );

		$user_id = email_exists( $email );
		if ( $user_id ) {
			return self::update_existing_user( (int) $user_id, $name, $values );
		}

		return self::create_new_user( $name, $email, $values );
	}

	/**
	 * @param array<string, mixed> $values Values.
	 * @param string               $storage Storage key.
	 */
	private static function value_for_storage( array $values, string $storage ): string {
		$field = WNRCG_Fields::get_field_by_storage( $storage );
		if ( ! $field ) {
			return '';
		}
		$id = (string) $field['id'];
		if ( $field['type'] === WNRCG_Fields::TYPE_CHECKBOX ) {
			return ! empty( $values[ $id ] ) ? '1' : '';
		}
		return (string) ( $values[ $id ] ?? '' );
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param string               $name    Display name from form.
	 * @param array<string, mixed> $values  All values.
	 * @return int|WP_Error
	 */
	private static function update_existing_user( int $user_id, string $name, array $values ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error( 'wnrcg_user_missing', __( 'Account could not be found.', 'wp-no-registration-content-gate' ) );
		}

		$roles              = (array) $user->roles;
		$has_protected_role = (bool) array_intersect( self::PROTECTED_ROLES, $roles );

		if ( ! $has_protected_role && empty( $roles ) ) {
			$user->set_role( 'subscriber' );
		}

		if ( $name !== '' ) {
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
		}

		self::persist_field_values( $user_id, $values, false );

		return $user_id;
	}

	/**
	 * @param string               $name   Name.
	 * @param string               $email  Email.
	 * @param array<string, mixed> $values Values.
	 * @return int|WP_Error
	 */
	private static function create_new_user( string $name, string $email, array $values ) {
		wnrcg_suppress_new_user_emails();

		$username = self::unique_username_from_email( $email );
		$password = wp_generate_password( 24, true, true );

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$display = $name !== '' ? $name : $username;

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $display,
				'first_name'   => $display,
				'role'         => 'subscriber',
			)
		);

		self::persist_field_values( (int) $user_id, $values, true );

		return (int) $user_id;
	}

	/**
	 * Save meta-backed answers and unlock timestamps.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $values  Submission.
	 * @param bool                 $is_new  First unlock.
	 */
	private static function persist_field_values( int $user_id, array $values, bool $is_new ): void {
		foreach ( WNRCG_Fields::all() as $field ) {
			$id      = (string) $field['id'];
			$storage = (string) $field['storage'];

			if ( in_array( $storage, array( WNRCG_Fields::STORAGE_USER_EMAIL, WNRCG_Fields::STORAGE_DISPLAY_NAME ), true ) ) {
				continue;
			}

			if ( $storage === WNRCG_Fields::STORAGE_COMMS_OPT_IN ) {
				update_user_meta( $user_id, 'wnrcg_comms_opt_in', ! empty( $values[ $id ] ) ? 'yes' : 'no' );
				continue;
			}

			if ( $storage === WNRCG_Fields::STORAGE_META ) {
				$val = $values[ $id ] ?? '';
				if ( $field['type'] === WNRCG_Fields::TYPE_CHECKBOX ) {
					$val = ! empty( $values[ $id ] ) ? 'yes' : 'no';
				}
				update_user_meta( $user_id, WNRCG_Fields::meta_key( $id ), is_scalar( $val ) ? (string) $val : '' );
			}
		}

		update_user_meta( $user_id, 'wnrcg_last_unlocked_at', gmdate( 'c' ) );
		if ( $is_new ) {
			update_user_meta( $user_id, 'wnrcg_unlocked_at', gmdate( 'c' ) );
		}
	}

	/**
	 * @param string $email Email.
	 */
	private static function normalize_email( string $email ): string {
		return strtolower( trim( $email ) );
	}

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