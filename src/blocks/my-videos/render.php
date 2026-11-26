<?php
/**
 * Server-side render for mediashield/my-videos block.
 *
 * Displays a grid of video cards showing the current user's watch history
 * with progress bars, resume links, and completion badges. Filtered and
 * paged on the server.
 *
 * @package MediaShield\Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();

/*
 * The empty and logged-out states: an icon, one line, one next step
 * (front-end walk F28). $ms_state_action is [ url, label ] or null.
 */
$ms_state = static function ( string $icon, string $title, string $line, ?array $action ): void {
	?>
	<div <?php echo get_block_wrapper_attributes( array( 'class' => 'ms-my-videos ms-my-videos--state' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the wrapper attributes. ?>>
		<div class="ms-my-videos__state">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inline SVG from a vendored path map.
			echo \MediaShield\Support\Icons::svg(
				$icon,
				array(
					'size'  => 28,
					'class' => 'ms-my-videos__state-icon',
				)
			);
			?>
			<p class="ms-my-videos__state-title"><?php echo esc_html( $title ); ?></p>
			<p class="ms-my-videos__state-line"><?php echo esc_html( $line ); ?></p>
			<?php if ( $action ) : ?>
				<a class="ms-my-videos__state-action" href="<?php echo esc_url( $action[0] ); ?>"><?php echo esc_html( $action[1] ); ?></a>
			<?php endif; ?>
		</div>
	</div>
	<?php
};

if ( ! $current_user_id ) {
	$ms_state(
		'lock',
		__( 'Please log in to see your video history.', 'mediashield' ),
		__( 'Your videos and where you stopped are kept with your account.', 'mediashield' ),
		array( wp_login_url( get_permalink() ?: home_url( '/' ) ), __( 'Log in', 'mediashield' ) )
	);
	return;
}

global $wpdb;

// Server-side filter and paging, so a member with a long history gets a
// bounded query and the filter covers all of it, not just the loaded page.
// Plain links (no JS): ?ms_videos=in-progress&ms_videos_page=2.
$filters = array(
	'all'         => array( __( 'All', 'mediashield' ), '', __( 'You have not watched any videos yet.', 'mediashield' ) ),
	'in-progress' => array( __( 'In Progress', 'mediashield' ), 'HAVING completion_pct > 0 AND completion_pct < 100', __( 'No videos in progress.', 'mediashield' ) ),
	'completed'   => array( __( 'Completed', 'mediashield' ), 'HAVING completion_pct >= 100', __( 'No completed videos yet.', 'mediashield' ) ),
);
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view state.
$filter = isset( $_GET['ms_videos'] ) ? sanitize_key( wp_unslash( $_GET['ms_videos'] ) ) : 'all';
$page   = isset( $_GET['ms_videos_page'] ) ? max( 1, absint( $_GET['ms_videos_page'] ) ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended
if ( ! isset( $filters[ $filter ] ) ) {
	$filter = 'all';
}

/**
 * Filter how many videos My Videos shows per page.
 *
 * @since 1.4.1
 *
 * @param int $per_page Videos per page. Default 12, capped at 100.
 */
$per_page = min( 100, max( 1, (int) apply_filters( 'mediashield_my_videos_per_page', 12 ) ) );

$sessions_table = "{$wpdb->prefix}ms_watch_sessions";
$played_s       = \MediaShield\Access\SessionManager::played_sql( 's' ); // Only videos the member played: every page load opens a session.
$grouped        = "SELECT s.video_id,
		MAX( s.completion_pct ) AS completion_pct,
		MAX( s.last_heartbeat ) AS last_watched
	 FROM {$sessions_table} s
	 INNER JOIN {$wpdb->posts} p ON s.video_id = p.ID AND p.post_type = 'mediashield_video' AND p.post_status = 'publish'
	 WHERE s.user_id = %d AND {$played_s}
	 GROUP BY s.video_id " . $filters[ $filter ][1];

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; the HAVING clause is a fixed string from $filters. Served by idx_user.
$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ( {$grouped} ) t", $current_user_id ) );
$pages = max( 1, (int) ceil( $total / $per_page ) );
$page  = min( $page, $pages );
$videos = $total ? $wpdb->get_results(
	$wpdb->prepare(
		"{$grouped} ORDER BY last_watched DESC, s.video_id DESC LIMIT %d OFFSET %d",
		$current_user_id,
		$per_page,
		( $page - 1 ) * $per_page
	)
) : array();
// phpcs:enable

if ( ! $total && 'all' === $filter ) {
	// The owner's "Browse videos" link (block setting, or the shortcode's
	// browse_url); no link, no button.
	$ms_browse = isset( $attributes['browseUrl'] ) ? esc_url_raw( (string) $attributes['browseUrl'] ) : '';
	$ms_state(
		'video',
		$filters['all'][2],
		__( 'Videos you start appear here, with where you stopped.', 'mediashield' ),
		'' !== $ms_browse ? array( $ms_browse, __( 'Browse videos', 'mediashield' ) ) : null
	);
	return;
}

// One query for the posts, their meta and their thumbnails, not one per card.
$video_ids = array_map( static fn( $row ) => (int) $row->video_id, $videos );
_prime_post_caches( $video_ids, false, true );
_prime_post_caches( array_filter( array_map( 'get_post_thumbnail_id', $video_ids ) ), false, true );

$view_url = static fn( string $f, int $p = 1 ): string => add_query_arg(
	array(
		'ms_videos'      => 'all' === $f ? false : $f,
		'ms_videos_page' => $p > 1 ? $p : false,
	)
);
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'ms-my-videos' ) ); // phpcs:ignore ?>>
	<nav class="ms-my-videos-filters" aria-label="<?php esc_attr_e( 'Filter videos', 'mediashield' ); ?>">
		<?php foreach ( $filters as $key => $def ) : ?>
			<a class="ms-my-videos-filter-btn<?php echo $key === $filter ? ' is-active' : ''; ?>" href="<?php echo esc_url( $view_url( $key ) ); ?>"<?php echo $key === $filter ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $def[0] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php if ( ! $videos ) : ?>
		<p class="ms-my-videos__empty"><?php echo esc_html( $filters[ $filter ][2] ); ?></p>
	<?php endif; ?>
	<div class="ms-my-videos__grid">
		<?php
		// The page where the member watches each video. An integration (an
		// LMS lesson) answers first; only the videos it leaves without a page
		// are looked up in the content, in one batch (VideoUsage::prime()).
		$watch_urls = array();
		foreach ( $videos as $video ) {
			$video_id = (int) $video->video_id;
			/**
			 * Filter the page where a member watches this video.
			 *
			 * The video post type is not public, so it has no page of its own:
			 * its permalink lands on the home page. An integration that embeds
			 * the video (an LMS lesson, a course page) returns that page's URL.
			 * Left empty, MediaShield links the first published page or post
			 * that shows the video and that this member may read (the Videos
			 * list's "Used in"), so a site with no LMS still leads back to the
			 * video. With none, the title shows as plain text and no Resume
			 * link is printed. The player on that page offers to resume by
			 * itself.
			 *
			 * @since 1.4.1
			 *
			 * @param string $url      Watch page URL. Default ''.
			 * @param int    $video_id Video CPT post ID.
			 * @param int    $user_id  Member the list is for.
			 */
			$watch_urls[ $video_id ] = (string) apply_filters( 'mediashield_video_watch_url', '', $video_id, $current_user_id );
		}
		\MediaShield\CPT\VideoUsage::prime( array_keys( array_filter( $watch_urls, static fn( $url ) => '' === $url ) ) );
		?>
		<?php foreach ( $videos as $video ) : ?>
			<?php
			$video_post = get_post( (int) $video->video_id );
			if ( ! $video_post ) {
				continue;
			}
			$completion  = round( (float) $video->completion_pct, 1 );
			$is_complete = $completion >= 100;
			$thumbnail   = get_the_post_thumbnail_url( $video_post->ID, 'medium' );

			$watch_url = $watch_urls[ $video_post->ID ] ?? '';
			if ( '' === $watch_url ) {
				$watch_url = \MediaShield\CPT\VideoUsage::page_for_viewer( $video_post->ID, $current_user_id );
			}
			?>
			<div class="ms-my-videos-item ms-my-videos__card" data-completion-pct="<?php echo esc_attr( $completion ); ?>">
				<?php if ( $thumbnail ) : ?>
					<div class="ms-my-videos__thumbnail">
						<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( get_the_title( $video_post ) ); ?>" loading="lazy" />
					</div>
				<?php endif; ?>

				<div class="ms-my-videos__info">
					<h3 class="ms-my-videos__title">
						<?php if ( '' !== $watch_url ) : ?>
							<a href="<?php echo esc_url( $watch_url ); ?>">
								<?php echo esc_html( get_the_title( $video_post ) ); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( get_the_title( $video_post ) ); ?>
						<?php endif; ?>
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
					<?php elseif ( '' !== $watch_url ) : ?>
						<a href="<?php echo esc_url( $watch_url ); ?>" class="ms-my-videos__resume">
							<?php echo esc_html__( 'Resume', 'mediashield' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php if ( $pages > 1 ) : ?>
		<nav class="ms-my-videos__pagination" aria-label="<?php esc_attr_e( 'My videos pages', 'mediashield' ); ?>">
			<?php if ( $page > 1 ) : ?>
				<a class="ms-my-videos__page-link" href="<?php echo esc_url( $view_url( $filter, $page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'mediashield' ); ?></a>
			<?php endif; ?>
			<span class="ms-my-videos__page-info">
				<?php
				/* translators: 1: current page, 2: total pages. */
				printf( esc_html__( 'Page %1$d of %2$d', 'mediashield' ), (int) $page, (int) $pages );
				?>
			</span>
			<?php if ( $page < $pages ) : ?>
				<a class="ms-my-videos__page-link" href="<?php echo esc_url( $view_url( $filter, $page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'mediashield' ); ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</div>
