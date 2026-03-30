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
		return preg_replace_callback( $pattern, function ( $matches ) use ( $platform, $html ) {
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
			$src_url          = $matches[1] ?? '';
			$platform_video_id = self::extract_video_id( $src_url, $platform );
			$protection        = get_option( 'ms_default_protection', 'standard' );
			$player_type       = apply_filters( 'mediashield_player_type', 'standard', 0 );

			// Flag Shaka Player needed for self/bunny.
			if ( in_array( $platform, array( 'self', 'bunny' ), true ) ) {
				do_action( 'mediashield_needs_shaka' );
			}

			return sprintf(
				'<div class="ms-protected-player" data-video-id="0" data-platform="%s" data-protection-level="%s" data-player-type="%s">'
				. '<div class="ms-player-target" data-platform-video-id="%s" data-source-url="%s" data-stream-url=""></div>'
				. '<canvas class="ms-watermark-canvas"></canvas>'
				. '<div class="ms-protection-overlay"></div>'
				. '<button class="ms-fullscreen-btn" aria-label="Fullscreen"><span class="dashicons dashicons-fullscreen-alt"></span></button>'
				. '</div>',
				esc_attr( $platform ),
				esc_attr( $protection ),
				esc_attr( $player_type ),
				esc_attr( $platform_video_id ),
				esc_url( $src_url )
			);
		}, $html );
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
				if ( preg_match( '/embed\/([a-zA-Z0-9_-]{11})/', $url, $m ) ) {
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
