<?php
/**
 * Shared playlist renderer — owns the protected playlist HTML output so the
 * Gutenberg block render and the [mediashield_playlist] shortcode produce
 * byte-identical markup.
 *
 * Mirrors {@see \MediaShield\Player\Renderer} for single videos: validates the
 * playlist CPT and items, enqueues frontend assets, returns HTML (does not echo).
 *
 * @package MediaShield\Player
 */

namespace MediaShield\Player;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Core\Assets;

class PlaylistRenderer {

	/**
	 * Render a playlist by ID.
	 *
	 * @param int    $playlist_id   Playlist CPT post ID.
	 * @param string $wrapper_attrs Optional extra wrapper attributes already
	 *                              rendered as a string (e.g. from
	 *                              get_block_wrapper_attributes()). Empty string
	 *                              for shortcode contexts.
	 * @return string HTML output, or empty string for invalid playlists.
	 */
	public static function render( int $playlist_id, string $wrapper_attrs = '' ): string {
		if ( $playlist_id <= 0 ) {
			return self::notice( __( 'No playlist selected.', 'mediashield' ) );
		}

		$playlist = get_post( $playlist_id );
		if ( ! $playlist || 'mediashield_playlist' !== $playlist->post_type || 'publish' !== $playlist->post_status ) {
			return self::notice( __( 'Playlist not found or not published.', 'mediashield' ) );
		}

		$items = self::fetch_items( $playlist_id );
		if ( empty( $items ) ) {
			return self::notice( __( 'This playlist has no videos yet.', 'mediashield' ) );
		}

		// MediaShield is switched off site-wide, so the playlist JS that drives the
		// player never loads. Fall back to the plain videos in playlist order —
		// the queue navigation is a protection-layer feature, the videos are not.
		if ( ! get_option( 'ms_enabled', true ) ) {
			return self::render_unprotected_items( $items, $wrapper_attrs );
		}

		// Only enqueue assets once we know we have something to render.
		Assets::enqueue();

		$autoplay         = (bool) get_post_meta( $playlist_id, '_ms_autoplay', true );
		$ms_countdown_raw = get_post_meta( $playlist_id, '_ms_countdown', true );
		$countdown        = (int) ( $ms_countdown_raw ? $ms_countdown_raw : 5 );
		$loop             = (bool) get_post_meta( $playlist_id, '_ms_loop', true );
		$shuffle          = (bool) get_post_meta( $playlist_id, '_ms_shuffle', true );

		$first             = $items[0];
		$first_platform    = $first->platform ? $first->platform : 'self';
		$first_protection  = $first->protection_level ? $first->protection_level : Protection::default_level();

		// Hide Video Source URL applies to the opening video too, not just the
		// sidebar list. Both are on this page, so missing either one leaks the
		// file address the setting exists to keep out of the markup.
		$first_urls        = Protection::filter_player_urls(
			(int) $first->video_id,
			(string) $first_platform,
			(string) ( $first->source_url ? $first->source_url : '' ),
			''
		);
		$first_source_url  = '' !== $first_urls['source_url'] ? $first_urls['source_url'] : $first_urls['stream_url'];
		$first_player_type = apply_filters( 'mediashield_player_type', 'standard', (int) $first->video_id );

		// If the caller didn't pass block wrapper attributes, build the same
		// shape manually so shortcode markup matches the block.
		if ( '' === $wrapper_attrs ) {
			$wrapper_attrs = sprintf(
				'class="wp-block-mediashield-playlist ms-playlist-player" data-playlist-id="%d" data-autoplay="%s" data-countdown="%d" data-loop="%s" data-shuffle="%s" data-wp-interactive="mediashield/playlist"',
				$playlist_id,
				$autoplay ? '1' : '0',
				$countdown,
				$loop ? '1' : '0',
				$shuffle ? '1' : '0'
			);
		}

		ob_start();
		?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper attrs are built from sanitized data + get_block_wrapper_attributes(). ?>>
	<div class="ms-playlist-main">
		<div class="ms-protected-player"
			data-video-id="<?php echo esc_attr( $first->video_id ); ?>"
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
						allowfullscreen></iframe>
				<?php endif; ?>
			</div>
			<canvas class="ms-watermark-canvas"></canvas>
			<div class="ms-protection-overlay"></div>
		</div>

		<div class="ms-playlist-countdown" style="display:none;">
			<span class="ms-countdown-text"><?php esc_html_e( 'Next video in', 'mediashield' ); ?></span>
			<span class="ms-countdown-timer"><?php echo esc_html( (string) $countdown ); ?></span>
		</div>
	</div>

	<div class="ms-playlist-sidebar">
		<div class="ms-playlist-title"><?php echo esc_html( $playlist->post_title ); ?></div>
		<div class="ms-playlist-items">
			<?php
			foreach ( $items as $idx => $item ) :
				$thumb = get_the_post_thumbnail_url( (int) $item->video_id, 'thumbnail' );

				// Apply "Hide Video Source URL" here too. The 1.3.0 fix covered
				// Renderer and PlayerWrapper but not this path, so a self-hosted
				// video inside a playlist still printed its real file address
				// with the setting on - the whole point of that setting, missed
				// on one of three render paths.
				$item_urls   = Protection::filter_player_urls(
					(int) $item->video_id,
					(string) ( $item->platform ? $item->platform : 'self' ),
					(string) ( $item->source_url ? $item->source_url : '' ),
					''
				);
				$item_source = $item_urls['source_url'];
				$item_stream = $item_urls['stream_url'];
				?>
				<div class="ms-playlist-item <?php echo esc_attr( 0 === $idx ? 'is-active' : '' ); ?>"
					data-video-id="<?php echo esc_attr( $item->video_id ); ?>"
					data-source-url="<?php echo esc_url( $item_source ); ?>"
					data-stream-url="<?php echo esc_url( $item_stream ); ?>"
					data-platform="<?php echo esc_attr( $item->platform ? $item->platform : 'self' ); ?>"
					data-protection-level="<?php echo esc_attr( $item->protection_level ? $item->protection_level : Protection::default_level() ); ?>"
					data-index="<?php echo esc_attr( (string) $idx ); ?>">
					<span class="ms-playlist-item-num"><?php echo esc_html( (string) ( $idx + 1 ) ); ?></span>
					<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $item->video_title ); ?>" class="ms-playlist-item-thumb" loading="lazy" />
					<?php else : ?>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg returns inline SVG built from a vendored Lucide path map; no user input.
						echo \MediaShield\Support\Icons::svg(
							'video',
							array(
								'size'  => 24,
								'class' => 'ms-playlist-item-thumb-placeholder',
							)
						);
						?>
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
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render playlist items as plain, unprotected players.
	 *
	 * Used when MediaShield is switched off site-wide. Items already carry their
	 * platform and source URL from fetch_items(), so this needs no extra queries.
	 *
	 * @since 1.2.0
	 *
	 * @param array  $items         Playlist item rows from fetch_items().
	 * @param string $wrapper_attrs Pre-escaped block wrapper attributes.
	 * @return string Playlist HTML, or empty string when nothing is playable.
	 */
	private static function render_unprotected_items( array $items, string $wrapper_attrs = '' ): string {
		$players = '';

		foreach ( $items as $item ) {
			$players .= Renderer::render_unprotected(
				(int) $item->video_id,
				$item->platform ? $item->platform : 'self',
				$item->source_url ? $item->source_url : ''
			);
		}

		if ( '' === $players ) {
			return '';
		}

		return sprintf(
			'<div class="ms-playlist-unprotected"%s>%s</div>',
			$wrapper_attrs ? ' ' . $wrapper_attrs : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by get_block_wrapper_attributes().
			$players
		);
	}

	/**
	 * Build an editor-only placeholder notice for empty/invalid playlists.
	 *
	 * Front-end visitors get an empty string (no broken UI); users who can edit
	 * content get a visible explanation so the blank area is never a mystery.
	 *
	 * @param string $message Already-translated, human-readable explanation.
	 * @return string Notice HTML for editors, empty string otherwise.
	 */
	private static function notice( string $message ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		// Ensure the notice picks up player.css styling.
		Assets::enqueue();

		return '<p class="ms-playlist-notice">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Fetch published playlist items joined with their video metadata.
	 *
	 * @param int $playlist_id Playlist CPT post ID.
	 * @return array<int, object>
	 */
	private static function fetch_items( int $playlist_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom playlist-items table needs a join that core query APIs cannot express.
		$rows = $wpdb->get_results(
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

		return is_array( $rows ) ? $rows : array();
	}
}
