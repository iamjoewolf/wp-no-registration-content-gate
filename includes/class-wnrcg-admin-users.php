<?php
/**
 * Users list columns and CSV export.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Admin_Users
 */
class WNRCG_Admin_Users {

	/**
	 * Register admin hooks.
	 */
	public static function init(): void {
		add_filter( 'manage_users_columns', array( __CLASS__, 'add_columns' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_column' ), 10, 3 );
		add_action( 'manage_users_extra_tablenav', array( __CLASS__, 'export_button' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_export_csv' ) );
	}

	/**
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public static function add_columns( array $columns ): array {
		$columns['wnrcg_job_title']     = __( 'Job title', 'wp-no-registration-content-gate' );
		$columns['wnrcg_poem_usage']    = __( 'Poem usage', 'wp-no-registration-content-gate' );
		$columns['wnrcg_comms_opt_in']  = __( 'Comms opt-in', 'wp-no-registration-content-gate' );

		return $columns;
	}

	/**
	 * @param string $output      Cell output.
	 * @param string $column_name Column key.
	 * @param int    $user_id     User ID.
	 */
	public static function render_column( string $output, string $column_name, int $user_id ): string {
		switch ( $column_name ) {
			case 'wnrcg_job_title':
				return esc_html( (string) get_user_meta( $user_id, 'wnrcg_job_title', true ) );
			case 'wnrcg_poem_usage':
				$usage = (string) get_user_meta( $user_id, 'wnrcg_poem_usage', true );
				return esc_html( wp_html_excerpt( $usage, 80, '…' ) );
			case 'wnrcg_comms_opt_in':
				$opt = (string) get_user_meta( $user_id, 'wnrcg_comms_opt_in', true );
				return $opt === 'yes' ? esc_html__( 'Yes', 'wp-no-registration-content-gate' ) : esc_html__( 'No', 'wp-no-registration-content-gate' );
		}

		return $output;
	}

	/**
	 * Export button above users table.
	 *
	 * @param string $which top|bottom.
	 */
	public static function export_button( string $which ): void {
		if ( $which !== 'top' ) {
			return;
		}

		if ( ! current_user_can( 'list_users' ) ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'users.php?wnrcg_export_csv=1' ),
			'wnrcg_export_users',
			'wnrcg_export_nonce'
		);
		?>
		<div class="alignleft actions">
			<a href="<?php echo esc_url( $url ); ?>" class="button">
				<?php esc_html_e( 'Export CSV (gate fields)', 'wp-no-registration-content-gate' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Stream CSV when requested.
	 */
	public static function maybe_export_csv(): void {
		if ( ! isset( $_GET['wnrcg_export_csv'] ) || $_GET['wnrcg_export_csv'] !== '1' ) {
			return;
		}

		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( esc_html__( 'You cannot export users.', 'wp-no-registration-content-gate' ), 403 );
		}

		if ( ! isset( $_GET['wnrcg_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['wnrcg_export_nonce'] ) ), 'wnrcg_export_users' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-no-registration-content-gate' ), 403 );
		}

		$users = get_users( array( 'fields' => 'all' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wnrcg-users-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		if ( $out === false ) {
			exit;
		}

		fputcsv(
			$out,
			array(
				'ID',
				'Email',
				'Name',
				'Role',
				'Job Title',
				'Poem Usage',
				'Comms Opt-In',
				'Registered',
				'Last Unlocked',
			)
		);

		foreach ( $users as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}

			$roles = implode( ', ', $user->roles );
			$comms = (string) get_user_meta( $user->ID, 'wnrcg_comms_opt_in', true );
			$last  = (string) get_user_meta( $user->ID, 'wnrcg_last_unlocked_at', true );

			fputcsv(
				$out,
				array(
					(string) $user->ID,
					$user->user_email,
					$user->display_name,
					$roles,
					(string) get_user_meta( $user->ID, 'wnrcg_job_title', true ),
					(string) get_user_meta( $user->ID, 'wnrcg_poem_usage', true ),
					$comms === 'yes' ? 'Yes' : 'No',
					$user->user_registered,
					$last,
				)
			);
		}

		fclose( $out );
		exit;
	}
}