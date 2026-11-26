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

		$post_id = isset( $_POST['wnrcg_post_id'] ) ? (int) $_POST['wnrcg_post_id'] : 0;

		$redirect_fallback = $post_id > 0 ? (string) get_permalink( $post_id ) : home_url( '/' );
		$redirect_raw      = isset( $_POST['wnrcg_redirect_to'] )
			? esc_url_raw( wp_unslash( (string) $_POST['wnrcg_redirect_to'] ) )
			: '';
		$redirect          = wp_validate_redirect( $redirect_raw, $redirect_fallback );

		// Honeypot: bots that fill hidden fields get a benign redirect.
		if ( isset( $_POST['wnrcg_hp'] ) && (string) $_POST['wnrcg_hp'] !== '' ) {
			wp_safe_redirect( $redirect );
			exit;
		}

		$has_gate = $post_id > 0 && (
			WNRCG_Meta::is_protected( $post_id )
			|| WNRCG_Content_Gate::post_has_gated_sections( $post_id )
		);

		if ( ! $has_gate ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$gate_scope = WNRCG_Scopes::scope_from_post_request( $post_id );

		if ( WNRCG_Rate_Limit::is_limited() ) {
			WNRCG_Form::set_flash(
				$post_id,
				array( __( 'Too many unlock attempts. Please wait a few minutes and try again.', 'wp-no-registration-content-gate' ) ),
				array(),
				$gate_scope
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		WNRCG_Rate_Limit::record_attempt();

		$raw_values = WNRCG_Fields::parse_submission();
		$errors     = WNRCG_Fields::validate( $raw_values );

		if ( ! empty( $errors ) ) {
			WNRCG_Form::set_flash( $post_id, $errors, $raw_values, $gate_scope );
			wp_safe_redirect( $redirect );
			exit;
		}

		$result = WNRCG_User_Service::upsert_from_unlock( $raw_values );

		if ( is_wp_error( $result ) ) {
			WNRCG_Form::set_flash( $post_id, array( $result->get_error_message() ), $raw_values, $gate_scope );
			wp_safe_redirect( $redirect );
			exit;
		}

		$user_id = (int) $result;

		WNRCG_Cookie::set_for_user( $user_id );
		WNRCG_Scopes::grant_user_scope( $user_id, $gate_scope );

		/**
		 * Fires after a successful unlock.
		 *
		 * @param int    $user_id User ID (one row per email; scopes differ in scoped mode).
		 * @param int    $post_id Post ID.
		 * @param string $scope   Gate scope unlocked.
		 */
		do_action( 'wnrcg_unlocked', $user_id, $post_id, $gate_scope );

		wp_safe_redirect( $redirect );
		exit;
	}
}