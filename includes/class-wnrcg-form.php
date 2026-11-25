<?php
/**
 * Unlock form markup.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Form
 */
class WNRCG_Form {

	/**
	 * Track per-post form output (one form per page load).
	 *
	 * @var array<int, bool>
	 */
	private static $form_shown = array();

	/**
	 * Render form once per page; further gated blocks show a short notice.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $context entire|block|shortcode.
	 */
	public static function render_for_context( int $post_id, string $context = 'entire' ): string {
		if ( ! isset( self::$form_shown[ $post_id ] ) ) {
			self::$form_shown[ $post_id ] = true;
			return self::render( $post_id );
		}

		if ( $context === 'block' || $context === 'shortcode' ) {
			return '<p class="wnrcg-gated-section-placeholder">' . esc_html__(
				'Complete the unlock form above to view this section.',
				'wp-no-registration-content-gate'
			) . '</p>';
		}

		return '';
	}

	/**
	 * Render the unlock form for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function render( int $post_id ): string {
		$values = self::values_for_post( $post_id );
		$errors = self::errors_for_post( $post_id );

		ob_start();
		?>
		<div class="wnrcg-gate" id="wnrcg-gate">
			<h2 class="wnrcg-gate__title"><?php echo esc_html( (string) WNRCG_Settings::get( 'form_title', __( 'Unlock poems', 'wp-no-registration-content-gate' ) ) ); ?></h2>
			<?php if ( ! empty( $errors ) ) : ?>
				<div class="wnrcg-gate__errors" role="alert">
					<ul>
						<?php foreach ( $errors as $message ) : ?>
							<li><?php echo esc_html( $message ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<form class="wnrcg-gate__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
				<?php wp_nonce_field( 'wnrcg_unlock', 'wnrcg_unlock_nonce' ); ?>
				<input type="hidden" name="action" value="wnrcg_unlock" />
				<input type="hidden" name="wnrcg_post_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
				<input type="hidden" name="wnrcg_redirect_to" value="<?php echo esc_url( get_permalink( $post_id ) ); ?>" />

				<p class="wnrcg-gate__field">
					<label for="wnrcg_name"><?php esc_html_e( 'Name', 'wp-no-registration-content-gate' ); ?> <span class="wnrcg-required">*</span></label>
					<input type="text" name="wnrcg_name" id="wnrcg_name" class="wnrcg-input" required autocomplete="name"
						value="<?php echo esc_attr( $values['name'] ); ?>" />
				</p>

				<p class="wnrcg-gate__field">
					<label for="wnrcg_job_title"><?php esc_html_e( 'Job title', 'wp-no-registration-content-gate' ); ?> <span class="wnrcg-required">*</span></label>
					<input type="text" name="wnrcg_job_title" id="wnrcg_job_title" class="wnrcg-input" required
						value="<?php echo esc_attr( $values['job_title'] ); ?>" />
				</p>

				<p class="wnrcg-gate__field">
					<label for="wnrcg_poem_usage"><?php esc_html_e( 'How will you use the poems?', 'wp-no-registration-content-gate' ); ?> <span class="wnrcg-required">*</span></label>
					<textarea name="wnrcg_poem_usage" id="wnrcg_poem_usage" class="wnrcg-textarea" rows="4" required><?php echo esc_textarea( $values['poem_usage'] ); ?></textarea>
				</p>

				<p class="wnrcg-gate__field">
					<label for="wnrcg_email"><?php esc_html_e( 'Email address', 'wp-no-registration-content-gate' ); ?> <span class="wnrcg-required">*</span></label>
					<input type="email" name="wnrcg_email" id="wnrcg_email" class="wnrcg-input" required autocomplete="email"
						value="<?php echo esc_attr( $values['email'] ); ?>" />
					<span class="wnrcg-gate__help">
						<?php echo esc_html( (string) WNRCG_Settings::get( 'email_help', '' ) ); ?>
					</span>
				</p>

				<p class="wnrcg-gate__field wnrcg-gate__field--checkbox">
					<label for="wnrcg_comms_opt_in">
						<input type="checkbox" name="wnrcg_comms_opt_in" id="wnrcg_comms_opt_in" value="1" <?php checked( $values['comms_opt_in'] ); ?> />
						<?php echo esc_html( (string) WNRCG_Settings::get( 'comms_opt_in_label', '' ) ); ?>
					</label>
				</p>

				<p class="wnrcg-gate__submit">
					<button type="submit" class="wnrcg-button">
						<?php echo esc_html( (string) WNRCG_Settings::get( 'submit_label', __( 'Unlock poems', 'wp-no-registration-content-gate' ) ) ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Transient key for form repopulation / errors.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function flash_key( int $post_id ): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'wnrcg_flash_' . md5( $ip . '|' . $post_id );
	}

	/**
	 * Store errors and values for next render.
	 *
	 * @param int                    $post_id Post ID.
	 * @param string[]               $errors  Error messages.
	 * @param array<string, string|bool> $values Field values.
	 */
	public static function set_flash( int $post_id, array $errors, array $values ): void {
		set_transient(
			self::flash_key( $post_id ),
			array(
				'errors' => $errors,
				'values' => $values,
			),
			MINUTE_IN_SECONDS * 15
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private static function errors_for_post( int $post_id ): array {
		$flash = get_transient( self::flash_key( $post_id ) );
		if ( ! is_array( $flash ) || empty( $flash['errors'] ) ) {
			return array();
		}
		delete_transient( self::flash_key( $post_id ) );
		return array_map( 'strval', $flash['errors'] );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array{name: string, job_title: string, poem_usage: string, email: string, comms_opt_in: bool}
	 */
	private static function values_for_post( int $post_id ): array {
		$defaults = array(
			'name'           => '',
			'job_title'      => '',
			'poem_usage'     => '',
			'email'          => '',
			'comms_opt_in'   => false,
		);

		$flash = get_transient( self::flash_key( $post_id ) );
		if ( is_array( $flash ) && ! empty( $flash['values'] ) && is_array( $flash['values'] ) ) {
			$values = wp_parse_args( $flash['values'], $defaults );
			delete_transient( self::flash_key( $post_id ) );
			return array(
				'name'         => (string) $values['name'],
				'job_title'    => (string) $values['job_title'],
				'poem_usage'   => (string) $values['poem_usage'],
				'email'        => (string) $values['email'],
				'comms_opt_in' => ! empty( $values['comms_opt_in'] ),
			);
		}

		return $defaults;
	}
}