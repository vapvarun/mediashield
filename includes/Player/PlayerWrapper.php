<?php
/**
 * Detect and wrap video embeds with MediaShield protection.
 *
 * Hooks into template_redirect to buffer output. Replaces detected iframes
 * with .ms-player-target divs for JS adapter initialization — no more raw
 * iframes. Extracts platform video IDs from embed URLs.
 *
 * @package MediaShield\Player
 */

namespace MediaShield\Player;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Core\Assets;

/**
 * Class PlayerWrapper
 *
 * Detects and wraps video embeds with MediaShield protection.
 *
 * @since 1.0.0
 */
class PlayerWrapper {

	/**
	 * Register the output buffer hook.
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ) );
	}

	/**
	 * Start output buffering on frontend pages.
	 */
	public static function start_buffer(): void {
		/**
		 * Filter whether MediaShield output buffering is enabled.
		 *
		 * Return false to disable automatic video detection via output buffering.
		 * Useful when using only shortcodes/blocks, or when OB conflicts with
		 * caching plugins.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $enable Whether to enable output buffering. Default true.
		 */
		if ( ! apply_filters( 'mediashield_enable_output_buffer', true ) ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! get_option( 'ms_enabled', true ) ) {
			return;
		}

		ob_start( array( __CLASS__, 'process_buffer' ) );
	}

	/**
	 * Process the output buffer — find and wrap video embeds.
	 *
	 * @param string $html Full page HTML.
	 * @return string Processed HTML.
	 */
	public static function process_buffer( string $html ): string {
		if ( empty( $html ) ) {
			return $html;
		}

		if ( ! preg_match( '/<(iframe|video|div[^>]*wistia)/i', $html ) ) {
			return $html;
		}

		// Video content detected in output buffer — enqueue assets.
		Assets::enqueue();

		// YouTube iframes (including nocookie).
		$html = self::wrap_platform(
			$html,
			'/<iframe[^>]*\ssrc=["\']([^"\']*(?:youtube\.com\/embed|youtube-nocookie\.com\/embed)[^"\']*)["\'][^>]*><\/iframe>/i',
			'youtube'
		);

		// Vimeo iframes.
		$html = self::wrap_platform(
			$html,
			'/<iframe[^>]*\ssrc=["\']([^"\']*player\.vimeo\.com\/video[^"\']*)["\'][^>]*><\/iframe>/i',
			'vimeo'
		);

		// Bunny Stream iframes.
		$html = self::wrap_platform(
			$html,
			'/<iframe[^>]*\ssrc=["\']([^"\']*iframe\.mediadelivery\.net[^"\']*)["\'][^>]*><\/iframe>/i',
			'bunny'
		);

		// Wistia embeds.
		$html = self::wrap_platform(
			$html,
			'/<div[^>]*class=["\'][^"\']*wistia_embed\s+wistia_async_([a-z0-9]+)[^"\']*["\'][^>]*>.*?<\/div>/is',
			'wistia'
		);

		// Self-hosted <video> tags.
		$html = self::wrap_platform(
			$html,
			'/<video[^>]*>.*?<\/video>/is',
			'self'
		);

		return $html;
	}

