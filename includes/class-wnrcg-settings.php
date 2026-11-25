<?php
/**
 * Plugin settings (Options API).
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Settings
 */
class WNRCG_Settings {

	public const OPTION_KEY = 'wnrcg_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'form_title'              => __( 'Unlock poems', 'wp-no-registration-content-gate' ),
			'submit_label'            => __( 'Unlock poems', 'wp-no-registration-content-gate' ),
			'comms_opt_in_label'      => __( 'Keep me updated with occasional news and new poem packs.', 'wp-no-registration-content-gate' ),
			'email_help'              => __( 'Used only to unlock access and remember you without needing a password. We do not use your email for anything else unless you opt in below.', 'wp-no-registration-content-gate' ),
			'cookie_days'             => 365,
			'update_display_name'     => false,
		);
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 */
	public static function get( string $key, $default = null ) {
		$options  = get_option( self::OPTION_KEY, array() );
		$defaults = self::defaults();

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}

		if ( array_key_exists( $key, $defaults ) ) {
			return $defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Cookie lifetime in days.
	 */
	public static function cookie_days(): int {
		$days = (int) self::get( 'cookie_days', WNRCG_COOKIE_DAYS );
		if ( $days < 1 ) {
			$days = (int) WNRCG_COOKIE_DAYS;
		}
		if ( $days > 730 ) {
			$days = 730;
		}
		return $days;
	}

	/**
	 * Register settings on admin.
	 */
	public static function register(): void {
		register_setting(
			'wnrcg_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * @param array<string, mixed>|mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			return self::defaults();
		}

		$clean = self::defaults();

		$clean['form_title']         = isset( $input['form_title'] ) ? sanitize_text_field( (string) $input['form_title'] ) : $clean['form_title'];
		$clean['submit_label']       = isset( $input['submit_label'] ) ? sanitize_text_field( (string) $input['submit_label'] ) : $clean['submit_label'];
		$clean['comms_opt_in_label'] = isset( $input['comms_opt_in_label'] ) ? sanitize_text_field( (string) $input['comms_opt_in_label'] ) : $clean['comms_opt_in_label'];
		$clean['email_help']         = isset( $input['email_help'] ) ? sanitize_textarea_field( (string) $input['email_help'] ) : $clean['email_help'];
		$clean['cookie_days']        = isset( $input['cookie_days'] ) ? absint( $input['cookie_days'] ) : $clean['cookie_days'];
		$clean['update_display_name'] = ! empty( $input['update_display_name'] );

		return $clean;
	}
}