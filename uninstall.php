<?php
/**
 * Uninstall: remove plugin options (user accounts and meta are kept).
 *
 * @package WNRCG
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wnrcg_form_fields' );
delete_option( 'wnrcg_settings' );

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
		'_wnrcg_protected_page'
	)
);