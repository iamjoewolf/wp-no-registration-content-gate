<?php
/**
 * Plugin Name:       WP No-Registration Content Gate
 * Plugin URI:        https://github.com/iamjoewolf/wp-no-registration-content-gate
 * Description:       Frictionless unlock form on protected pages; captures users without registration emails.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            iamjoewolf
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-no-registration-content-gate
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WNRCG_VERSION', '1.1.0' );
define( 'WNRCG_PLUGIN_FILE', __FILE__ );
define( 'WNRCG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WNRCG_COOKIE_NAME', 'wnrcg_unlocked_token' );
define( 'WNRCG_COOKIE_DAYS', 365 );

require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-cookie.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-user-service.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-meta.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-fields.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-form.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-unlock-handler.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-content-gate.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-blocks.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-scopes.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-settings.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-admin.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-admin-form-fields.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-admin-users.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-rate-limit.php';
require_once WNRCG_PLUGIN_DIR . 'includes/class-wnrcg-cache.php';

/**
 * Load translations.
 */
function wnrcg_load_textdomain(): void {
	load_plugin_textdomain(
		'wp-no-registration-content-gate',
		false,
		dirname( plugin_basename( WNRCG_PLUGIN_FILE ) ) . '/languages'
	);
}
add_action( 'init', 'wnrcg_load_textdomain' );

/**
 * Bootstrap plugin components.
 */
function wnrcg_bootstrap(): void {
	WNRCG_Fields::maybe_seed();
	WNRCG_Meta::init();
	WNRCG_Unlock_Handler::init();
	WNRCG_Content_Gate::init();
	WNRCG_Blocks::init();
	WNRCG_Admin_Users::init();
	WNRCG_Cache::init();

	if ( is_admin() ) {
		WNRCG_Admin::init();
		WNRCG_Admin_Form_Fields::init();
	}
}
add_action( 'plugins_loaded', 'wnrcg_bootstrap' );

/**
 * Seed default form fields on activation.
 */
function wnrcg_activate(): void {
	WNRCG_Fields::maybe_seed();
}
register_activation_hook( WNRCG_PLUGIN_FILE, 'wnrcg_activate' );

/**
 * Enqueue gate styles on singular protected pages when the gate may show.
 */
function wnrcg_enqueue_assets(): void {
	if ( ! is_singular( WNRCG_Meta::post_types() ) ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! WNRCG_Content_Gate::needs_gate_assets( $post_id ) ) {
		return;
	}

	wp_enqueue_style(
		'wnrcg-gate',
		plugins_url( 'assets/gate.css', WNRCG_PLUGIN_FILE ),
		array(),
		WNRCG_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'wnrcg_enqueue_assets' );

/**
 * Suppress new-user emails when this plugin creates accounts.
 */
function wnrcg_suppress_new_user_emails(): void {
	add_filter( 'wp_send_new_user_notification_to_user', '__return_false' );
	add_filter( 'wp_send_new_user_notification_to_admin', '__return_false' );
	add_filter( 'wp_new_user_notification_email', '__return_false' );
}