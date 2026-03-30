<?php
/**
 * [mediashield] shortcode handler.
 *
 * Usage: [mediashield id=123]
 * Renders the same protected player HTML as the Gutenberg block's render.php.
 *
 * @package MediaShield\Block
 */

namespace MediaShield\Block;

class Shortcode {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_shortcode( 'mediashield', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render( $atts ): string {
		$atts = shortcode_atts( array(
			'id' => 0,
		), $atts, 'mediashield' );

		$video_id = (int) $atts['id'];

		if ( $video_id <= 0 ) {
			return '';
		}

		$video = get_post( $video_id );

		if ( ! $video || 'mediashield_video' !== $video->post_type || 'publish' !== $video->post_status ) {
			return '';
		}

		$platform         = get_post_meta( $video_id, '_ms_platform', true ) ?: 'self';
		$source_url       = get_post_meta( $video_id, '_ms_source_url', true );
		$protection_level = get_post_meta( $video_id, '_ms_protection_level', true ) ?: 'standard';
		$player_type      = apply_filters( 'mediashield_player_type', 'standard', $video_id );

		if ( empty( $source_url ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="ms-protected-player"
			data-video-id="<?php echo esc_attr( $video_id ); ?>"
			data-platform="<?php echo esc_attr( $platform ); ?>"
			data-protection-level="<?php echo esc_attr( $protection_level ); ?>"
			data-player-type="<?php echo esc_attr( $player_type ); ?>">
			<div class="ms-player-inner">
				<?php if ( 'self' === $platform ) : ?>
					<video controls controlsList="nodownload" preload="metadata">
						<source src="<?php echo esc_url( $source_url ); ?>" type="video/mp4">
					</video>
				<?php else : ?>
					<iframe
						src="<?php echo esc_url( $source_url ); ?>"
						frameborder="0"
						allow="autoplay; fullscreen; picture-in-picture"
						allowfullscreen>
					</iframe>
				<?php endif; ?>
			</div>
			<canvas class="ms-watermark-canvas"></canvas>
			<div class="ms-protection-overlay"></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
