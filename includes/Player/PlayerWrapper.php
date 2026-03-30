<?php
/**
 * Detect and wrap video embeds with the MediaShield protection layer.
 *
 * Hooks into template_redirect to buffer output and wrap detected embeds
 * with .ms-protected-player containers. Supports YouTube, Vimeo, Bunny,
 * Wistia, self-hosted <video>, and generic iframes.
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

		// Skip if no potential video content.
		if ( ! preg_match( '/<(iframe|video|div[^>]*wistia)/i', $html ) ) {
			return $html;
		}

		// YouTube iframes (including nocookie variant).
		$html = self::wrap_pattern(
			$html,
			'/<iframe[^>]*\ssrc=["\'][^"\']*(?:youtube\.com\/embed|youtube-nocookie\.com\/embed)[^"\']*["\'][^>]*><\/iframe>/i',
			'youtube'
		);

		// Vimeo iframes.
		$html = self::wrap_pattern(
			$html,
			'/<iframe[^>]*\ssrc=["\'][^"\']*player\.vimeo\.com\/video[^"\']*["\'][^>]*><\/iframe>/i',
			'vimeo'
		);

		// Bunny Stream iframes.
		$html = self::wrap_pattern(
			$html,
			'/<iframe[^>]*\ssrc=["\'][^"\']*iframe\.mediadelivery\.net[^"\']*["\'][^>]*><\/iframe>/i',
			'bunny'
		);

		// Wistia embeds.
		$html = self::wrap_pattern(
			$html,
			'/<div[^>]*class=["\'][^"\']*wistia_embed[^"\']*["\'][^>]*>.*?<\/div>/is',
			'wistia'
		);

		// Self-hosted <video> tags.
		$html = self::wrap_pattern(
			$html,
			'/<video[^>]*>.*?<\/video>/is',
			'self'
		);

		// Custom admin-configured patterns.
		$custom_patterns = get_option( 'ms_custom_url_patterns', '' );
		if ( ! empty( $custom_patterns ) ) {
			$patterns = array_filter( array_map( 'trim', explode( "\n", $custom_patterns ) ) );
			foreach ( $patterns as $pattern ) {
				// Validate regex before use.
				if ( @preg_match( '/' . $pattern . '/', '' ) !== false ) {
					$html = self::wrap_pattern(
						$html,
						'/<iframe[^>]*\ssrc=["\'][^"\']*' . $pattern . '[^"\']*["\'][^>]*><\/iframe>/i',
						'iframe'
					);
				}
			}
		}

		return $html;
	}

	/**
	 * Find matches and wrap them in .ms-protected-player containers.
	 *
	 * Double-wrap prevention: skip elements already inside .ms-protected-player.
	 *
	 * @param string $html     HTML content.
	 * @param string $pattern  Regex pattern to match.
	 * @param string $platform Platform identifier.
	 * @return string Processed HTML.
	 */
	private static function wrap_pattern( string $html, string $pattern, string $platform ): string {
		return preg_replace_callback( $pattern, function ( $matches ) use ( $platform, $html ) {
			$embed = $matches[0];

			// Double-wrap prevention: skip if already inside .ms-protected-player.
			if ( str_contains( $embed, 'ms-protected-player' ) ) {
				return $embed;
			}

			// Check if this match is inside a wrapper div (look at surrounding context).
			$pos = strpos( $html, $embed );
			if ( false !== $pos ) {
				$before = substr( $html, max( 0, $pos - 200 ), min( 200, $pos ) );
				if ( str_contains( $before, 'ms-protected-player' ) && ! str_contains( $before, '</div>' ) ) {
					return $embed;
				}
			}

			$video_id      = 0; // Will be resolved by JS or Gutenberg block.
			$protection    = get_option( 'ms_default_protection', 'standard' );

			/**
			 * Filter the player type for this video.
			 *
			 * Pro overrides to 'drm' for DRM-protected videos.
			 *
			 * @since 1.0.0
			 *
			 * @param string $player_type Player type: 'standard' or 'drm'.
			 * @param int    $video_id    Video CPT post ID (0 if unresolved).
			 */
			$player_type = apply_filters( 'mediashield_player_type', 'standard', $video_id );

			return sprintf(
				'<div class="ms-protected-player" data-video-id="%d" data-platform="%s" data-protection-level="%s" data-player-type="%s">'
				. '<div class="ms-player-inner">%s</div>'
				. '<canvas class="ms-watermark-canvas"></canvas>'
				. '<div class="ms-protection-overlay"></div>'
				. '</div>',
				$video_id,
				esc_attr( $platform ),
				esc_attr( $protection ),
				esc_attr( $player_type ),
				$embed
			);
		}, $html );
	}
}
