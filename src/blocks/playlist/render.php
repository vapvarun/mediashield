<?php
/**
 * MediaShield Playlist Block — Frontend render.
 *
 * Outputs a playlist player with sidebar video list + main player area.
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

$playlist = get_post( $playlist_id );

if ( ! $playlist || 'mediashield_playlist' !== $playlist->post_type || 'publish' !== $playlist->post_status ) {
	return;
}

// Fetch playlist items.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query for block render.
$items = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT pi.id AS item_id, pi.video_id, pi.sort_order,
			p.post_title AS video_title,
			pm_platform.meta_value AS platform,
			pm_url.meta_value AS source_url,
			pm_protection.meta_value AS protection_level
	 FROM {$wpdb->prefix}ms_playlist_items pi
	 INNER JOIN {$wpdb->posts} p ON pi.video_id = p.ID AND p.post_status = 'publish'
	 LEFT JOIN {$wpdb->postmeta} pm_platform ON pi.video_id = pm_platform.post_id AND pm_platform.meta_key = '_ms_platform'
	 LEFT JOIN {$wpdb->postmeta} pm_url ON pi.video_id = pm_url.post_id AND pm_url.meta_key = '_ms_source_url'
	 LEFT JOIN {$wpdb->postmeta} pm_protection ON pi.video_id = pm_protection.post_id AND pm_protection.meta_key = '_ms_protection_level'
	 WHERE pi.playlist_id = %d
	 ORDER BY pi.sort_order ASC, pi.id ASC",
		$playlist_id
	)
);

if ( empty( $items ) ) {
	return;
}

// Playlist settings.
$autoplay         = (bool) get_post_meta( $playlist_id, '_ms_autoplay', true );
$ms_countdown_raw = get_post_meta( $playlist_id, '_ms_countdown', true );
$countdown        = (int) ( $ms_countdown_raw ? $ms_countdown_raw : 5 );
$loop             = (bool) get_post_meta( $playlist_id, '_ms_loop', true );
$shuffle          = (bool) get_post_meta( $playlist_id, '_ms_shuffle', true );

$first_item        = $items[0];
$first_platform    = $first_item->platform ? $first_item->platform : 'self';
$first_source_url  = $first_item->source_url ? $first_item->source_url : '';
$first_protection  = $first_item->protection_level ? $first_item->protection_level : 'standard';
$first_player_type = apply_filters( 'mediashield_player_type', 'standard', (int) $first_item->video_id );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'               => 'ms-playlist-player',
		'data-playlist-id'    => $playlist_id,
		'data-autoplay'       => $autoplay ? '1' : '0',
		'data-countdown'      => $countdown,
		'data-loop'           => $loop ? '1' : '0',
		'data-shuffle'        => $shuffle ? '1' : '0',
		'data-wp-interactive' => 'mediashield/playlist',
	)
);
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="ms-playlist-main">
		<div class="ms-protected-player"
			data-video-id="<?php echo esc_attr( $first_item->video_id ); ?>"
			data-platform="<?php echo esc_attr( $first_platform ); ?>"
			data-protection-level="<?php echo esc_attr( $first_protection ); ?>"
			data-player-type="<?php echo esc_attr( $first_player_type ); ?>">
			<div class="ms-player-inner">
				<?php if ( 'self' === $first_platform ) : ?>
					<video controls controlsList="nodownload" preload="metadata">
						<source src="<?php echo esc_url( $first_source_url ); ?>" type="video/mp4">
					</video>
				<?php elseif ( $first_source_url ) : ?>
					<iframe
						src="<?php echo esc_url( $first_source_url ); ?>"
						frameborder="0"
						allow="autoplay; fullscreen; picture-in-picture"
						allowfullscreen>
					</iframe>
				<?php endif; ?>
			</div>
			<canvas class="ms-watermark-canvas"></canvas>
			<div class="ms-protection-overlay"></div>
		</div>

		<div class="ms-playlist-countdown" style="display:none;">
			<span class="ms-countdown-text"><?php esc_html_e( 'Next video in', 'mediashield' ); ?></span>
			<span class="ms-countdown-timer"><?php echo esc_html( $countdown ); ?></span>
		</div>
	</div>

	<div class="ms-playlist-sidebar">
		<div class="ms-playlist-title"><?php echo esc_html( $playlist->post_title ); ?></div>
		<div class="ms-playlist-items">
			<?php
			foreach ( $items as $idx => $item ) :
				$thumb = get_the_post_thumbnail_url( (int) $item->video_id, 'thumbnail' );
				?>
				<div class="ms-playlist-item <?php echo 0 === $idx ? 'is-active' : ''; ?>"
					data-video-id="<?php echo esc_attr( $item->video_id ); ?>"
					data-source-url="<?php echo esc_url( $item->source_url ? $item->source_url : '' ); ?>"
					data-platform="<?php echo esc_attr( $item->platform ? $item->platform : 'self' ); ?>"
					data-protection-level="<?php echo esc_attr( $item->protection_level ? $item->protection_level : 'standard' ); ?>"
					data-index="<?php echo esc_attr( $idx ); ?>">
					<span class="ms-playlist-item-num"><?php echo esc_html( $idx + 1 ); ?></span>
					<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>" alt="" class="ms-playlist-item-thumb" loading="lazy" />
					<?php else : ?>
						<span class="dashicons dashicons-video-alt3 ms-playlist-item-thumb-placeholder"></span>
					<?php endif; ?>
					<div class="ms-playlist-item-info">
						<span class="ms-playlist-item-title"><?php echo esc_html( $item->video_title ); ?></span>
						<span class="ms-playlist-item-platform"><?php echo esc_html( $item->platform ? $item->platform : 'self' ); ?></span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
