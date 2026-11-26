<?php
/**
 * Replace protected content with the unlock form.
 *
 * @package WNRCG
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WNRCG_Content_Gate
 */
class WNRCG_Content_Gate {

	/**
	 * Register content filters.
	 */
	public static function init(): void {
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 1 );
		add_filter( 'the_excerpt', array( __CLASS__, 'filter_excerpt' ), 1 );
		add_filter( 'render_block', array( __CLASS__, 'filter_post_content_block' ), 1, 2 );
	}

	/**
	 * Whether the current visitor may see protected content for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	/**
	 * @param int         $post_id Post ID.
	 * @param string|null $scope   Optional block/shortcode scope (scoped mode).
	 */
	public static function access_granted( int $post_id, ?string $scope = null ): bool {
		return WNRCG_Scopes::access_granted( $post_id, $scope );
	}

	/**
	 * Strip protected body content from the_content.
	 *
	 * @param string $content Post content.
	 */
	public static function filter_content( string $content ): string {
		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return $content;
		}

		if ( ! WNRCG_Meta::is_protected( (int) $post->ID ) ) {
			return $content;
		}

		if ( self::access_granted( (int) $post->ID ) ) {
			return $content;
		}

		if ( ! is_main_query() && ! apply_filters( 'wnrcg_gate_filter_secondary_loops', false, $post ) ) {
			return $content;
		}

		if ( ! in_the_loop() ) {
			return $content;
		}

		return WNRCG_Form::render_for_context( (int) $post->ID, 'entire' );
	}

	/**
	 * Avoid leaking protected text in excerpts.
	 *
	 * @param string $excerpt Excerpt.
	 */
	public static function filter_excerpt( string $excerpt ): string {
		$post = get_post();
		if ( ! $post instanceof WP_Post || ! WNRCG_Meta::is_protected( (int) $post->ID ) ) {
			return $excerpt;
		}

		if ( self::access_granted( (int) $post->ID ) ) {
			return $excerpt;
		}

		if ( self::post_has_gated_sections( (int) $post->ID ) ) {
			return '';
		}

		return '';
	}

	/**
	 * Hide core/post-content block output when gated.
	 *
	 * @param string $block_content Rendered block.
	 * @param array  $block         Block data.
	 */
	public static function filter_post_content_block( string $block_content, array $block ): string {
		if ( ( $block['blockName'] ?? '' ) !== 'core/post-content' ) {
			return $block_content;
		}

		$post_id = get_queried_object_id();
		if ( $post_id < 1 || ! WNRCG_Meta::is_protected( $post_id ) ) {
			return $block_content;
		}

		if ( self::access_granted( $post_id ) ) {
			return $block_content;
		}

		return WNRCG_Form::render_for_context( $post_id, 'entire' );
	}

	/**
	 * Whether the post uses block-level or shortcode-level gates.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function post_has_gated_sections( int $post_id ): bool {
		$content = (string) get_post_field( 'post_content', $post_id );
		if ( $content === '' ) {
			return false;
		}

		if ( str_contains( $content, 'wp:wnrcg/gated-content' ) ) {
			return true;
		}

		return (bool) preg_match( '/\[wnrcg_gated[\]\s]/', $content );
	}

	/**
	 * Whether the front end should load gate styles for this post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function needs_gate_assets( int $post_id ): bool {
		$has_sections = self::post_has_gated_sections( $post_id );
		$protected    = WNRCG_Meta::is_protected( $post_id );

		if ( ! $protected && ! $has_sections ) {
			return false;
		}

		if ( WNRCG_Scopes::is_site_mode() && self::access_granted( $post_id ) ) {
			return false;
		}

		if ( $protected && ! self::access_granted( $post_id ) ) {
			return true;
		}

		return $has_sections && ! is_user_logged_in();
	}
}