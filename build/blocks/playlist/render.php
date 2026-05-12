<?php
/**
 * MediaShield Playlist Block — Frontend render.
 *
 * Thin shim: builds the block wrapper attributes, then delegates to
 * {@see \MediaShield\Player\PlaylistRenderer} so the block and shortcode
 * produce identical output.
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

$playlist_id = (int) ( $attributes['playlistId'] ?? 0 );
if ( $playlist_id <= 0 ) {
	return;
}

// Inherit autoplay/countdown/loop/shuffle from meta so the wrapper attrs match
// what PlaylistRenderer would build for the shortcode.
$autoplay         = (bool) get_post_meta( $playlist_id, '_ms_autoplay', true );
$ms_countdown_raw = get_post_meta( $playlist_id, '_ms_countdown', true );
$countdown        = (int) ( $ms_countdown_raw ? $ms_countdown_raw : 5 );
$loop             = (bool) get_post_meta( $playlist_id, '_ms_loop', true );
$shuffle          = (bool) get_post_meta( $playlist_id, '_ms_shuffle', true );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'ms-playlist-player',
		'data-playlist-id'    => $playlist_id,
		'data-autoplay'       => $autoplay ? '1' : '0',
		'data-countdown'      => (string) $countdown,
		'data-loop'           => $loop ? '1' : '0',
		'data-shuffle'        => $shuffle ? '1' : '0',
		'data-wp-interactive' => 'mediashield/playlist',
	)
);

echo \MediaShield\Player\PlaylistRenderer::render( $playlist_id, $wrapper_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer output is internally escaped.
