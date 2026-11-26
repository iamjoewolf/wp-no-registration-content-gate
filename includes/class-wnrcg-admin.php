<?php
/**
 * WordPress admin UI: menu, lists, block editor panel.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Admin
 */
class WNRCG_Admin {

	/**
	 * Register admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( WNRCG_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_state_label' ), 10, 2 );

		foreach ( WNRCG_Meta::post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( __CLASS__, 'list_table_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'list_table_column_render' ), 10, 2 );
		}
	}

	/**
	 * @param string[] $links Plugin links.
	 * @return string[]
	 */
	public static function plugin_action_links( array $links ): array {
		$custom = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wnrcg-gate' ) ) . '">' . esc_html__( 'Dashboard', 'wp-no-registration-content-gate' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wnrcg-settings' ) ) . '">' . esc_html__( 'Settings', 'wp-no-registration-content-gate' ) . '</a>',
		);
		return array_merge( $custom, $links );
	}

	/**
	 * Admin menus.
	 */
	public static function register_menus(): void {
		add_menu_page(
			__( 'Content Gate', 'wp-no-registration-content-gate' ),
			__( 'Content Gate', 'wp-no-registration-content-gate' ),
			'manage_options',
			'wnrcg-gate',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-lock',
			81
		);

		add_submenu_page(
			'wnrcg-gate',
			__( 'Content Gate dashboard', 'wp-no-registration-content-gate' ),
			__( 'Dashboard', 'wp-no-registration-content-gate' ),
			'manage_options',
			'wnrcg-gate',
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			'wnrcg-gate',
			__( 'Protected content', 'wp-no-registration-content-gate' ),
			__( 'Protected content', 'wp-no-registration-content-gate' ),
			'edit_pages',
			'wnrcg-protected',
			array( __CLASS__, 'render_protected_list' )
		);

		add_submenu_page(
			'wnrcg-gate',
			__( 'Gate form fields', 'wp-no-registration-content-gate' ),
			__( 'Gate form fields', 'wp-no-registration-content-gate' ),
			'manage_options',
			'wnrcg-form-fields',
			array( 'WNRCG_Admin_Form_Fields', 'render_page' )
		);

		add_submenu_page(
			'wnrcg-gate',
			__( 'Content Gate settings', 'wp-no-registration-content-gate' ),
			__( 'Settings', 'wp-no-registration-content-gate' ),
			'manage_options',
			'wnrcg-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Register option group.
	 */
	public static function register_settings(): void {
		WNRCG_Settings::register();
	}

	/**
	 * Admin styles on plugin screens.
	 *
	 * @param string $hook_suffix Current screen hook.
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, 'wnrcg' ) ) {
			return;
		}

		wp_enqueue_style(
			'wnrcg-admin',
			plugins_url( 'assets/admin.css', WNRCG_PLUGIN_FILE ),
			array(),
			WNRCG_VERSION
		);
	}

	/**
	 * Block editor document sidebar toggle for protected meta.
	 */
	public static function enqueue_block_editor_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->base !== 'post' ) {
			return;
		}

		if ( ! in_array( $screen->post_type, WNRCG_Meta::post_types(), true ) ) {
			return;
		}

		wp_enqueue_script(
			'wnrcg-block-editor',
			plugins_url( 'assets/js/editor-panel.js', WNRCG_PLUGIN_FILE ),
			array( 'wp-plugins', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n' ),
			WNRCG_VERSION,
			true
		);

		wp_localize_script(
			'wnrcg-block-editor',
			'wnrcgEditor',
			array(
				'metaKey'      => WNRCG_Meta::META_KEY,
				'scopeMetaKey' => WNRCG_Meta::SCOPE_META_KEY,
				'unlockMode'   => WNRCG_Scopes::unlock_mode(),
				'postTypes'    => WNRCG_Meta::post_types(),
				'settingsUrl'  => admin_url( 'admin.php?page=wnrcg-settings' ),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wnrcg-block-editor', 'wp-no-registration-content-gate', WNRCG_PLUGIN_DIR . 'languages' );
		}
	}

	/**
	 * Dashboard overview.
	 */
	public static function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$protected = self::get_protected_posts();
		$unlocked  = self::count_users_with_unlock_meta();

		?>
		<div class="wrap wnrcg-admin-wrap">
			<h1><?php esc_html_e( 'Content Gate', 'wp-no-registration-content-gate' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Manage which pages require the unlock form and review captured visitors.', 'wp-no-registration-content-gate' ); ?>
			</p>

			<div class="wnrcg-admin-cards">
				<div class="wnrcg-admin-card">
					<h2><?php echo esc_html( (string) count( $protected ) ); ?></h2>
					<p><?php esc_html_e( 'Protected pages & posts', 'wp-no-registration-content-gate' ); ?></p>
					<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wnrcg-protected' ) ); ?>"><?php esc_html_e( 'View list', 'wp-no-registration-content-gate' ); ?></a></p>
				</div>
				<div class="wnrcg-admin-card">
					<h2><?php echo esc_html( (string) $unlocked ); ?></h2>
					<p><?php esc_html_e( 'Users with unlock data', 'wp-no-registration-content-gate' ); ?></p>
					<p><a class="button" href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>"><?php esc_html_e( 'All users', 'wp-no-registration-content-gate' ); ?></a></p>
				</div>
				<div class="wnrcg-admin-card">
					<p><?php esc_html_e( 'Cookie lifetime', 'wp-no-registration-content-gate' ); ?></p>
					<h2><?php echo esc_html( (string) WNRCG_Settings::cookie_days() ); ?> <?php esc_html_e( 'days', 'wp-no-registration-content-gate' ); ?></h2>
					<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wnrcg-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'wp-no-registration-content-gate' ); ?></a></p>
				</div>
			</div>

			<h2><?php esc_html_e( 'How to protect a page', 'wp-no-registration-content-gate' ); ?></h2>
			<ol class="wnrcg-admin-steps">
				<li><?php esc_html_e( 'Edit a page or post in the block or classic editor.', 'wp-no-registration-content-gate' ); ?></li>
				<li><?php esc_html_e( 'Entire page: enable “Restrict entire page” in the Content gate panel (block) or meta box (classic).', 'wp-no-registration-content-gate' ); ?></li>
				<li><?php esc_html_e( 'Part of a page: add the Gated content block (block editor) or wrap content in [wnrcg_gated]…[/wnrcg_gated] (classic).', 'wp-no-registration-content-gate' ); ?></li>
			</ol>

			<p>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Add new page', 'wp-no-registration-content-gate' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Table of protected content.
	 */
	public static function render_protected_list(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		$posts = self::get_protected_posts();
		?>
		<div class="wrap wnrcg-admin-wrap">
			<h1><?php esc_html_e( 'Protected content', 'wp-no-registration-content-gate' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'These entries show the unlock form to visitors who are not logged in and have no valid unlock cookie.', 'wp-no-registration-content-gate' ); ?>
			</p>

			<?php if ( empty( $posts ) ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'No protected content yet. Edit a page and turn on the content gate.', 'wp-no-registration-content-gate' ); ?></p></div>
			<?php else : ?>
				<table class="widefat striped wnrcg-admin-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'wp-no-registration-content-gate' ); ?></th>
							<th><?php esc_html_e( 'Type', 'wp-no-registration-content-gate' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-no-registration-content-gate' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wp-no-registration-content-gate' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $posts as $post ) : ?>
							<tr>
								<td>
									<strong>
										<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
											<?php echo esc_html( get_the_title( $post ) ?: __( '(no title)', 'wp-no-registration-content-gate' ) ); ?>
										</a>
									</strong>
								</td>
								<td><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ?? $post->post_type ); ?></td>
								<td><?php echo esc_html( get_post_status_object( $post->post_status )->label ?? $post->post_status ); ?></td>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Edit', 'wp-no-registration-content-gate' ); ?></a>
									<?php if ( $post->post_status === 'publish' ) : ?>
										| <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'wp-no-registration-content-gate' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Settings screen.
	 */
	public static function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = wp_parse_args( get_option( WNRCG_Settings::OPTION_KEY, array() ), WNRCG_Settings::defaults() );
		?>
		<div class="wrap wnrcg-admin-wrap">
			<h1><?php esc_html_e( 'Content Gate settings', 'wp-no-registration-content-gate' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wnrcg_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wnrcg_unlock_mode"><?php esc_html_e( 'Unlock mode', 'wp-no-registration-content-gate' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( WNRCG_Settings::OPTION_KEY ); ?>[unlock_mode]" id="wnrcg_unlock_mode">
								<option value="site" <?php selected( ( $options['unlock_mode'] ?? 'site' ), 'site' ); ?>><?php esc_html_e( 'Site-wide — one unlock opens all gated content (Poems for the Wall)', 'wp-no-registration-content-gate' ); ?></option>
								<option value="scoped" <?php selected( ( $options['unlock_mode'] ?? 'site' ), 'scoped' ); ?>><?php esc_html_e( 'Scoped — unlock per gate group; same email reuses one user', 'wp-no-registration-content-gate' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Scoped mode stores unlocked groups on the user record. The unlock cookie still identifies the visitor without a WordPress login.', 'wp-no-registration-content-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wnrcg_default_gate_scope"><?php esc_html_e( 'Default gate scope', 'wp-no-registration-content-gate' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( WNRCG_Settings::OPTION_KEY ); ?>[default_gate_scope]" id="wnrcg_default_gate_scope" type="text" class="regular-text" value="<?php echo esc_attr( (string) ( $options['default_gate_scope'] ?? WNRCG_Scopes::SCOPE_ALL ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Slug used when a page or block has no scope (e.g. pack-a). Ignored in site-wide mode.', 'wp-no-registration-content-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wnrcg_form_title"><?php esc_html_e( 'Form heading', 'wp-no-registration-content-gate' ); ?></label></th>
						<td><input name="<?php echo esc_attr( WNRCG_Settings::OPTION_KEY ); ?>[form_title]" id="wnrcg_form_title" type="text" class="regular-text" value="<?php echo esc_attr( (string) $options['form_title'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wnrcg_submit_label"><?php esc_html_e( 'Submit button label', 'wp-no-registration-content-gate' ); ?></label></th>
						<td><input name="<?php echo esc_attr( WNRCG_Settings::OPTION_KEY ); ?>[submit_label]" id="wnrcg_submit_label" type="text" class="regular-text" value="<?php echo esc_attr( (string) $options['submit_label'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wnrcg_cookie_days"><?php esc_html_e( 'Unlock cookie lifetime (days)', 'wp-no-registration-content-gate' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( WNRCG_Settings::OPTION_KEY ); ?>[cookie_days]" id="wnrcg_cookie_days" type="number" min="1" max="730" value="<?php echo esc_attr( (string) $options['cookie_days'] ); ?>" />
							<p class="description"><?php esc_html_e( 'How long returning visitors stay unlocked (1–730 days).', 'wp-no-registration-content-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Returning users', 'wp-no-registration-content-gate' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( WNRCG_Settings::OPTION_KEY ); ?>[update_display_name]" value="1" <?php checked( ! empty( $options['update_display_name'] ) ); ?> />
								<?php esc_html_e( 'Update WordPress display name when an existing user unlocks again', 'wp-no-registration-content-gate' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'If unchecked, the submitted name is stored in user meta only (recommended for historic accounts).', 'wp-no-registration-content-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cookie refresh', 'wp-no-registration-content-gate' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( WNRCG_Settings::OPTION_KEY ); ?>[cookie_slide_refresh]" value="1" <?php checked( ! empty( $options['cookie_slide_refresh'] ) ); ?> />
								<?php esc_html_e( 'Extend unlock cookie lifetime on each visit while the cookie is still valid', 'wp-no-registration-content-gate' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wnrcg_privacy_policy_url"><?php esc_html_e( 'Privacy policy URL', 'wp-no-registration-content-gate' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( WNRCG_Settings::OPTION_KEY ); ?>[privacy_policy_url]" id="wnrcg_privacy_policy_url" type="url" class="regular-text" value="<?php echo esc_attr( (string) ( $options['privacy_policy_url'] ?? '' ) ); ?>" placeholder="https://" />
							<p class="description"><?php esc_html_e( 'Optional link shown below the unlock form.', 'wp-no-registration-content-gate' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Caching', 'wp-no-registration-content-gate' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Discourage page cache', 'wp-no-registration-content-gate' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( WNRCG_Settings::OPTION_KEY ); ?>[prevent_cache]" value="1" <?php checked( ! isset( $options['prevent_cache'] ) || ! empty( $options['prevent_cache'] ) ); ?> />
								<?php esc_html_e( 'Set DONOTCACHEPAGE on gated singular views (when the visitor may see the unlock form)', 'wp-no-registration-content-gate' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Also exclude gated URLs in your CDN or cache plugin when possible. Override per site with the wnrcg_gate_should_prevent_cache filter.', 'wp-no-registration-content-gate' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="description">
					<?php esc_html_e( 'Protected pages must not serve the same cached HTML to locked and unlocked visitors.', 'wp-no-registration-content-gate' ); ?>
				</p>

				<p class="description">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wnrcg-form-fields' ) ); ?>">
						<?php esc_html_e( 'Edit unlock form questions (fields, labels, opt-in)', 'wp-no-registration-content-gate' ); ?>
					</a>
				</p>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return WP_Post[]
	 */
	private static function get_protected_posts(): array {
		$query = new WP_Query(
			array(
				'post_type'              => WNRCG_Meta::post_types(),
				'post_status'            => array( 'publish', 'draft', 'future', 'private', 'pending' ),
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'meta_key'               => WNRCG_Meta::META_KEY,
				'meta_value'             => '1',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return $query->posts;
	}

	/**
	 * Users with at least one unlock timestamp.
	 */
	private static function count_users_with_unlock_meta(): int {
		$user_query = new WP_User_Query(
			array(
				'meta_key'     => 'wnrcg_last_unlocked_at',
				'meta_compare' => 'EXISTS',
				'fields'       => 'ID',
				'number'       => 1,
				'count_total'  => true,
			)
		);

		return (int) $user_query->get_total();
	}

	/**
	 * Show “Content gate” under title in Pages/Posts list.
	 *
	 * @param string[] $states States.
	 * @param WP_Post  $post   Post.
	 * @return string[]
	 */
	public static function post_state_label( array $states, WP_Post $post ): array {
		if ( ! in_array( $post->post_type, WNRCG_Meta::post_types(), true ) ) {
			return $states;
		}

		if ( WNRCG_Meta::is_protected( (int) $post->ID ) ) {
			$states['wnrcg_protected'] = __( 'Content gate', 'wp-no-registration-content-gate' );
		}

		return $states;
	}

	/**
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public static function list_table_column( array $columns ): array {
		$columns['wnrcg_gate'] = __( 'Content gate', 'wp-no-registration-content-gate' );
		return $columns;
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function list_table_column_render( string $column, int $post_id ): void {
		if ( $column !== 'wnrcg_gate' ) {
			return;
		}

		if ( WNRCG_Meta::is_protected( $post_id ) ) {
			echo '<span class="wnrcg-admin-badge">' . esc_html__( 'Protected', 'wp-no-registration-content-gate' ) . '</span>';
		} else {
			echo '<span class="wnrcg-admin-badge wnrcg-admin-badge--muted">' . esc_html__( 'Open', 'wp-no-registration-content-gate' ) . '</span>';
		}
	}
}