<?php
/**
 * Shared player renderer — outputs the .ms-protected-player container
 * with .ms-player-target div for JS adapter initialization.
 *
 * Used by Shortcode, Gutenberg block render.php, and single template.
 * No more raw iframes — JS adapters create the player via platform APIs.
 *
 * @package MediaShield\Player
 */

namespace MediaShield\Player;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Core\Assets;

/**
 * Class Renderer
 *
 * Shared player renderer for protected video containers.
 *
 * @since 1.0.0
 */
class Renderer {

	/**
	 * Build an editor-only placeholder notice for a video that cannot render.
	 *
	 * A mistyped or unpublished ID otherwise produces silent blank space with
	 * nothing to search for. Front-end visitors still get nothing (never expose
	 * why a video is missing); users who can edit content get an explanation.
	 *
	 * Mirrors PlaylistRenderer::notice(), which has always done this.
	 *
	 * @since 1.2.0
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
	 * Render a video with no protection applied.
	 *
	 * Used when MediaShield is switched off site-wide. The protected player is a
	 * JS-filled shell, and the player JS is not registered while the plugin is
	 * off, so this emits self-sufficient native markup instead: an <iframe> for
	 * platform embeds, a <video> for self-hosted files. No watermark, no session
	 * tracking, no protection overlay — just the video.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $video_id      Video CPT post ID.
	 * @param string $platform      Platform identifier.
	 * @param string $source_url    Source URL.
	 * @param string $stream_url    Stream URL, used when no source URL is stored.
	 * @param string $wrapper_attrs Pre-escaped block wrapper attributes.
	 * @return string Player HTML, or empty string when there is nothing playable.
	 */
	public static function render_unprotected( int $video_id, string $platform, string $source_url, string $stream_url = '', string $wrapper_attrs = '' ): string {
		$url = ! empty( $source_url ) ? $source_url : $stream_url;

		if ( empty( $url ) ) {
			return '';
		}

		if ( 'self' === $platform ) {
			$player = sprintf(
				'<video class="ms-unprotected-player" controls playsinline src="%s"></video>',
				esc_url( $url )
			);
		} else {
			$player = sprintf(
				'<iframe class="ms-unprotected-player" src="%s" frameborder="0" allowfullscreen title="%s"></iframe>',
				esc_url( $url ),
				esc_attr( get_the_title( $video_id ) )
			);
		}

		$html = sprintf(
			'<div class="ms-player-unprotected"%s>%s</div>',
			$wrapper_attrs ? ' ' . $wrapper_attrs : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by get_block_wrapper_attributes().
			$player
		);

		/**
		 * Filter the unprotected player HTML used while MediaShield is switched off.
		 *
		 * @since 1.2.0
		 *
		 * @param string $html     The rendered unprotected player HTML.
		 * @param int    $video_id Video CPT post ID.
		 * @param string $platform Platform identifier.
		 */
		return apply_filters( 'mediashield_unprotected_player_html', $html, $video_id, $platform );
	}

