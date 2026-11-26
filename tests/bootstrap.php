<?php
/**
 * Minimal stubs for unit tests without a full WordPress stack.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/includes/class-wnrcg-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-wnrcg-cookie.php';

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = '' ): string {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( string $email ): string {
		return filter_var( $email, FILTER_SANITIZE_EMAIL ) ?: '';
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return sanitize_text_field( $str );
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, $value, $autoload = true ): bool {
		unset( $key, $value, $autoload );
		return true;
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( string $email ): bool {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'WNRCG_COOKIE_NAME' ) ) {
	define( 'WNRCG_COOKIE_NAME', 'wnrcg_unlocked_token' );
}

if ( ! defined( 'COOKIEPATH' ) ) {
	define( 'COOKIEPATH', '/' );
}

if ( ! defined( 'COOKIE_DOMAIN' ) ) {
	define( 'COOKIE_DOMAIN', '' );
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( string $scheme ): string {
		unset( $scheme );
		return 'unit-test-salt';
	}
}

if ( ! function_exists( 'get_user_by' ) ) {
	function get_user_by( string $field, int $user_id ) {
		unset( $field );
		if ( $user_id === 42 ) {
			return (object) array( 'ID' => 42 );
		}
		return false;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		if ( $key === 'wnrcg_form_fields' ) {
			require_once dirname( __DIR__ ) . '/includes/class-wnrcg-fields.php';
			return WNRCG_Fields::default_fields();
		}
		return $default;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-wnrcg-fields.php';