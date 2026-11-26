<?php
/**
 * Protected page meta (Block + Classic editors).
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Meta
 */
class WNRCG_Meta {

	public const META_KEY        = '_wnrcg_protected_page';
	public const SCOPE_META_KEY  = '_wnrcg_gate_scope';

	/**
	 * Post types that can be protected.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		$types = array( 'page', 'post' );

		/**
		 * Filter post types that support the content gate.
		 *
		 * @param string[] $types Post type names.
		 */
		return apply_filters( 'wnrcg_protected_post_types', $types );
	}

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );
	}

	/**
	 * Register post meta for REST (block editor).
	 */
	public static function register_meta(): void {
		foreach ( self::post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '0',
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_meta' ),
					'auth_callback'     => static function (): bool {
						return current_user_can( 'edit_posts' );
					},
				)
			);
			register_post_meta(
				$post_type,
				self::SCOPE_META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => static function ( $value ): string {
						return WNRCG_Scopes::sanitize_scope( (string) $value );
					},
					'auth_callback'     => static function (): bool {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Sanitize protected flag.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_meta( $value ): string {
		return ( $value === '1' || $value === 1 || $value === true ) ? '1' : '0';
	}

	/**
	 * Whether a post is protected.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function is_protected( int $post_id ): bool {
		return get_post_meta( $post_id, self::META_KEY, true ) === '1';
	}

	/**
	 * Meta boxes for classic and block editor screens.
	 */
	public static function add_meta_boxes(): void {
		foreach ( self::post_types() as $post_type ) {
			add_meta_box(
				'wnrcg_protected',
				__( 'Content gate', 'wp-no-registration-content-gate' ),
				array( __CLASS__, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Meta box UI.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'wnrcg_save_protected', 'wnrcg_protected_nonce' );
		$checked = self::is_protected( (int) $post->ID );
		$scope   = (string) get_post_meta( (int) $post->ID, self::SCOPE_META_KEY, true );
		?>
		<p>
			<label>
				<input type="checkbox" name="wnrcg_protected_page" value="1" <?php checked( $checked ); ?> />
				<?php esc_html_e( 'Restrict entire page (hide all content until unlock)', 'wp-no-registration-content-gate' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Same idea as Ultimate Member “restrict this page”. For only part of a page, leave this off and use the Gated content block or [wnrcg_gated] shortcode.', 'wp-no-registration-content-gate' ); ?>
		</p>
		<?php if ( ! WNRCG_Scopes::is_site_mode() ) : ?>
			<p>
				<label for="wnrcg_gate_scope"><?php esc_html_e( 'Gate scope', 'wp-no-registration-content-gate' ); ?></label><br />
				<input type="text" class="widefat" name="wnrcg_gate_scope" id="wnrcg_gate_scope" value="<?php echo esc_attr( $scope ); ?>" placeholder="<?php echo esc_attr( WNRCG_Scopes::default_scope() ); ?>" />
			</p>
			<p class="description">
				<?php esc_html_e( 'Visitors who unlocked this scope (same email = same user) can see this page. Use the same scope on related poem packs.', 'wp-no-registration-content-gate' ); ?>
			</p>
		<?php endif; ?>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wnrcg-protected' ) ); ?>">
					<?php esc_html_e( 'View all protected content', 'wp-no-registration-content-gate' ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_meta_box( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! in_array( $post->post_type, self::post_types(), true ) ) {
			return;
		}

		if ( ! isset( $_POST['wnrcg_protected_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wnrcg_protected_nonce'] ) ), 'wnrcg_save_protected' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$protected = isset( $_POST['wnrcg_protected_page'] ) ? '1' : '0';
		update_post_meta( $post_id, self::META_KEY, $protected );

		if ( ! WNRCG_Scopes::is_site_mode() && isset( $_POST['wnrcg_gate_scope'] ) ) {
			update_post_meta(
				$post_id,
				self::SCOPE_META_KEY,
				WNRCG_Scopes::sanitize_scope( sanitize_text_field( wp_unslash( (string) $_POST['wnrcg_gate_scope'] ) ) )
			);
		}
	}
}