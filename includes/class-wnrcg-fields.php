<?php
/**
 * Configurable gate form fields (stored in wp_options).
 * Answers are stored in wp_users / wp_usermeta.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Fields
 */
class WNRCG_Fields {

	public const OPTION_KEY = 'wnrcg_form_fields';

	public const TYPE_TEXT     = 'text';
	public const TYPE_TEXTAREA = 'textarea';
	public const TYPE_EMAIL    = 'email';
	public const TYPE_CHECKBOX = 'checkbox';
	public const TYPE_SELECT   = 'select';

	public const STORAGE_USER_EMAIL   = 'user_email';
	public const STORAGE_DISPLAY_NAME = 'display_name';
	public const STORAGE_COMMS_OPT_IN = 'comms_opt_in';
	public const STORAGE_META           = 'meta';

	/**
	 * Core field definition shape.
	 *
	 * @return array<string, mixed>
	 */
	private static function field_template(): array {
		return array(
			'id'              => '',
			'type'            => self::TYPE_TEXT,
			'label'           => '',
			'required'        => false,
			'help'            => '',
			'options'         => array(),
			'storage'         => self::STORAGE_META,
			'locked'          => false,
			'show_in_users'   => true,
			'show_in_export'  => true,
			'sort_order'      => 0,
		);
	}

	/**
	 * Default fieldset for new installs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function default_fields(): array {
		return array(
			array(
				'id'             => 'name',
				'type'           => self::TYPE_TEXT,
				'label'          => __( 'Name', 'wp-no-registration-content-gate' ),
				'required'       => true,
				'help'           => '',
				'options'        => array(),
				'storage'        => self::STORAGE_DISPLAY_NAME,
				'locked'         => true,
				'show_in_users'  => false,
				'show_in_export' => true,
				'sort_order'     => 10,
			),
			array(
				'id'             => 'email',
				'type'           => self::TYPE_EMAIL,
				'label'          => __( 'Email address', 'wp-no-registration-content-gate' ),
				'required'       => true,
				'help'           => __( 'Used only to unlock access and remember you without needing a password. We do not use your email for anything else unless you opt in below.', 'wp-no-registration-content-gate' ),
				'options'        => array(),
				'storage'        => self::STORAGE_USER_EMAIL,
				'locked'         => true,
				'show_in_users'  => false,
				'show_in_export' => true,
				'sort_order'     => 20,
			),
			array(
				'id'             => 'comms_opt_in',
				'type'           => self::TYPE_CHECKBOX,
				'label'          => __( 'Keep me updated with occasional news and updates.', 'wp-no-registration-content-gate' ),
				'required'       => false,
				'help'           => '',
				'options'        => array(),
				'storage'        => self::STORAGE_COMMS_OPT_IN,
				'locked'         => true,
				'show_in_users'  => true,
				'show_in_export' => true,
				'sort_order'     => 100,
			),
		);
	}

	/**
	 * Ensure option exists (activation / legacy sites).
	 */
	public static function maybe_seed(): void {
		$current = get_option( self::OPTION_KEY, null );
		if ( $current === null || $current === false || $current === array() ) {
			update_option( self::OPTION_KEY, self::default_fields(), false );
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		self::maybe_seed();
		$fields = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $fields ) ) {
			return self::default_fields();
		}

		$fields = array_map( array( __CLASS__, 'normalize_field' ), $fields );
		usort(
			$fields,
			static function ( array $a, array $b ): int {
				return (int) $a['sort_order'] <=> (int) $b['sort_order'];
			}
		);

