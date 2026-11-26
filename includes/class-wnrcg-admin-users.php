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
		foreach ( WNRCG_Fields::list_columns() as $field ) {
			$id = (string) $field['id'];
			$key = 'wnrcg_' . $id;
			$columns[ $key ] = (string) $field['label'];
		}
		return $columns;
	}

	public static function render_column( string $output, string $column_name, int $user_id ): string {
		if ( ! str_starts_with( $column_name, 'wnrcg_' ) ) {
			return $output;
		}

		$field_id = substr( $column_name, 6 );
		foreach ( WNRCG_Fields::list_columns() as $field ) {
			if ( (string) $field['id'] === $field_id ) {
				$value = WNRCG_Fields::value_for_user( $user_id, $field );
				if ( (string) $field['type'] === WNRCG_Fields::TYPE_TEXTAREA ) {
					return esc_html( wp_html_excerpt( $value, 80, '…' ) );
				}
				return esc_html( $value );
			}
		}

		return $output;
	}

	public static function export_button( string $which ): void {
		if ( $which !== 'top' || ! current_user_can( 'list_users' ) ) {
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

		$export_fields = WNRCG_Fields::export_columns();
		$header        = array( 'ID', 'Role', 'Registered', 'Last Unlocked' );
		foreach ( $export_fields as $field ) {
			$header[] = (string) $field['label'];
		}

		$users = get_users(
			array(
				'fields'     => 'all',
				'meta_query' => array(
					array(
						'key'     => 'wnrcg_unlocked_at',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wnrcg-users-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		if ( $out === false ) {
			exit;
		}

		fputcsv( $out, $header );

		foreach ( $users as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}

			$row = array(
				(string) $user->ID,
				implode( ', ', $user->roles ),
				$user->user_registered,
				(string) get_user_meta( $user->ID, 'wnrcg_last_unlocked_at', true ),
			);

			foreach ( $export_fields as $field ) {
				$row[] = WNRCG_Fields::value_for_user( $user->ID, $field );
			}

			fputcsv( $out, $row );
		}

		fclose( $out );
		exit;
	}
}