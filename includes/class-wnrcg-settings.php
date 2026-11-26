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
			'form_title'            => __( 'Unlock content', 'wp-no-registration-content-gate' ),
			'submit_label'          => __( 'Unlock', 'wp-no-registration-content-gate' ),
			'cookie_days'           => 365,
			'update_display_name'   => false,
			'cookie_slide_refresh'  => true,
			'prevent_cache'         => true,
			'privacy_policy_url'    => '',
			'unlock_mode'           => WNRCG_Scopes::MODE_SITE,
			'default_gate_scope'    => WNRCG_Scopes::SCOPE_ALL,
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

		$clean['form_title']   = isset( $input['form_title'] ) ? sanitize_text_field( (string) $input['form_title'] ) : $clean['form_title'];
		$clean['submit_label'] = isset( $input['submit_label'] ) ? sanitize_text_field( (string) $input['submit_label'] ) : $clean['submit_label'];
		$clean['cookie_days']  = isset( $input['cookie_days'] ) ? absint( $input['cookie_days'] ) : $clean['cookie_days'];
		$clean['update_display_name']   = ! empty( $input['update_display_name'] );
		$clean['cookie_slide_refresh']    = ! empty( $input['cookie_slide_refresh'] );
		$clean['prevent_cache']           = ! empty( $input['prevent_cache'] );
		$clean['privacy_policy_url']      = isset( $input['privacy_policy_url'] )
			? esc_url_raw( (string) $input['privacy_policy_url'] )
			: '';
		$mode = isset( $input['unlock_mode'] ) ? sanitize_key( (string) $input['unlock_mode'] ) : WNRCG_Scopes::MODE_SITE;
		$clean['unlock_mode']           = $mode === WNRCG_Scopes::MODE_SCOPED ? WNRCG_Scopes::MODE_SCOPED : WNRCG_Scopes::MODE_SITE;
		$clean['default_gate_scope']      = isset( $input['default_gate_scope'] )
			? WNRCG_Scopes::sanitize_scope( sanitize_text_field( (string) $input['default_gate_scope'] ) )
			: WNRCG_Scopes::SCOPE_ALL;

		return $clean;
	}
}