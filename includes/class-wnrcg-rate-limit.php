<?php
/**
 * Lightweight unlock rate limiting (per IP).
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Rate_Limit
 */
class WNRCG_Rate_Limit {

	/**
	 * Transient key for the current request IP.
	 */
	public static function transient_key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: 'unknown';

		return 'wnrcg_rate_' . md5( $ip );
	}

	/**
	 * Max attempts within the window.
	 */
	public static function max_attempts(): int {
		$max = (int) apply_filters( 'wnrcg_rate_limit_max_attempts', 10 );
		if ( $max < 1 ) {
			$max = 10;
		}
		return $max;
	}

	/**
	 * Window length in seconds.
	 */
	public static function window_seconds(): int {
		$window = (int) apply_filters( 'wnrcg_rate_limit_window', 15 * MINUTE_IN_SECONDS );
		if ( $window < 60 ) {
			$window = 15 * MINUTE_IN_SECONDS;
		}
		return $window;
	}

	public static function is_limited(): bool {
		$count = (int) get_transient( self::transient_key() );
		return $count >= self::max_attempts();
	}

	public static function record_attempt(): void {
		$key   = self::transient_key();
		$count = (int) get_transient( $key );
		++$count;
		set_transient( $key, $count, self::window_seconds() );
	}
}