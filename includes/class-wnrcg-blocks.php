<?php
/**
 * Block and shortcode restrictions (partial content).
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Blocks
 */
class WNRCG_Blocks {

	/**
	 * Gutenberg block name.
	 */
	public const BLOCK_NAME = 'wnrcg/gated-content';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'block_category' ), 10, 2 );
		add_shortcode( 'wnrcg_gated', array( __CLASS__, 'shortcode_gated' ) );
		add_filter( 'wp_trim_excerpt', array( __CLASS__, 'filter_trim_excerpt' ), 5 );
	}

	/**
	 * @param array<int, array<string, string>> $categories Categories.
	 * @return array<int, array<string, string>>
	 */
	public static function block_category( array $categories, $editor_context ): array {
		unset( $editor_context );
		$categories[] = array(
			'slug'  => 'wnrcg',
			'title' => __( 'Content Gate', 'wp-no-registration-content-gate' ),
			'icon'  => null,
		);
		return $categories;
	}

	/**
	 * Register gated content block from block.json.
	 */
	public static function register_block(): void {
		register_block_type( WNRCG_PLUGIN_DIR . 'blocks/gated-content' );
	}

	/**
	 * Classic editor shortcode wrapper (like UM restricted content shortcodes).
	 *
	 * @param array<string, string>|string $atts    Attributes.
	 * @param string|null                  $content Enclosed content.
	 */
	public static function shortcode_gated( $atts, ?string $content = null ): string {
		$post_id = (int) get_the_ID();
		if ( $post_id < 1 ) {
			return '';
		}

		if ( WNRCG_Meta::is_protected( $post_id ) ) {
			return '';
		}

		if ( WNRCG_Content_Gate::access_granted( $post_id ) ) {
			return do_shortcode( (string) $content );
		}

		return WNRCG_Form::render_for_context( $post_id, 'shortcode' );
	}

	/**
	 * Prevent auto excerpts from including gated block/shortcode inner text.
	 *
	 * @param string $excerpt Excerpt.
	 */
	public static function filter_trim_excerpt( string $excerpt ): string {
		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return $excerpt;
		}

		if ( WNRCG_Content_Gate::access_granted( (int) $post->ID ) ) {
			return $excerpt;
		}

		if ( WNRCG_Meta::is_protected( (int) $post->ID ) ) {
			return '';
		}

		if ( ! WNRCG_Content_Gate::post_has_gated_sections( (int) $post->ID ) ) {
			return $excerpt;
		}

		return '';
	}
}