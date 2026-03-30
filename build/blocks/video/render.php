<?php
/**
 * MediaShield Video Block — Frontend render (Interactivity API).
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

$video = get_post( $video_id );

if ( ! $video || 'mediashield_video' !== $video->post_type || 'publish' !== $video->post_status ) {
	return;
}

$platform         = get_post_meta( $video_id, '_ms_platform', true ) ?: 'self';
$source_url       = get_post_meta( $video_id, '_ms_source_url', true );
$protection_level = get_post_meta( $video_id, '_ms_protection_level', true ) ?: 'standard';
$player_type      = apply_filters( 'mediashield_player_type', 'standard', $video_id );

if ( empty( $source_url ) ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes( array(
	'class'                  => 'ms-protected-player',
	'data-video-id'          => $video_id,
	'data-platform'          => esc_attr( $platform ),
	'data-protection-level'  => esc_attr( $protection_level ),
	'data-player-type'       => esc_attr( $player_type ),
	'data-wp-interactive'    => 'mediashield/video',
) );
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes handles escaping. ?>>
	<div class="ms-player-inner">
		<?php if ( 'self' === $platform ) : ?>
			<video controls controlsList="nodownload" preload="metadata">
				<source src="<?php echo esc_url( $source_url ); ?>" type="video/mp4">
			</video>
		<?php else : ?>
			<iframe
				src="<?php echo esc_url( $source_url ); ?>"
				frameborder="0"
				allow="autoplay; fullscreen; picture-in-picture"
				allowfullscreen>
			</iframe>
		<?php endif; ?>
	</div>
	<canvas class="ms-watermark-canvas"></canvas>
	<div class="ms-protection-overlay"></div>
</div>
