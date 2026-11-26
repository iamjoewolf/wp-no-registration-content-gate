<?php
/**
 * Prevent full-page cache on gated views when appropriate.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Cache
 */
class WNRCG_Cache {

	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_prevent_cache' ), 0 );
	}

	public static function maybe_prevent_cache(): void {
		if ( is_admin() || ! is_singular( WNRCG_Meta::post_types() ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( $post_id < 1 ) {
			return;
		}

		if ( ! WNRCG_Content_Gate::needs_gate_assets( $post_id ) ) {
			return;
		}

		$default_prevent = (bool) WNRCG_Settings::get( 'prevent_cache', true );

		/**
		 * Whether to define DONOTCACHEPAGE on gated singular views.
		 *
		 * @param bool $prevent  Default from plugin settings.
		 * @param int  $post_id  Queried post ID.
		 */
		$prevent = (bool) apply_filters( 'wnrcg_gate_should_prevent_cache', $default_prevent, $post_id );

		if ( $prevent ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
		}
	}
}