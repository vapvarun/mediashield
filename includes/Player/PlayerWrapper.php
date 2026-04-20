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

		// Custom admin-configured patterns.
		$custom_patterns = get_option( 'ms_custom_url_patterns', '' );
		if ( ! empty( $custom_patterns ) ) {
			$patterns = array_filter( array_map( 'trim', explode( "\n", $custom_patterns ) ) );
			foreach ( $patterns as $pattern ) {
				if ( @preg_match( '/' . $pattern . '/', '' ) !== false ) {
					$html = self::wrap_platform(
						$html,
						'/<iframe[^>]*\ssrc=["\']([^"\']*' . $pattern . '[^"\']*)["\'][^>]*><\/iframe>/i',
						'iframe'
					);
				}
			}
		}

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

				// Check surrounding context for existing wrapper.
				$pos = strpos( $html, $embed );
				if ( false !== $pos ) {
					$before = substr( $html, max( 0, $pos - 200 ), min( 200, $pos ) );
					if ( str_contains( $before, 'ms-protected-player' ) && ! str_contains( $before, '</div>' ) ) {
						return $embed;
					}
				}

				// Extract platform video ID from the URL.
				$src_url           = $matches[1] ?? '';
				$platform_video_id = self::extract_video_id( $src_url, $platform );
				$protection        = get_option( 'ms_default_protection', 'standard' );

				// Look up mediashield_video CPT by platform video ID.
				$video_post_id  = 0;
				$untracked_attr = '';
				if ( ! empty( $platform_video_id ) ) {
					$found_posts = get_posts(
						array(
							'post_type'      => 'mediashield_video',
							'posts_per_page' => 1,
							'fields'         => 'ids',
							'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
								array(
									'key'   => '_ms_platform_video_id',
									'value' => $platform_video_id,
								),
							),
						)
					);
					if ( ! empty( $found_posts ) ) {
						$video_post_id = (int) $found_posts[0];
					}
				}

				if ( 0 === $video_post_id ) {
					$untracked_attr = ' data-ms-untracked="1"';
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

				return sprintf(
					'<div class="ms-protected-player" data-video-id="%d" data-platform="%s" data-protection-level="%s" data-player-type="%s"%s>'
					. '<div class="ms-player-target" data-platform-video-id="%s" data-source-url="%s" data-stream-url=""%s></div>'
					. '<canvas class="ms-watermark-canvas" aria-hidden="true"></canvas>'
					. '<div class="ms-protection-overlay"></div>'
					. '<button class="ms-fullscreen-btn" aria-label="%s"><span class="dashicons dashicons-fullscreen-alt"></span></button>'
					. '</div>',
					$video_post_id,
					esc_attr( $platform ),
					esc_attr( $protection ),
					esc_attr( $player_type ),
					$untracked_attr,
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
				return ''; // Self-hosted uses source URL directly.

			default:
				return '';
		}
	}
}
