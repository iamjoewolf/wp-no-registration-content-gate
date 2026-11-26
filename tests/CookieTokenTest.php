<?php
/**
 * Cookie token build/verify tests.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * Cookie token tests.
 */
final class CookieTokenTest extends TestCase {

	public function test_build_and_verify_valid_token(): void {
		$expires = time() + DAY_IN_SECONDS;
		$token   = WNRCG_Cookie::build_token( 42, $expires );

		$this->assertSame( 42, WNRCG_Cookie::verify_token( $token ) );
	}

	public function test_rejects_expired_token(): void {
		$expires = time() - 10;
		$token   = WNRCG_Cookie::build_token( 42, $expires );

		$this->assertSame( 0, WNRCG_Cookie::verify_token( $token ) );
	}

	public function test_rejects_tampered_mac(): void {
		$expires = time() + DAY_IN_SECONDS;
		$token   = WNRCG_Cookie::build_token( 42, $expires );
		$token   = preg_replace( '/[a-f]$/', '0', $token );

		$this->assertSame( 0, WNRCG_Cookie::verify_token( (string) $token ) );
	}

	public function test_rejects_unknown_user(): void {
		$expires = time() + DAY_IN_SECONDS;
		$token   = WNRCG_Cookie::build_token( 999, $expires );

		$this->assertSame( 0, WNRCG_Cookie::verify_token( $token ) );
	}
}