	/**
	 * Find matches, extract platform video ID, and replace with player target div.
	 *
	 * @param string $html     HTML content.
	 * @param string $pattern  Regex pattern (must capture src URL in group 1 for iframes).
	 * @param string $platform Platform identifier.
	 * @return string Processed HTML.
	 */
	private static function wrap_platform( string $html, string $pattern, string $platform ): string {
		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $platform, $html ) {
				$embed = $matches[0];

				// Double-wrap prevention.
				if ( str_contains( $embed, 'ms-protected-player' ) || str_contains( $embed, 'ms-player-target' ) ) {
					return $embed;
				}

				// Explicit opt-out: an embed carrying `data-ms-skip` (or the
				// `ms-skip` class) is left untouched. Lets other contexts render
				// a native <video>/<iframe> — e.g. an ad-creative preview in an
				// advertiser dashboard — without MediaShield protection wrapping.
				if ( preg_match( '/\bdata-ms-skip\b/i', $embed ) || preg_match( '/class=["\'][^"\']*\bms-skip\b/i', $embed ) ) {
					return $embed;
				}

				// Check surrounding context for existing wrapper.
				$pos = strpos( $html, $embed );
				if ( false !== $pos ) {
					$before = substr( $html, max( 0, $pos - 200 ), min( 200, $pos ) );
					if ( str_contains( $before, 'ms-protected-player' ) && ! str_contains( $before, '</div>' ) ) {
						return $embed;
					}
				}

				// Self-hosted <video> tags carry no platform ID, so identify them by
				// their source URL — which is what the CPT stores for them.
				$src_url           = ( 'self' === $platform )
					? self::extract_source_url( $embed )
					: ( $matches[1] ?? '' );
				$platform_video_id = self::extract_video_id( $src_url, $platform );
				$protection        = get_option( 'ms_default_protection', 'standard' );

				$video_post_id = self::find_video_post_id( $platform, $platform_video_id, $src_url );

				// Not a MediaShield-managed video. Leave the markup exactly as the
				// theme or another plugin emitted it: wrapping a video we do not own
				// would burn a viewer watermark onto someone else's embed, and for a
				// self-hosted tag it would swap the element for a container whose
				// source URL we never captured — destroying playback outright.
				if ( 0 === $video_post_id ) {
					return $embed;
				}

				$player_type = apply_filters( 'mediashield_player_type', 'standard', $video_post_id );

				// Flag Shaka Player needed for self/bunny.
				if ( in_array( $platform, array( 'self', 'bunny' ), true ) ) {
					do_action( 'mediashield_needs_shaka' );
				}

				$fullscreen_label = esc_attr__( 'Fullscreen', 'mediashield' );

				// Per-video player feature overrides (same logic as Renderer.php).
				$video_overrides = array();
				$overrides_attr  = '';
				if ( $video_post_id > 0 ) {
					$override_keys = array(
						'_ms_player_speed'     => 'speedControl',
						'_ms_player_keyboard'  => 'keyboard',
						'_ms_player_resume'    => 'resume',
						'_ms_player_sticky'    => 'sticky',
						'_ms_player_endscreen' => 'endscreen',
					);
					foreach ( $override_keys as $meta_key => $js_key ) {
						$val = get_post_meta( $video_post_id, $meta_key, true );
						if ( 'on' === $val || 'off' === $val ) {
							$video_overrides[ $js_key ] = ( 'on' === $val );
						}
					}
					$es_text = get_post_meta( $video_post_id, '_ms_player_endscreen_text', true );
					$es_url  = get_post_meta( $video_post_id, '_ms_player_endscreen_url', true );
					if ( ! empty( $es_text ) ) {
						$video_overrides['endscreenText'] = $es_text;
					}
					if ( ! empty( $es_url ) ) {
						$video_overrides['endscreenUrl'] = $es_url;
					}
					if ( ! empty( $video_overrides ) ) {
						$overrides_attr = ' data-player-overrides="' . esc_attr( wp_json_encode( $video_overrides ) ) . '"';
					}
				}

				// Auto-wrapped players must advertise the access type exactly as
				// Renderer::render() does, or a gated video looks ungated on this
				// path: the client never learns it should show the email gate, so
				// the visitor just meets a player that refuses to stream. Same
				// filter, same attribute, so both render paths agree.
				$access_type      = (string) apply_filters( 'mediashield_player_access_type', '', $video_post_id );
				$access_type_attr = '' !== $access_type
					? ' data-access-type="' . esc_attr( $access_type ) . '"'
					: '';

				return sprintf(
					'<div class="ms-protected-player" data-video-id="%d" data-platform="%s" data-protection-level="%s" data-player-type="%s"%s>'
					. '<div class="ms-player-target" data-platform-video-id="%s" data-source-url="%s" data-stream-url=""%s></div>'
					. '<canvas class="ms-watermark-canvas" aria-hidden="true"></canvas>'
					. '<div class="ms-protection-overlay"></div>'
					. '<button class="ms-fullscreen-btn" aria-label="%s">' . \MediaShield\Support\Icons::svg( 'maximize', array( 'size' => 20 ) ) . '</button>'
					. '</div>',
					$video_post_id,
					esc_attr( $platform ),
					esc_attr( $protection ),
					esc_attr( $player_type ),
					$access_type_attr,
					esc_attr( $platform_video_id ),
					esc_url( $src_url ),
					$overrides_attr,
					$fullscreen_label
				);
			},
			$html
		);
	}

	/**
	 * Extract platform video ID from an embed URL.
	 *
	 * @param string $url      Embed URL.
	 * @param string $platform Platform identifier.
	 * @return string Video ID or empty string.
	 */
	private static function extract_video_id( string $url, string $platform ): string {
		switch ( $platform ) {
			case 'youtube':
				if ( preg_match( '/embed\/([a-zA-Z0-9_-]+)/', $url, $m ) ) {
					return $m[1];
				}
				return '';

			case 'vimeo':
				if ( preg_match( '/video\/(\d+)/', $url, $m ) ) {
					return $m[1];
				}
				return '';

			case 'bunny':
				if ( preg_match( '/embed\/(\d+)\/([a-f0-9-]+)/', $url, $m ) ) {
					return $m[1] . '/' . $m[2];
				}
				return '';

			case 'wistia':
				// For Wistia, the video ID is in the class name (captured by regex group 1).
				return $url; // Already the hashed_id from regex capture.

			case 'self':
				return ''; // Self-hosted is matched on its source URL, not an ID.

			default:
				return '';
		}
	}

	/**
	 * Pull the source URL out of a matched self-hosted <video> element.
	 *
	 * Handles both `<video src="...">` and `<video><source src="..."></video>`.
	 *
	 * @since 1.2.0
	 *
	 * @param string $embed The full matched <video> element.
	 * @return string Source URL, or empty string when none is present.
	 */
	private static function extract_source_url( string $embed ): string {
		if ( preg_match( '/<video[^>]*\ssrc=["\']([^"\']+)["\']/i', $embed, $m ) ) {
			return $m[1];
		}

		if ( preg_match( '/<source[^>]*\ssrc=["\']([^"\']+)["\']/i', $embed, $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * Resolve an embed to the mediashield_video CPT that manages it.
	 *
	 * Platform embeds are matched on their platform video ID; self-hosted videos
	 * have no such ID and are matched on their source URL instead. A page can
	 * carry many embeds, so results are memoised per request to keep this off the
	 * N+1 path.
	 *
	 * @since 1.2.0
	 *
	 * @param string $platform          Platform identifier.
	 * @param string $platform_video_id Platform video ID, empty for self-hosted.
	 * @param string $src_url           Source URL.
	 * @return int Video CPT post ID, or 0 when MediaShield does not manage it.
	 */
	private static function find_video_post_id( string $platform, string $platform_video_id, string $src_url ): int {
		static $cache = array();

		if ( 'self' === $platform ) {
			$meta_key   = '_ms_source_url';
			$meta_value = $src_url;
		} else {
			$meta_key   = '_ms_platform_video_id';
			$meta_value = $platform_video_id;
		}

		if ( '' === $meta_value ) {
			return 0;
		}

		$cache_key = $meta_key . '|' . $meta_value;
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$found_posts = get_posts(
			array(
				'post_type'        => 'mediashield_video',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => $meta_key,
						'value' => $meta_value,
					),
				),
			)
		);

		$cache[ $cache_key ] = ! empty( $found_posts ) ? (int) $found_posts[0] : 0;

		return $cache[ $cache_key ];
	}
}
