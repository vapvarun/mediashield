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

		$platform         = get_post_meta( $video_id, '_ms_platform', true ) ?: 'self';
		$platform_video_id = get_post_meta( $video_id, '_ms_platform_video_id', true );
		$source_url       = get_post_meta( $video_id, '_ms_source_url', true );
		$stream_url       = get_post_meta( $video_id, '_ms_stream_url', true );
		$protection_level = get_post_meta( $video_id, '_ms_protection_level', true ) ?: 'standard';
		$duration         = (int) get_post_meta( $video_id, '_ms_duration', true );
		$player_type      = apply_filters( 'mediashield_player_type', 'standard', $video_id );

		if ( empty( $source_url ) && empty( $stream_url ) && empty( $platform_video_id ) ) {
			return '';
		}

		// Flag that we need Shaka Player for self-hosted / bunny.
		if ( in_array( $platform, array( 'self', 'bunny' ), true ) ) {
			do_action( 'mediashield_needs_shaka' );
		}

		ob_start();
		?>
		<div class="ms-protected-player <?php echo $wrapper_attrs ? '' : ''; ?>"
			data-video-id="<?php echo esc_attr( $video_id ); ?>"
			data-platform="<?php echo esc_attr( $platform ); ?>"
			data-protection-level="<?php echo esc_attr( $protection_level ); ?>"
			data-player-type="<?php echo esc_attr( $player_type ); ?>"
			<?php if ( $wrapper_attrs ) { echo $wrapper_attrs; } // phpcs:ignore ?>
		>
			<div class="ms-player-target"
				data-platform-video-id="<?php echo esc_attr( $platform_video_id ); ?>"
				data-source-url="<?php echo esc_url( $source_url ); ?>"
				data-stream-url="<?php echo esc_url( $stream_url ); ?>"
				data-duration="<?php echo esc_attr( $duration ); ?>">
			</div>
			<canvas class="ms-watermark-canvas"></canvas>
			<div class="ms-protection-overlay"></div>
			<button class="ms-fullscreen-btn" aria-label="<?php esc_attr_e( 'Fullscreen', 'mediashield' ); ?>" title="<?php esc_attr_e( 'Fullscreen', 'mediashield' ); ?>">
				<span class="dashicons dashicons-fullscreen-alt"></span>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}
}
