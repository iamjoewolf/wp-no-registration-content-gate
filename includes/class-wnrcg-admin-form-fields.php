<?php
/**
 * Admin UI for gate form fields.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Admin_Form_Fields
 */
class WNRCG_Admin_Form_Fields {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Save field set from POST.
	 */
	public static function handle_save(): void {
		if ( ! isset( $_POST['wnrcg_save_form_fields'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'wnrcg_save_form_fields', 'wnrcg_form_fields_nonce' );

		$rows = isset( $_POST['wnrcg_fields'] ) && is_array( $_POST['wnrcg_fields'] )
			? wp_unslash( $_POST['wnrcg_fields'] )
			: array();

		update_option( WNRCG_Fields::OPTION_KEY, WNRCG_Fields::sanitize_field_set( $rows ), false );

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=wnrcg-form-fields' ) ) );
		exit;
	}

	/**
	 * Render admin page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$fields = WNRCG_Fields::all();

		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Gate form fields saved.', 'wp-no-registration-content-gate' ) . '</p></div>';
		}
		?>
		<div class="wrap wnrcg-admin-wrap">
			<h1><?php esc_html_e( 'Gate form fields', 'wp-no-registration-content-gate' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Configure the unlock form. Answers are stored in WordPress users (wp_users) and user meta (wp_usermeta). Email is always required for matching returning visitors.', 'wp-no-registration-content-gate' ); ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( 'wnrcg_save_form_fields', 'wnrcg_form_fields_nonce' ); ?>
				<input type="hidden" name="wnrcg_save_form_fields" value="1" />

				<table class="widefat striped wnrcg-admin-table wnrcg-fields-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'wp-no-registration-content-gate' ); ?></th>
							<th><?php esc_html_e( 'Field ID', 'wp-no-registration-content-gate' ); ?></th>
							<th><?php esc_html_e( 'Label', 'wp-no-registration-content-gate' ); ?></th>
							<th><?php esc_html_e( 'Type', 'wp-no-registration-content-gate' ); ?></th>
							<th><?php esc_html_e( 'Required', 'wp-no-registration-content-gate' ); ?></th>
							<th><?php esc_html_e( 'Help text', 'wp-no-registration-content-gate' ); ?></th>
							<th><?php esc_html_e( 'Users list', 'wp-no-registration-content-gate' ); ?></th>
							<th><?php esc_html_e( 'CSV', 'wp-no-registration-content-gate' ); ?></th>
							<th><?php esc_html_e( 'Remove', 'wp-no-registration-content-gate' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $fields as $index => $field ) : ?>
							<?php
							$locked = ! empty( $field['locked'] );
							$id     = (string) $field['id'];
							?>
							<tr>
								<td>
									<input type="number" name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][sort_order]" value="<?php echo esc_attr( (string) $field['sort_order'] ); ?>" class="small-text" />
									<input type="hidden" name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" />
									<input type="hidden" name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][storage]" value="<?php echo esc_attr( (string) $field['storage'] ); ?>" />
									<input type="hidden" name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][locked]" value="<?php echo $locked ? '1' : '0'; ?>" />
								</td>
								<td>
									<code><?php echo esc_html( $id ); ?></code>
									<?php if ( $locked ) : ?>
										<br /><span class="description"><?php esc_html_e( 'Core field', 'wp-no-registration-content-gate' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<input type="text" class="regular-text" name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( (string) $field['label'] ); ?>" <?php disabled( $id === 'email' && $locked ); ?> />
								</td>
								<td>
									<?php if ( $locked ) : ?>
										<code><?php echo esc_html( (string) $field['type'] ); ?></code>
										<input type="hidden" name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][type]" value="<?php echo esc_attr( (string) $field['type'] ); ?>" />
									<?php else : ?>
										<select name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][type]">
											<?php foreach ( array( 'text', 'textarea', 'email', 'checkbox', 'select' ) as $type ) : ?>
												<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field['type'], $type ); ?>><?php echo esc_html( $type ); ?></option>
											<?php endforeach; ?>
										</select>
									<?php endif; ?>
								</td>
								<td>
									<label>
										<input type="checkbox" name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?> <?php disabled( $locked && in_array( $id, array( 'email', 'name' ), true ) ); ?> />
									</label>
								</td>
								<td>
									<textarea name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][help]" rows="2" class="large-text"><?php echo esc_textarea( (string) $field['help'] ); ?></textarea>
									<?php if ( (string) $field['type'] === WNRCG_Fields::TYPE_SELECT && ! $locked ) : ?>
										<p class="description"><?php esc_html_e( 'Select options (one per line):', 'wp-no-registration-content-gate' ); ?></p>
										<textarea name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][options]" rows="3" class="large-text"><?php echo esc_textarea( implode( "\n", (array) $field['options'] ) ); ?></textarea>
									<?php endif; ?>
								</td>
								<td>
									<input type="checkbox" name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][show_in_users]" value="1" <?php checked( ! empty( $field['show_in_users'] ) ); ?> <?php disabled( (string) $field['storage'] === WNRCG_Fields::STORAGE_USER_EMAIL ); ?> />
								</td>
								<td>
									<input type="checkbox" name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][show_in_export]" value="1" <?php checked( ! empty( $field['show_in_export'] ) ); ?> />
								</td>
								<td>
									<?php if ( ! $locked ) : ?>
										<label><input type="checkbox" name="wnrcg_fields[<?php echo esc_attr( (string) $index ); ?>][delete]" value="1" /> <?php esc_html_e( 'Remove', 'wp-no-registration-content-gate' ); ?></label>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Add custom question', 'wp-no-registration-content-gate' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wnrcg_new_id"><?php esc_html_e( 'Field ID', 'wp-no-registration-content-gate' ); ?></label></th>
						<td>
							<input name="wnrcg_fields[<?php echo esc_attr( (string) count( $fields ) ); ?>][id]" id="wnrcg_new_id" type="text" class="regular-text" placeholder="job_title" />
							<p class="description"><?php esc_html_e( 'Lowercase letters, numbers, underscores. Stored as wp_usermeta wnrcg_{id}.', 'wp-no-registration-content-gate' ); ?></p>
							<input type="hidden" name="wnrcg_fields[<?php echo esc_attr( (string) count( $fields ) ); ?>][storage]" value="meta" />
							<input type="hidden" name="wnrcg_fields[<?php echo esc_attr( (string) count( $fields ) ); ?>][sort_order]" value="<?php echo esc_attr( (string) ( 50 + count( $fields ) ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wnrcg_new_label"><?php esc_html_e( 'Label', 'wp-no-registration-content-gate' ); ?></label></th>
						<td><input name="wnrcg_fields[<?php echo esc_attr( (string) count( $fields ) ); ?>][label]" id="wnrcg_new_label" type="text" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Type', 'wp-no-registration-content-gate' ); ?></th>
						<td>
							<select name="wnrcg_fields[<?php echo esc_attr( (string) count( $fields ) ); ?>][type]">
								<option value="text">text</option>
								<option value="textarea">textarea</option>
								<option value="select">select</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Required', 'wp-no-registration-content-gate' ); ?></th>
						<td><label><input type="checkbox" name="wnrcg_fields[<?php echo esc_attr( (string) count( $fields ) ); ?>][required]" value="1" /> <?php esc_html_e( 'Required', 'wp-no-registration-content-gate' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Help text', 'wp-no-registration-content-gate' ); ?></th>
						<td><textarea name="wnrcg_fields[<?php echo esc_attr( (string) count( $fields ) ); ?>][help]" rows="2" class="large-text"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Select options', 'wp-no-registration-content-gate' ); ?></th>
						<td><textarea name="wnrcg_fields[<?php echo esc_attr( (string) count( $fields ) ); ?>][options]" rows="3" class="large-text" placeholder="<?php esc_attr_e( "One option per line", 'wp-no-registration-content-gate' ); ?>"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin visibility', 'wp-no-registration-content-gate' ); ?></th>
						<td>
							<label><input type="checkbox" name="wnrcg_fields[<?php echo esc_attr( (string) count( $fields ) ); ?>][show_in_users]" value="1" checked /> <?php esc_html_e( 'Users list column', 'wp-no-registration-content-gate' ); ?></label><br />
							<label><input type="checkbox" name="wnrcg_fields[<?php echo esc_attr( (string) count( $fields ) ); ?>][show_in_export]" value="1" checked /> <?php esc_html_e( 'Include in CSV export', 'wp-no-registration-content-gate' ); ?></label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save form fields', 'wp-no-registration-content-gate' ) ); ?>
			</form>
		</div>
		<?php
	}
}