<?php
/**
 * Server-side render for mediashield/my-videos block.
 *
 * Displays a grid of video cards showing the current user's watch history
 * with progress bars, resume links, and completion badges.
 *
 * @package MediaShield\Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();

if ( ! $current_user_id ) {
	echo '<p class="ms-my-videos__login-required">';
	echo esc_html__( 'Please log in to see your video history.', 'mediashield' );
	echo '</p>';
	return;
}

global $wpdb;

$sessions_table = "{$wpdb->prefix}ms_watch_sessions";

// Get aggregated watch data per video for the current user.
$videos = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT s.video_id,
				MAX( s.completion_pct ) AS completion_pct,
				MAX( s.max_position ) AS max_position,
				SUM( s.total_seconds ) AS total_seconds,
				MAX( s.last_heartbeat ) AS last_watched
		 FROM {$sessions_table} s
		 INNER JOIN {$wpdb->posts} p ON s.video_id = p.ID AND p.post_status = 'publish'
		 WHERE s.user_id = %d
		 GROUP BY s.video_id
		 ORDER BY last_watched DESC",
		$current_user_id
	)
);

if ( empty( $videos ) ) {
	echo '<p class="ms-my-videos__empty">';
	echo esc_html__( 'You have not watched any videos yet.', 'mediashield' );
	echo '</p>';
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'ms-my-videos' ) ); // phpcs:ignore ?>>
	<div class="ms-my-videos__grid">
		<?php foreach ( $videos as $video ) : ?>
			<?php
			$post           = get_post( (int) $video->video_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			$completion     = round( (float) $video->completion_pct, 1 );
			$is_complete    = $completion >= 100;
			$thumbnail      = get_the_post_thumbnail_url( $post->ID, 'medium' );
			$permalink      = get_permalink( $post->ID );
			$resume_url     = add_query_arg( 'resume', '1', $permalink );
			?>
			<div class="ms-my-videos__card">
				<?php if ( $thumbnail ) : ?>
					<div class="ms-my-videos__thumbnail">
						<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( get_the_title( $post ) ); ?>" loading="lazy" />
					</div>
				<?php endif; ?>

				<div class="ms-my-videos__info">
					<h3 class="ms-my-videos__title">
						<a href="<?php echo esc_url( $permalink ); ?>">
							<?php echo esc_html( get_the_title( $post ) ); ?>
						</a>
					</h3>

					<div class="ms-my-videos__progress">
						<div class="ms-my-videos__progress-bar">
							<div class="ms-my-videos__progress-fill" style="width: <?php echo esc_attr( min( 100, $completion ) ); ?>%"></div>
						</div>
						<span class="ms-my-videos__progress-text">
							<?php
							/* translators: %s: completion percentage */
							printf( esc_html__( '%s%% complete', 'mediashield' ), esc_html( $completion ) );
							?>
						</span>
					</div>

					<?php if ( $is_complete ) : ?>
						<span class="ms-my-videos__badge ms-my-videos__badge--completed">
							<?php echo esc_html__( 'Completed', 'mediashield' ); ?>
						</span>
					<?php else : ?>
						<a href="<?php echo esc_url( $resume_url ); ?>" class="ms-my-videos__resume">
							<?php echo esc_html__( 'Resume', 'mediashield' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
