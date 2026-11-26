<?php
/**
 * Signed unlock cookie.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Cookie
 */
class WNRCG_Cookie {

	/**
	 * Whether cookie expiry was slid this request.
	 *
	 * @var bool
	 */
	private static $slide_done = false;

	/**
	 * Cookie name.
	 */
	public static function name(): string {
		return WNRCG_COOKIE_NAME;
	}

	/**
	 * Issue cookie for a user ID.
	 *
	 * @param int $user_id User ID.
	 */
	public static function set_for_user( int $user_id ): void {
		$expires = time() + ( DAY_IN_SECONDS * WNRCG_Settings::cookie_days() );
		$token   = self::build_token( $user_id, $expires );
		$path    = COOKIEPATH ? COOKIEPATH : '/';

		setcookie(
			self::name(),
			$token,
			array(
				'expires'  => $expires,
				'path'     => $path,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ self::name() ] = $token;
	}

	/**
	 * Build HMAC-signed token.
	 *
	 * @param int $user_id User ID.
	 * @param int $expires Unix timestamp.
	 */
	public static function build_token( int $user_id, int $expires ): string {
		$payload = $user_id . '|' . $expires;
		$mac     = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

		return $user_id . '|' . $expires . '|' . $mac;
	}

	/**
	 * Parse and verify cookie value; return user ID or 0.
	 *
	 * @param string $raw Raw cookie.
	 */
	public static function verify_token( string $raw ): int {
		$parts = explode( '|', $raw, 3 );
		if ( count( $parts ) !== 3 ) {
			return 0;
		}

		$user_id = (int) $parts[0];
		$expires = (int) $parts[1];
		$mac     = $parts[2];

		if ( $user_id < 1 || $expires < time() ) {
			return 0;
		}

		$expected = hash_hmac( 'sha256', $user_id . '|' . $expires, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected, $mac ) ) {
			return 0;
		}

		if ( ! get_user_by( 'id', $user_id ) ) {
			return 0;
		}

		if (
			! self::$slide_done
			&& (bool) WNRCG_Settings::get( 'cookie_slide_refresh', true )
		) {
			self::$slide_done = true;
			self::set_for_user( $user_id );
		}

		return $user_id;
	}

	/**
	 * Whether the current request has a valid unlock cookie.
	 */
	public static function is_valid_for_request(): bool {
		return self::current_user_id() > 0;
	}

	/**
	 * User ID from cookie, if valid.
	 */
	public static function current_user_id(): int {
		if ( ! isset( $_COOKIE[ self::name() ] ) ) {
			return 0;
		}

		$raw = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::name() ] ) );
		if ( $raw === '' ) {
			return 0;
		}

		return self::verify_token( $raw );
	}
}