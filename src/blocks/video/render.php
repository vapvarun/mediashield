<?php
/**
 * MediaShield Video Block — Frontend render.
 *
 * Delegates to Player\Renderer for API-compatible player output.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 *
 * @package MediaShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$video_id = (int) ( $attributes['videoId'] ?? 0 );

if ( $video_id <= 0 ) {
	return;
}

// Pass block wrapper attributes for alignment/spacing support.
$extra = get_block_wrapper_attributes(
	array(
		'data-wp-interactive' => 'mediashield/video',
	)
);

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer::render() returns pre-escaped HTML.
echo \MediaShield\Player\Renderer::render( $video_id, $extra );
