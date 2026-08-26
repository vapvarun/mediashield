<?php
/**
 * Per-video overrides of the global player settings.
 *
 * @package MediaShield\Player
 */

namespace MediaShield\Player;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one list of player features a video can override, and how to read them.
 *
 * WHY THIS EXISTS
 *
 * The map of override meta key to frontend config key was written out three
 * times: in `Player\Renderer`, in `Player\PlayerWrapper`, and (as labels) in
 * `CPT\VideoPostType`, with a fourth copy of just the keys in the save handler.
 * Four copies of one list is how "Prevent Forward Seek" ended up as the only
 * player feature with no per-video control - it was added to the global
 * settings and to the player, and nobody updated the other lists (BC#10239799401).
 *
 * It is also the same shape as two bugs already fixed this release: the
 * protection level had five copies that disagreed, and the adaptive-platform
 * check had two. Adding a sixth feature to two hardcoded maps would have been
 * repeating the mistake rather than fixing it.
 *
 * Add a feature here and it appears on the edit screen, saves, and reaches the
 * player - no other file needs touching.
 */
class FeatureOverrides {

	/**
	 * Override meta key => the key the player reads in its config.
	 *
	 * @return array<string, string>
	 */
	public static function map(): array {
		/**
		 * Filter which player features can be overridden per video.
		 *
		 * @since 1.3.0
		 *
		 * @param array<string, string> $map Meta key => frontend config key.
		 */
		return (array) apply_filters(
			'mediashield_player_overrides',
			array(
				'_ms_player_speed'                => 'speedControl',
				'_ms_player_keyboard'             => 'keyboard',
				'_ms_player_resume'               => 'resume',
				'_ms_player_sticky'               => 'sticky',
				'_ms_player_endscreen'            => 'endscreen',
				'_ms_player_prevent_forward_seek' => 'preventForwardSeek',
			)
		);
	}

	/**
	 * Human labels for the edit screen, in the same order as map().
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'_ms_player_speed'                => __( 'Speed Control', 'mediashield' ),
			'_ms_player_keyboard'             => __( 'Keyboard Shortcuts', 'mediashield' ),
			'_ms_player_resume'               => __( 'Resume Playback', 'mediashield' ),
			'_ms_player_sticky'               => __( 'Sticky Player', 'mediashield' ),
			'_ms_player_endscreen'            => __( 'End Screen', 'mediashield' ),
			'_ms_player_prevent_forward_seek' => __( 'Prevent Skipping Ahead', 'mediashield' ),
		);
	}

	/**
	 * The meta keys, for the save handler.
	 *
	 * @return string[]
	 */
	public static function keys(): array {
		return array_keys( self::map() );
	}

	/**
	 * Resolve one video's overrides into the shape the player expects.
	 *
	 * Tri-state: 'on' and 'off' override the global setting, anything else
	 * (including an unset key) means "use the global value".
	 *
	 * @param int $video_id Video CPT post ID.
	 * @return array<string, bool> Frontend config key => value.
	 */
	public static function for_video( int $video_id ): array {
		if ( $video_id <= 0 ) {
			return array();
		}

		$out = array();

		foreach ( self::map() as $meta_key => $js_key ) {
			$val = get_post_meta( $video_id, $meta_key, true );

			if ( 'on' === $val || 'off' === $val ) {
				$out[ $js_key ] = ( 'on' === $val );
			}
		}

		return $out;
	}
}
