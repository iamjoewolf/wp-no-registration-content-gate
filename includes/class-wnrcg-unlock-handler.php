<?php
/**
 * Handle unlock form POST.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Unlock_Handler
 */
class WNRCG_Unlock_Handler {

	public static function init(): void {
		add_action( 'admin_post_nopriv_wnrcg_unlock', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_wnrcg_unlock', array( __CLASS__, 'handle' ) );
	}

	public static function handle(): void {
		if ( ! isset( $_POST['wnrcg_unlock_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wnrcg_unlock_nonce'] ) ), 'wnrcg_unlock' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-no-registration-content-gate' ), 403 );
		}

		$post_id  = isset( $_POST['wnrcg_post_id'] ) ? (int) $_POST['wnrcg_post_id'] : 0;
		$redirect = isset( $_POST['wnrcg_redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['wnrcg_redirect_to'] ) ) : '';

		$has_gate = $post_id > 0 && (
			WNRCG_Meta::is_protected( $post_id )
			|| WNRCG_Content_Gate::post_has_gated_sections( $post_id )
		);

		if ( ! $has_gate ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( $redirect === '' ) {
			$redirect = (string) get_permalink( $post_id );
		}

		$raw_values = WNRCG_Fields::parse_submission();
		$errors     = WNRCG_Fields::validate( $raw_values );

		if ( ! empty( $errors ) ) {
			WNRCG_Form::set_flash( $post_id, $errors, $raw_values );
			wp_safe_redirect( $redirect );
			exit;
		}

		$result = WNRCG_User_Service::upsert_from_unlock( $raw_values );

		if ( is_wp_error( $result ) ) {
			WNRCG_Form::set_flash( $post_id, array( $result->get_error_message() ), $raw_values );
			wp_safe_redirect( $redirect );
			exit;
		}

		WNRCG_Cookie::set_for_user( (int) $result );

		do_action( 'wnrcg_unlocked', (int) $result, $post_id );

		wp_safe_redirect( $redirect );
		exit;
	}
}