		return $fields;
	}

	/**
	 * @param array<string, mixed> $field Raw field.
	 * @return array<string, mixed>
	 */
	public static function normalize_field( array $field ): array {
		$field = wp_parse_args( $field, self::field_template() );
		$field['id'] = sanitize_key( (string) $field['id'] );
		return $field;
	}

	/**
	 * HTML input name for a field.
	 */
	public static function input_name( string $field_id ): string {
		return 'wnrcg_field_' . sanitize_key( $field_id );
	}

	/**
	 * usermeta key for storage=meta fields.
	 */
	public static function meta_key( string $field_id ): string {
		return 'wnrcg_' . sanitize_key( $field_id );
	}

	/**
	 * Read submitted values from POST.
	 *
	 * @return array<string, mixed>
	 */
	public static function parse_submission(): array {
		$parsed = array();
		foreach ( self::all() as $field ) {
			$id   = (string) $field['id'];
			$name = self::input_name( $id );

			if ( $field['type'] === self::TYPE_CHECKBOX ) {
				$parsed[ $id ] = isset( $_POST[ $name ] );
				continue;
			}

			if ( ! isset( $_POST[ $name ] ) ) {
				$parsed[ $id ] = '';
				continue;
			}

			$raw = wp_unslash( (string) $_POST[ $name ] );
			if ( $field['type'] === self::TYPE_TEXTAREA ) {
				$parsed[ $id ] = sanitize_textarea_field( $raw );
			} elseif ( $field['type'] === self::TYPE_EMAIL ) {
				$parsed[ $id ] = sanitize_email( $raw );
			} else {
				$parsed[ $id ] = sanitize_text_field( $raw );
			}
		}

		return $parsed;
	}

	/**
	 * @param array<string, mixed> $values Parsed submission.
	 * @return string[]
	 */
	public static function validate( array $values ): array {
		$errors = array();

		foreach ( self::all() as $field ) {
			$id    = (string) $field['id'];
			$label = (string) $field['label'];
			$value = $values[ $id ] ?? '';

			if ( ! empty( $field['required'] ) ) {
				if ( $field['type'] === self::TYPE_CHECKBOX ) {
					continue;
				}
				if ( $value === '' || $value === null ) {
					/* translators: %s: field label */
					$errors[] = sprintf( __( 'Please complete: %s', 'wp-no-registration-content-gate' ), $label );
					continue;
				}
			}

			if ( $field['type'] === self::TYPE_EMAIL && $value !== '' && ! is_email( (string) $value ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( 'Please enter a valid email for: %s', 'wp-no-registration-content-gate' ), $label );
			}

			if ( $field['type'] === self::TYPE_SELECT && $value !== '' && ! empty( $field['options'] ) ) {
				$allowed = array_map( 'strval', (array) $field['options'] );
				if ( ! in_array( (string) $value, $allowed, true ) ) {
					$errors[] = sprintf( __( 'Please choose a valid option for: %s', 'wp-no-registration-content-gate' ), $label );
				}
			}
		}

		$email_field = self::get_field_by_storage( self::STORAGE_USER_EMAIL );
		if ( $email_field ) {
			$eid = (string) $email_field['id'];
			if ( ( $values[ $eid ] ?? '' ) === '' || ! is_email( (string) ( $values[ $eid ] ?? '' ) ) ) {
				$errors[] = __( 'A valid email address is required.', 'wp-no-registration-content-gate' );
			}
		}

		return array_values( array_unique( $errors ) );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_field_by_storage( string $storage ): ?array {
		foreach ( self::all() as $field ) {
			if ( (string) $field['storage'] === $storage ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_columns(): array {
		return array_values(
			array_filter(
				self::all(),
				static function ( array $field ): bool {
					return ! empty( $field['show_in_users'] ) && (string) $field['storage'] === self::STORAGE_META
						|| (string) $field['storage'] === self::STORAGE_COMMS_OPT_IN;
				}
			)
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function export_columns(): array {
		return array_values(
			array_filter(
				self::all(),
				static function ( array $field ): bool {
					return ! empty( $field['show_in_export'] );
				}
			)
		);
	}

	/**
	 * Display stored value for a user.
	 *
	 * @param int                $user_id User ID.
	 * @param array<string,mixed> $field  Field config.
	 */
	public static function value_for_user( int $user_id, array $field ): string {
		$storage = (string) $field['storage'];
		$id      = (string) $field['id'];

		switch ( $storage ) {
			case self::STORAGE_USER_EMAIL:
				$user = get_user_by( 'id', $user_id );
				return $user ? (string) $user->user_email : '';
			case self::STORAGE_DISPLAY_NAME:
				$user = get_user_by( 'id', $user_id );
				return $user ? (string) $user->display_name : '';
			case self::STORAGE_COMMS_OPT_IN:
				$opt = (string) get_user_meta( $user_id, 'wnrcg_comms_opt_in', true );
				return $opt === 'yes' ? __( 'Yes', 'wp-no-registration-content-gate' ) : __( 'No', 'wp-no-registration-content-gate' );
			case self::STORAGE_META:
			default:
				return (string) get_user_meta( $user_id, self::meta_key( $id ), true );
		}
	}

	/**
	 * @param array<int, array<string, mixed>>|mixed $input Submitted fields from admin.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_field_set( $input ): array {
		if ( ! is_array( $input ) ) {
			return self::default_fields();
		}

		$clean   = array();
		$existing = self::all();
		$locked   = array();
		foreach ( $existing as $field ) {
			if ( ! empty( $field['locked'] ) ) {
				$locked[ (string) $field['id'] ] = $field;
			}
		}

		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( ! empty( $row['delete'] ) ) {
				continue;
			}

			$field = wp_parse_args( $row, self::field_template() );
			$field['id'] = sanitize_key( (string) $field['id'] );
			if ( $field['id'] === '' ) {
				continue;
			}

			$field['type'] = in_array(
				$field['type'],
				array( self::TYPE_TEXT, self::TYPE_TEXTAREA, self::TYPE_EMAIL, self::TYPE_CHECKBOX, self::TYPE_SELECT ),
				true
			) ? $field['type'] : self::TYPE_TEXT;

			$field['label']          = sanitize_text_field( (string) $field['label'] );
			$field['help']           = sanitize_textarea_field( (string) $field['help'] );
			$field['required']       = ! empty( $field['required'] );
			$field['show_in_users']  = ! empty( $field['show_in_users'] );
			$field['show_in_export'] = ! empty( $field['show_in_export'] );
			$field['sort_order']     = (int) $field['sort_order'];

			$allowed_storage = array( self::STORAGE_META, self::STORAGE_DISPLAY_NAME, self::STORAGE_COMMS_OPT_IN );
			if ( ! empty( $locked[ $field['id'] ] ) ) {
				$field = wp_parse_args( $field, $locked[ $field['id'] ] );
				$field['locked'] = true;
			} else {
				$field['storage'] = in_array( (string) $field['storage'], $allowed_storage, true )
					? (string) $field['storage']
					: self::STORAGE_META;
				$field['locked'] = false;
			}

			if ( $field['type'] === self::TYPE_SELECT && ! empty( $row['options'] ) && is_string( $row['options'] ) ) {
				$lines           = preg_split( '/\r\n|\r|\n/', $row['options'] ) ?: array();
				$field['options'] = array_values( array_filter( array_map( 'sanitize_text_field', $lines ) ) );
			} else {
				$field['options'] = array();
			}

			$clean[] = $field;
		}

		foreach ( $locked as $id => $field ) {
			$found = false;
			foreach ( $clean as $row ) {
				if ( (string) $row['id'] === $id ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$clean[] = $field;
			}
		}

		usort(
			$clean,
			static function ( array $a, array $b ): int {
				return (int) $a['sort_order'] <=> (int) $b['sort_order'];
			}
		);

		return $clean;
	}
}