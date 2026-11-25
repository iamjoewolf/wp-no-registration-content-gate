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

	/**
	 * Register handlers.
	 */
	public static function init(): void {
		add_action( 'admin_post_nopriv_wnrcg_unlock', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_wnrcg_unlock', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Process unlock submission.
	 */
	public static function handle(): void {
		if ( ! isset( $_POST['wnrcg_unlock_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wnrcg_unlock_nonce'] ) ), 'wnrcg_unlock' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-no-registration-content-gate' ), 403 );
		}

		$post_id = isset( $_POST['wnrcg_post_id'] ) ? (int) $_POST['wnrcg_post_id'] : 0;
		$redirect = isset( $_POST['wnrcg_redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['wnrcg_redirect_to'] ) ) : '';

		if ( $post_id < 1 || ! WNRCG_Meta::is_protected( $post_id ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( $redirect === '' ) {
			$redirect = (string) get_permalink( $post_id );
		}

		$raw_values = array(
			'name'           => isset( $_POST['wnrcg_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['wnrcg_name'] ) ) : '',
			'job_title'      => isset( $_POST['wnrcg_job_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['wnrcg_job_title'] ) ) : '',
			'poem_usage'     => isset( $_POST['wnrcg_poem_usage'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['wnrcg_poem_usage'] ) ) : '',
			'email'          => isset( $_POST['wnrcg_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['wnrcg_email'] ) ) : '',
			'comms_opt_in'   => isset( $_POST['wnrcg_comms_opt_in'] ),
		);

		$errors = self::validate( $raw_values );

		if ( ! empty( $errors ) ) {
			WNRCG_Form::set_flash( $post_id, $errors, $raw_values );
			wp_safe_redirect( $redirect );
			exit;
		}

		$result = WNRCG_User_Service::upsert_from_unlock(
			$raw_values['name'],
			$raw_values['job_title'],
			$raw_values['poem_usage'],
			$raw_values['email'],
			(bool) $raw_values['comms_opt_in']
		);

		if ( is_wp_error( $result ) ) {
			WNRCG_Form::set_flash( $post_id, array( $result->get_error_message() ), $raw_values );
			wp_safe_redirect( $redirect );
			exit;
		}

		WNRCG_Cookie::set_for_user( (int) $result );

		/**
		 * Fires after a visitor unlocks protected content.
		 *
		 * @param int $user_id User ID.
		 * @param int $post_id Protected post ID.
		 */
		do_action( 'wnrcg_unlocked', (int) $result, $post_id );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @param array<string, mixed> $values Submitted values.
	 * @return string[]
	 */
	private static function validate( array $values ): array {
		$errors = array();

		if ( $values['name'] === '' ) {
			$errors[] = __( 'Please enter your name.', 'wp-no-registration-content-gate' );
		}

		if ( $values['job_title'] === '' ) {
			$errors[] = __( 'Please enter your job title.', 'wp-no-registration-content-gate' );
		}

		if ( $values['poem_usage'] === '' ) {
			$errors[] = __( 'Please tell us how you will use the poems.', 'wp-no-registration-content-gate' );
		}

		if ( $values['email'] === '' || ! is_email( $values['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'wp-no-registration-content-gate' );
		}

		return $errors;
	}
}