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
	 * Render the protected player container for a video CPT.
	 *
	 * @param int    $video_id        Video CPT post ID.
	 * @param string $wrapper_attrs   Optional extra wrapper attributes (e.g. from get_block_wrapper_attributes).
	 * @return string HTML output.
	 */
	public static function render( int $video_id, string $wrapper_attrs = '' ): string {
		$video = get_post( $video_id );

		if ( ! $video || 'mediashield_video' !== $video->post_type || 'publish' !== $video->post_status ) {
			return '';
		}

		$platform_raw      = get_post_meta( $video_id, '_ms_platform', true );
		$platform          = ! empty( $platform_raw ) ? $platform_raw : 'self';
		$platform_video_id = get_post_meta( $video_id, '_ms_platform_video_id', true );
		$source_url        = get_post_meta( $video_id, '_ms_source_url', true );
		$stream_url        = get_post_meta( $video_id, '_ms_stream_url', true );
		$protection_raw    = get_post_meta( $video_id, '_ms_protection_level', true );
		$protection_level  = ! empty( $protection_raw ) ? $protection_raw : 'standard';
		$duration          = (int) get_post_meta( $video_id, '_ms_duration', true );
		$player_type       = apply_filters( 'mediashield_player_type', 'standard', $video_id );

		if ( empty( $source_url ) && empty( $stream_url ) && empty( $platform_video_id ) ) {
			return '';
		}

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

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			data-video-id="<?php echo esc_attr( $video_id ); ?>"
			data-platform="<?php echo esc_attr( $platform ); ?>"
			data-protection-level="<?php echo esc_attr( $protection_level ); ?>"
			data-player-type="<?php echo esc_attr( $player_type ); ?>"
			<?php
			if ( $wrapper_attrs ) {
				echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by get_block_wrapper_attributes().
			}
			?>
		>
			<div class="ms-player-target"
				data-platform-video-id="<?php echo esc_attr( $platform_video_id ); ?>"
				data-source-url="<?php echo esc_url( $source_url ); ?>"
				data-stream-url="<?php echo esc_url( $stream_url ); ?>"
				data-duration="<?php echo esc_attr( $duration ); ?>">
			</div>
			<canvas class="ms-watermark-canvas" aria-hidden="true"></canvas>
			<div class="ms-protection-overlay"></div>
			<button class="ms-fullscreen-btn" aria-label="<?php esc_attr_e( 'Fullscreen', 'mediashield' ); ?>" title="<?php esc_attr_e( 'Fullscreen', 'mediashield' ); ?>">
				<span class="dashicons dashicons-fullscreen-alt"></span>
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
