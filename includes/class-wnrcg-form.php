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
	 * Honeypot input name (must remain empty).
	 */
	public const HONEYPOT_NAME = 'wnrcg_hp';

	/**
	 * @var array<string, bool>
	 */
	private static $form_shown = array();

	/**
	 * @param int         $post_id    Post ID.
	 * @param string      $context    entire|block|shortcode.
	 * @param string|null $gate_scope Scope slug when using scoped unlock mode.
	 */
	public static function render_for_context( int $post_id, string $context = 'entire', ?string $gate_scope = null ): string {
		$scope = $gate_scope ?? ( $context === 'entire' ? WNRCG_Scopes::scope_for_post( $post_id ) : WNRCG_Scopes::default_scope() );
		$scope = WNRCG_Scopes::sanitize_scope( $scope );
		$key   = WNRCG_Scopes::form_instance_key( $post_id, $scope );

		if ( ! isset( self::$form_shown[ $key ] ) ) {
			self::$form_shown[ $key ] = true;
			return self::render( $post_id, $scope );
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
	 * @param int    $post_id Post ID.
	 * @param string $scope   Gate scope for this form.
	 */
	public static function render( int $post_id, string $scope = '' ): string {
		if ( $scope === '' ) {
			$scope = WNRCG_Scopes::scope_for_post( $post_id );
		}
		$scope = WNRCG_Scopes::sanitize_scope( $scope );

		$flash  = self::consume_flash( $post_id, $scope );
		$errors = $flash['errors'];
		$values = $flash['values'];
		$has_errors = ! empty( $errors );

		$privacy_url = (string) WNRCG_Settings::get( 'privacy_policy_url', '' );

		ob_start();
		?>
		<div class="wnrcg-gate" id="wnrcg-gate">
			<h2 class="wnrcg-gate__title"><?php echo esc_html( (string) WNRCG_Settings::get( 'form_title', __( 'Unlock content', 'wp-no-registration-content-gate' ) ) ); ?></h2>
			<?php if ( $has_errors ) : ?>
				<div class="wnrcg-gate__errors" id="wnrcg-gate-errors" role="alert" aria-live="polite">
					<ul>
						<?php foreach ( $errors as $message ) : ?>
							<li><?php echo esc_html( $message ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<form
				class="wnrcg-gate__form"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				novalidate
				<?php echo $has_errors ? ' aria-describedby="wnrcg-gate-errors"' : ''; ?>
			>
				<?php wp_nonce_field( 'wnrcg_unlock', 'wnrcg_unlock_nonce' ); ?>
				<input type="hidden" name="action" value="wnrcg_unlock" />
				<input type="hidden" name="wnrcg_post_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
				<input type="hidden" name="wnrcg_redirect_to" value="<?php echo esc_url( get_permalink( $post_id ) ); ?>" />
				<input type="hidden" name="wnrcg_gate_scope" value="<?php echo esc_attr( $scope ); ?>" />

				<div class="wnrcg-hp" aria-hidden="true">
					<label for="wnrcg_hp_field"><?php esc_html_e( 'Website', 'wp-no-registration-content-gate' ); ?></label>
					<input type="text" name="<?php echo esc_attr( self::HONEYPOT_NAME ); ?>" id="wnrcg_hp_field" value="" tabindex="-1" autocomplete="off" />
				</div>

				<?php foreach ( WNRCG_Fields::all() as $field ) : ?>
					<?php self::render_field( $field, $values, $has_errors ); ?>
				<?php endforeach; ?>

				<p class="wnrcg-gate__submit">
					<button type="submit" class="wnrcg-button">
						<?php echo esc_html( (string) WNRCG_Settings::get( 'submit_label', __( 'Unlock', 'wp-no-registration-content-gate' ) ) ); ?>
					</button>
				</p>
				<?php if ( $privacy_url !== '' ) : ?>
					<p class="wnrcg-gate__privacy">
						<a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Privacy policy', 'wp-no-registration-content-gate' ); ?></a>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $field     Field config.
	 * @param array<string, mixed> $values    Prior values.
	 * @param bool                 $has_errors Whether the form has validation errors.
	 */
	private static function render_field( array $field, array $values, bool $has_errors ): void {
		$id       = (string) $field['id'];
		$label    = (string) $field['label'];
		$required = ! empty( $field['required'] );
		$help     = (string) $field['help'];
		$type     = (string) $field['type'];
		$name     = WNRCG_Fields::input_name( $id );
		$value    = $values[ $id ] ?? '';
		$checked  = ! empty( $value );
		$help_id  = $help !== '' ? $name . '-help' : '';
		$invalid  = $has_errors && $required && $type !== WNRCG_Fields::TYPE_CHECKBOX
			&& ( $value === '' || $value === null );

		if ( $type === WNRCG_Fields::TYPE_CHECKBOX ) {
			?>
			<p class="wnrcg-gate__field wnrcg-gate__field--checkbox">
				<label for="<?php echo esc_attr( $name ); ?>">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
				<?php if ( $help !== '' ) : ?>
					<span class="wnrcg-gate__help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></span>
				<?php endif; ?>
			</p>
			<?php
			return;
		}

		$describedby = trim( ( $has_errors ? 'wnrcg-gate-errors ' : '' ) . $help_id );

		?>
		<p class="wnrcg-gate__field">
			<label for="<?php echo esc_attr( $name ); ?>">
				<?php echo esc_html( $label ); ?>
				<?php if ( $required ) : ?>
					<span class="wnrcg-required">*</span>
				<?php endif; ?>
			</label>
			<?php
			$aria = '';
			if ( $invalid ) {
				$aria .= ' aria-invalid="true"';
			}
			if ( $describedby !== '' ) {
				$aria .= ' aria-describedby="' . esc_attr( trim( $describedby ) ) . '"';
			}

			if ( $type === WNRCG_Fields::TYPE_TEXTAREA ) {
				?>
				<textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" class="wnrcg-textarea" rows="4" <?php echo $required ? 'required' : ''; ?><?php echo $aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php
			} elseif ( $type === WNRCG_Fields::TYPE_SELECT ) {
				?>
				<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" class="wnrcg-input" <?php echo $required ? 'required' : ''; ?><?php echo $aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<option value=""></option>
					<?php foreach ( (array) $field['options'] as $option ) : ?>
						<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( (string) $value, (string) $option ); ?>><?php echo esc_html( (string) $option ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
			} else {
				$input_type   = $type === WNRCG_Fields::TYPE_EMAIL ? 'email' : 'text';
				$autocomplete = $type === WNRCG_Fields::TYPE_EMAIL ? 'email' : ( $id === 'name' ? 'name' : 'off' );
				?>
				<input
					type="<?php echo esc_attr( $input_type ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					id="<?php echo esc_attr( $name ); ?>"
					class="wnrcg-input"
					value="<?php echo esc_attr( (string) $value ); ?>"
					<?php echo $required ? 'required' : ''; ?>
					autocomplete="<?php echo esc_attr( $autocomplete ); ?>"
					<?php echo $aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				/>
				<?php
			}
			if ( $help !== '' ) {
				echo '<span class="wnrcg-gate__help" id="' . esc_attr( $help_id ) . '">' . esc_html( $help ) . '</span>';
			}
			?>
		</p>
		<?php
	}

	public static function flash_key( int $post_id, string $scope = '' ): string {
		$scope = WNRCG_Scopes::sanitize_scope( $scope !== '' ? $scope : WNRCG_Scopes::scope_for_post( $post_id ) );
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'wnrcg_flash_' . md5( $ip . '|' . $post_id . '|' . $scope );
	}

	/**
	 * @param int                    $post_id Post ID.
	 * @param string[]               $errors  Errors.
	 * @param array<string, mixed> $values  Values keyed by field id.
	 */
	public static function set_flash( int $post_id, array $errors, array $values, string $scope = '' ): void {
		set_transient(
			self::flash_key( $post_id, $scope ),
			array(
				'errors' => $errors,
				'values' => $values,
			),
			MINUTE_IN_SECONDS * 15
		);
	}

	/**
	 * Read and delete flash data for this visitor and post.
	 *
	 * @return array{errors: string[], values: array<string, mixed>}
	 */
	private static function consume_flash( int $post_id, string $scope = '' ): array {
		$defaults = array();
		foreach ( WNRCG_Fields::all() as $field ) {
			$id              = (string) $field['id'];
			$defaults[ $id ] = $field['type'] === WNRCG_Fields::TYPE_CHECKBOX ? false : '';
		}

		$flash = get_transient( self::flash_key( $post_id, $scope ) );
		if ( ! is_array( $flash ) ) {
			return array(
				'errors' => array(),
				'values' => $defaults,
			);
		}

		delete_transient( self::flash_key( $post_id, $scope ) );

		$errors = array();
		if ( ! empty( $flash['errors'] ) && is_array( $flash['errors'] ) ) {
			$errors = array_map( 'strval', $flash['errors'] );
		}

		$values = $defaults;
		if ( ! empty( $flash['values'] ) && is_array( $flash['values'] ) ) {
			$values = wp_parse_args( $flash['values'], $defaults );
		}

		return array(
			'errors' => $errors,
			'values' => $values,
		);
	}
}