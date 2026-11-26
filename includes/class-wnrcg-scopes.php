<?php
/**
 * Gate scopes: site-wide unlock vs per-group unlock.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Scopes
 */
class WNRCG_Scopes {

	public const MODE_SITE   = 'site';
	public const MODE_SCOPED = 'scoped';

	public const SCOPE_ALL = 'all';

	public const USER_META_SCOPES = 'wnrcg_unlocked_scopes';

	/**
	 * @return string site|scoped
	 */
	public static function unlock_mode(): string {
		$mode = (string) WNRCG_Settings::get( 'unlock_mode', self::MODE_SITE );
		return $mode === self::MODE_SCOPED ? self::MODE_SCOPED : self::MODE_SITE;
	}

	public static function is_site_mode(): bool {
		return self::unlock_mode() !== self::MODE_SCOPED;
	}

	/**
	 * Sanitize a scope slug.
	 */
	public static function sanitize_scope( string $scope ): string {
		$scope = sanitize_title( $scope );
		if ( $scope === '' ) {
			$scope = (string) WNRCG_Settings::get( 'default_gate_scope', self::SCOPE_ALL );
		}
		if ( $scope === '' ) {
			$scope = self::SCOPE_ALL;
		}
		return $scope;
	}

	/**
	 * Default scope when a post/block does not specify one.
	 */
	public static function default_scope(): string {
		return self::sanitize_scope( (string) WNRCG_Settings::get( 'default_gate_scope', self::SCOPE_ALL ) );
	}

	/**
	 * Scope required for a fully protected post.
	 */
	public static function scope_for_post( int $post_id ): string {
		$raw = (string) get_post_meta( $post_id, WNRCG_Meta::SCOPE_META_KEY, true );
		if ( $raw !== '' ) {
			return self::sanitize_scope( $raw );
		}
		return self::default_scope();
	}

	/**
	 * Scope from unlock POST (falls back to post default).
	 */
	public static function scope_from_post_request( int $post_id ): string {
		if ( isset( $_POST['wnrcg_gate_scope'] ) ) {
			return self::sanitize_scope( sanitize_text_field( wp_unslash( (string) $_POST['wnrcg_gate_scope'] ) ) );
		}
		return self::scope_for_post( $post_id );
	}

	/**
	 * Unique key for one form instance per post + scope.
	 */
	public static function form_instance_key( int $post_id, string $scope ): string {
		return $post_id . '|' . self::sanitize_scope( $scope );
	}

	/**
	 * Whether the visitor may view content for this post/scope.
	 *
	 * @param int         $post_id Post ID.
	 * @param string|null $scope   Block/shortcode scope; null = entire-page scope for post.
	 */
	public static function access_granted( int $post_id, ?string $scope = null ): bool {
		if ( is_user_logged_in() ) {
			return true;
		}

		$user_id = WNRCG_Cookie::current_user_id();
		if ( $user_id < 1 ) {
			return false;
		}

		if ( self::is_site_mode() ) {
			return true;
		}

		$required = $scope !== null && $scope !== ''
			? self::sanitize_scope( $scope )
			: self::scope_for_post( $post_id );

		return self::user_has_scope( $user_id, $required );
	}

	/**
	 * @return string[]
	 */
	public static function get_user_scopes( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::USER_META_SCOPES, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_unique( array_map( array( __CLASS__, 'sanitize_scope' ), $raw ) ) );
	}

	public static function user_has_scope( int $user_id, string $scope ): bool {
		$scope = self::sanitize_scope( $scope );
		$scopes = self::get_user_scopes( $user_id );

		if ( in_array( self::SCOPE_ALL, $scopes, true ) ) {
			return true;
		}

		return in_array( $scope, $scopes, true );
	}

	/**
	 * Record that this user unlocked a scope (scoped mode only).
	 */
	public static function grant_user_scope( int $user_id, string $scope ): void {
		if ( self::is_site_mode() ) {
			return;
		}

		$scope  = self::sanitize_scope( $scope );
		$scopes = self::get_user_scopes( $user_id );

		if ( ! in_array( $scope, $scopes, true ) ) {
			$scopes[] = $scope;
			update_user_meta( $user_id, self::USER_META_SCOPES, $scopes );
		}
	}
}