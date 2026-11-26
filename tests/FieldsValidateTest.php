<?php
/**
 * Field validation tests (requires WNRCG_Fields).
 *
 * @package WNRCG
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * Field validation tests.
 */
final class FieldsValidateTest extends TestCase {

	public function test_requires_valid_email(): void {
		if ( ! function_exists( 'get_option' ) ) {
			$this->markTestSkipped( 'WordPress stubs missing.' );
		}

		$errors = WNRCG_Fields::validate(
			array(
				'name'  => 'Test User',
				'email' => 'not-an-email',
			)
		);

		$this->assertNotEmpty( $errors );
	}

	public function test_accepts_minimal_valid_submission(): void {
		$errors = WNRCG_Fields::validate(
			array(
				'name'         => 'Test User',
				'email'        => 'user@example.com',
				'comms_opt_in' => false,
			)
		);

		$this->assertSame( array(), $errors );
	}
}