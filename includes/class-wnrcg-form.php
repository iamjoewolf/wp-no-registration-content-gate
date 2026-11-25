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
	 * @var array<int, bool>
	 */
	private static $form_shown = array();

	/**
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
	 * @param int $post_id Post ID.
	 */
	public static function render( int $post_id ): string {
		$values = self::values_for_post( $post_id );
		$errors = self::errors_for_post( $post_id );

		ob_start();
		?>
		<div class="wnrcg-gate" id="wnrcg-gate">
			<h2 class="wnrcg-gate__title"><?php echo esc_html( (string) WNRCG_Settings::get( 'form_title', __( 'Unlock content', 'wp-no-registration-content-gate' ) ) ); ?></h2>
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

				<?php foreach ( WNRCG_Fields::all() as $field ) : ?>
					<?php self::render_field( $field, $values ); ?>
				<?php endforeach; ?>

				<p class="wnrcg-gate__submit">
					<button type="submit" class="wnrcg-button">
						<?php echo esc_html( (string) WNRCG_Settings::get( 'submit_label', __( 'Unlock', 'wp-no-registration-content-gate' ) ) ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $field  Field config.
	 * @param array<string, mixed> $values Prior values.
	 */
	private static function render_field( array $field, array $values ): void {
		$id       = (string) $field['id'];
		$label    = (string) $field['label'];
		$required = ! empty( $field['required'] );
		$help     = (string) $field['help'];
		$type     = (string) $field['type'];
		$name     = WNRCG_Fields::input_name( $id );
		$value    = $values[ $id ] ?? '';
		$checked  = ! empty( $value );

		if ( $type === WNRCG_Fields::TYPE_CHECKBOX ) {
			?>
			<p class="wnrcg-gate__field wnrcg-gate__field--checkbox">
				<label for="<?php echo esc_attr( $name ); ?>">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
				<?php if ( $help !== '' ) : ?>
					<span class="wnrcg-gate__help"><?php echo esc_html( $help ); ?></span>
				<?php endif; ?>
			</p>
			<?php
			return;
		}

		?>
		<p class="wnrcg-gate__field">
			<label for="<?php echo esc_attr( $name ); ?>">
				<?php echo esc_html( $label ); ?>
				<?php if ( $required ) : ?>
					<span class="wnrcg-required">*</span>
				<?php endif; ?>
			</label>
			<?php
			if ( $type === WNRCG_Fields::TYPE_TEXTAREA ) {
				?>
				<textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" class="wnrcg-textarea" rows="4" <?php echo $required ? 'required' : ''; ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php
			} elseif ( $type === WNRCG_Fields::TYPE_SELECT ) {
				?>
				<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" class="wnrcg-input" <?php echo $required ? 'required' : ''; ?>>
					<option value=""></option>
					<?php foreach ( (array) $field['options'] as $option ) : ?>
						<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( (string) $value, (string) $option ); ?>><?php echo esc_html( (string) $option ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
			} else {
				$input_type = $type === WNRCG_Fields::TYPE_EMAIL ? 'email' : 'text';
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
				/>
				<?php
			}
			if ( $help !== '' ) {
				echo '<span class="wnrcg-gate__help">' . esc_html( $help ) . '</span>';
			}
			?>
		</p>
		<?php
	}

	public static function flash_key( int $post_id ): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'wnrcg_flash_' . md5( $ip . '|' . $post_id );
	}

	/**
	 * @param int                    $post_id Post ID.
	 * @param string[]               $errors  Errors.
	 * @param array<string, mixed> $values  Values keyed by field id.
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
	 * @return array<string, mixed>
	 */
	private static function values_for_post( int $post_id ): array {
		$defaults = array();
		foreach ( WNRCG_Fields::all() as $field ) {
			$id = (string) $field['id'];
			$defaults[ $id ] = $field['type'] === WNRCG_Fields::TYPE_CHECKBOX ? false : '';
		}

		$flash = get_transient( self::flash_key( $post_id ) );
		if ( is_array( $flash ) && ! empty( $flash['values'] ) && is_array( $flash['values'] ) ) {
			delete_transient( self::flash_key( $post_id ) );
			return wp_parse_args( $flash['values'], $defaults );
		}

		return $defaults;
	}
}