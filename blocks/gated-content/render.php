<?php
/**
 * Server-render gated inner blocks.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Inner blocks HTML.
 * @var WP_Block             $block      Block instance.
 *
 * @package WNRCG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = (int) get_the_ID();
if ( $post_id < 1 && isset( $block->context['postId'] ) ) {
	$post_id = (int) $block->context['postId'];
}

if ( $post_id < 1 ) {
	return;
}

$scope = isset( $attributes['gateScope'] ) ? (string) $attributes['gateScope'] : '';
if ( $scope === '' ) {
	$scope = WNRCG_Scopes::default_scope();
}
$scope  = WNRCG_Scopes::sanitize_scope( $scope );
$access = WNRCG_Content_Gate::access_granted( $post_id, $scope );

// Entire-page restriction is handled by the_content / post-content filters.
if ( WNRCG_Meta::is_protected( $post_id ) && ! WNRCG_Content_Gate::access_granted( $post_id ) ) {
	return;
}

$class = 'wnrcg-gated-content';
if ( ! $access ) {
	$class .= ' wnrcg-gated-content--locked';
}

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class' => $class,
	)
);

$inner = $access ? $content : WNRCG_Form::render_for_context( $post_id, 'block', $scope );

printf(
	'<div %s>%s</div>',
	$wrapper_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);