	/**
	 * Render the protected player container for a video CPT.
	 *
	 * @param int    $video_id        Video CPT post ID.
	 * @param string $wrapper_attrs   Optional extra wrapper attributes (e.g. from get_block_wrapper_attributes).
	 * @return string HTML output.
	 */
	public static function render( int $video_id, string $wrapper_attrs = '' ): string {
		$video = get_post( $video_id );

		if ( ! $video || 'mediashield_video' !== $video->post_type ) {
			return self::notice(
				sprintf(
					/* translators: %d: video ID used in the shortcode or block. */
					__( 'MediaShield: no video found with ID %d.', 'mediashield' ),
					$video_id
				)
			);
		}

		if ( 'publish' !== $video->post_status ) {
			return self::notice(
				sprintf(
					/* translators: %s: video title. */
					__( 'MediaShield: the video "%s" is not published yet, so it is only visible to you.', 'mediashield' ),
					$video->post_title
				)
			);
		}

		$platform_raw      = get_post_meta( $video_id, '_ms_platform', true );
		$platform          = ! empty( $platform_raw ) ? $platform_raw : 'self';
		$platform_video_id = get_post_meta( $video_id, '_ms_platform_video_id', true );
		$source_url        = get_post_meta( $video_id, '_ms_source_url', true );
		$stream_url        = get_post_meta( $video_id, '_ms_stream_url', true );

		/**
		 * Filter the direct stream URL for a video.
		 *
		 * A stream URL is what lets MediaShield play the video in a real
		 * <video> element instead of an opaque provider iframe, which is the
		 * difference between having a controllable player (watermark, speed,
		 * accurate progress, in-video ad breaks) and not. Platform integrations
		 * that can derive one — Pro knows each Bunny library's pull zone, so it
		 * can build the HLS playlist URL from the video GUID — supply it here
		 * for videos that were imported before the URL was recorded.
		 *
		 * @since 1.2.0
		 *
		 * @param string $stream_url        Stored stream URL, usually empty.
		 * @param int    $video_id          Video CPT post ID.
		 * @param string $platform          Platform slug (self, bunny, youtube…).
		 * @param string $platform_video_id Provider-side video id / GUID.
		 */
		$stream_url = (string) apply_filters( 'mediashield_video_stream_url', (string) $stream_url, $video_id, $platform, (string) $platform_video_id );

		$protection_raw    = get_post_meta( $video_id, '_ms_protection_level', true );
		// Fall back to the operator-configured global default before the
		// last-resort 'standard' so the Settings → Default Protection Level
		// toggle actually has an effect on videos that don't carry a per-video
		// override.
		$protection_level = ! empty( $protection_raw )
			? $protection_raw
			: \MediaShield\Core\Settings::get( 'ms_default_protection' );
		if ( empty( $protection_level ) ) {
			$protection_level = 'standard';
		}
		$duration    = (int) get_post_meta( $video_id, '_ms_duration', true );
		$player_type = apply_filters( 'mediashield_player_type', 'standard', $video_id );

		if ( empty( $source_url ) && empty( $stream_url ) && empty( $platform_video_id ) ) {
			return self::notice(
				sprintf(
					/* translators: %s: video title. */
					__( 'MediaShield: the video "%s" has no source file or URL yet. Edit it and add one.', 'mediashield' ),
					$video->post_title
				)
			);
		}

		// MediaShield is switched off site-wide. Assets::register_frontend() bails
		// in that state, so the player JS that fills .ms-player-target never loads
		// and the protected container would render as an empty box. Turning
		// protection off must cost the viewer the protection, not the video.
		if ( ! get_option( 'ms_enabled', true ) ) {
			return self::render_unprotected( $video_id, $platform, $source_url, $stream_url, $wrapper_attrs );
		}

		// Enforce "Hide Video Source URL" server-side. Stripping the URL in JS
		// after the server had already printed it into data-source-url never hid
		// anything (BC#10143668134).
		//
		// Applied AFTER the switched-off bailout above on purpose: that path
		// deliberately hands the viewer the plain URL so the video still plays
		// with protection off, and routing it through the gated /stream/
		// endpoint instead would re-impose a check the operator just disabled.
		$safe_urls  = Protection::filter_player_urls( $video_id, (string) $platform, (string) $source_url, $stream_url );
		$source_url = $safe_urls['source_url'];
		$stream_url = $safe_urls['stream_url'];

		// Enqueue frontend assets (only loads when video content exists).
		Assets::enqueue();

		// Flag that we need Shaka Player for self-hosted / bunny.
		if ( in_array( $platform, array( 'self', 'bunny' ), true ) ) {
			do_action( 'mediashield_needs_shaka' );
		}

		/**
		 * Fires before a protected player is rendered.
		 *
		 * @since 1.1.0
		 *
		 * @param int $video_id Video CPT post ID.
		 */
		do_action( 'mediashield_before_player', $video_id );

		/**
		 * Filter the CSS classes applied to the player container.
		 *
		 * @since 1.1.0
		 *
		 * @param array $classes  Array of CSS class names.
		 * @param int   $video_id Video CPT post ID.
		 */
		$classes = apply_filters( 'mediashield_player_classes', array( 'ms-protected-player' ), $video_id );

		/**
		 * Filter the per-video access type.
		 *
		 * An extension returns a slug (typically the `_ms_access_type` meta value)
		 * so the client can route gated videos to the right overlay
		 * instead of the default login shortcut. Empty string means no special gate.
		 *
		 * @since 1.1.0
		 *
		 * @param string $access_type Access type slug, '' when not gated.
		 * @param int    $video_id    Video CPT post ID.
		 */
		$access_type = (string) apply_filters( 'mediashield_player_access_type', '', $video_id );

		/**
		 * In-video ad breaks (pre / mid / post-roll) for this video. Populated
		 * by the WB Ad Manager bridge; empty when no ad source is active. When
		 * present, the ad-breaks engine is enqueued and the list emitted on the
		 * container for ad-breaks.js to consume.
		 *
		 * @since 1.2.0
		 *
		 * @param array $breaks   List of break descriptors.
		 * @param int   $video_id Video CPT post ID.
		 * @param int   $duration Video duration in seconds.
		 */
		$ad_breaks = apply_filters( 'mediashield_video_ad_breaks', array(), $video_id, (int) $duration );
		if ( ! empty( $ad_breaks ) ) {
			wp_enqueue_script( 'mediashield-ad-breaks' );
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			data-video-id="<?php echo esc_attr( $video_id ); ?>"
			data-platform="<?php echo esc_attr( $platform ); ?>"
			data-protection-level="<?php echo esc_attr( $protection_level ); ?>"
			data-player-type="<?php echo esc_attr( $player_type ); ?>"
			<?php if ( ! empty( $ad_breaks ) ) : ?>
				data-ad-breaks="<?php echo esc_attr( wp_json_encode( $ad_breaks ) ); ?>"
			<?php endif; ?>
			<?php
			if ( '' !== $access_type ) :
				?>
				data-access-type="<?php echo esc_attr( $access_type ); ?>"<?php endif; ?>
			<?php
			if ( $wrapper_attrs ) {
				echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by get_block_wrapper_attributes().
			}
			?>
		>
			<?php
			// Per-video player feature overrides (tri-state: on/off/empty=global).
			$video_overrides = array();
			$override_keys   = array(
				'_ms_player_speed'     => 'speedControl',
				'_ms_player_keyboard'  => 'keyboard',
				'_ms_player_resume'    => 'resume',
				'_ms_player_sticky'    => 'sticky',
				'_ms_player_endscreen' => 'endscreen',
			);
			foreach ( $override_keys as $meta_key => $js_key ) {
				$val = get_post_meta( $video_id, $meta_key, true );
				if ( 'on' === $val || 'off' === $val ) {
					$video_overrides[ $js_key ] = ( 'on' === $val );
				}
			}
			// Per-video end screen text/URL.
			$es_text = get_post_meta( $video_id, '_ms_player_endscreen_text', true );
			$es_url  = get_post_meta( $video_id, '_ms_player_endscreen_url', true );
			if ( ! empty( $es_text ) ) {
				$video_overrides['endscreenText'] = $es_text;
			}
			if ( ! empty( $es_url ) ) {
				$video_overrides['endscreenUrl'] = $es_url;
			}

			// Binary playback options stored on the video CPT. Meta is tri-state:
			// '1' = explicit on, '0' = explicit off, '' = pristine (never saved —
			// treat as adapter default so older videos keep working unchanged).
			$playback_keys  = array(
				'_ms_autoplay'      => 'autoplay',
				'_ms_loop'          => 'loop',
				'_ms_muted'         => 'muted',
				'_ms_show_controls' => 'controls',
			);
			$playback_attrs = '';
			foreach ( $playback_keys as $meta_key => $data_attr ) {
				$raw = (string) get_post_meta( $video_id, $meta_key, true );
				if ( '1' === $raw || '0' === $raw ) {
					$playback_attrs .= sprintf( ' data-%s="%s"', $data_attr, esc_attr( $raw ) );
				}
			}
			?>
			<div class="ms-player-target"
				data-platform-video-id="<?php echo esc_attr( $platform_video_id ); ?>"
				data-source-url="<?php echo esc_url( $source_url ); ?>"
				data-stream-url="<?php echo esc_url( $stream_url ); ?>"
				data-duration="<?php echo esc_attr( $duration ); ?>"
				<?php echo $playback_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes pre-escaped above. ?>
				<?php if ( ! empty( $video_overrides ) ) : ?>
					data-player-overrides="<?php echo esc_attr( wp_json_encode( $video_overrides ) ); ?>"
				<?php endif; ?>
			>
			</div>
			<canvas class="ms-watermark-canvas" aria-hidden="true"></canvas>
			<div class="ms-protection-overlay"></div>
			<button class="ms-fullscreen-btn" aria-label="<?php esc_attr_e( 'Fullscreen', 'mediashield' ); ?>" title="<?php esc_attr_e( 'Fullscreen', 'mediashield' ); ?>">
				<?php echo \MediaShield\Support\Icons::svg( 'maximize', array( 'size' => 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static SVG. ?>
			</button>
		</div>
		<?php
		$html = ob_get_clean();

		/**
		 * Filter the complete player HTML output.
		 *
		 * @since 1.1.0
		 *
		 * @param string $html     The rendered player HTML.
		 * @param int    $video_id Video CPT post ID.
		 * @param array  $atts     Player attributes (platform, protection_level, etc.).
		 */
		$html = apply_filters(
			'mediashield_player_html',
			$html,
			$video_id,
			array(
				'platform'         => $platform,
				'protection_level' => $protection_level,
				'player_type'      => $player_type,
			)
		);

		/**
		 * Fires after a protected player is rendered.
		 *
		 * @since 1.1.0
		 *
		 * @param int $video_id Video CPT post ID.
		 */
		do_action( 'mediashield_after_player', $video_id );

		return $html;
	}
}
