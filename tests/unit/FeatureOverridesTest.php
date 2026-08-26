<?php
/**
 * Per-video player overrides.
 *
 * The map behind these was written out four times - Renderer, PlayerWrapper,
 * the edit-screen labels and the save handler's key list. That is how "Prevent
 * Forward Seek" shipped as the only player feature with no per-video control
 * (BC#10239799401): it was added to the global settings and to the player, and
 * the other four lists were never updated.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Player\FeatureOverrides;
use WP_UnitTestCase;

/**
 * FeatureOverrides.
 */
class FeatureOverridesTest extends WP_UnitTestCase {

	/**
	 * Video fixture.
	 *
	 * @var int
	 */
	private int $video_id = 0;

	/**
	 * Create a video.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->video_id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Every overridable feature has a label, and every label an override.
	 *
	 * This is the assertion that would have caught the original bug: the two
	 * lists were maintained by hand in different files and drifted.
	 */
	public function test_every_feature_has_a_label(): void {
		$this->assertSame(
			array_keys( FeatureOverrides::map() ),
			array_keys( FeatureOverrides::labels() ),
			'map() and labels() must cover exactly the same features, in the same order.'
		);
	}

	/**
	 * Prevent-forward-seek is overridable per video - the missing one.
	 */
	public function test_prevent_forward_seek_is_overridable(): void {
		$this->assertArrayHasKey( '_ms_player_prevent_forward_seek', FeatureOverrides::map() );
		$this->assertSame( 'preventForwardSeek', FeatureOverrides::map()['_ms_player_prevent_forward_seek'] );
	}

	/**
	 * No override set means the global value applies - the key is absent
	 * rather than sent as false, which would silently override the global.
	 */
	public function test_unset_override_is_absent_not_false(): void {
		$this->assertSame( array(), FeatureOverrides::for_video( $this->video_id ) );
	}

	/**
	 * 'on' and 'off' both override; the tri-state is the point.
	 */
	public function test_on_and_off_both_override(): void {
		update_post_meta( $this->video_id, '_ms_player_prevent_forward_seek', 'on' );
		$this->assertSame(
			array( 'preventForwardSeek' => true ),
			FeatureOverrides::for_video( $this->video_id )
		);

		update_post_meta( $this->video_id, '_ms_player_prevent_forward_seek', 'off' );
		$this->assertSame(
			array( 'preventForwardSeek' => false ),
			FeatureOverrides::for_video( $this->video_id )
		);
	}

	/**
	 * A value that is neither 'on' nor 'off' falls back to the global rather
	 * than being coerced - an empty string is the "Default" option.
	 */
	public function test_junk_value_falls_back_to_global(): void {
		update_post_meta( $this->video_id, '_ms_player_prevent_forward_seek', '' );
		$this->assertSame( array(), FeatureOverrides::for_video( $this->video_id ) );

		update_post_meta( $this->video_id, '_ms_player_prevent_forward_seek', 'maybe' );
		$this->assertSame( array(), FeatureOverrides::for_video( $this->video_id ) );
	}

	/**
	 * A video id of 0 - the auto-wrap path's "not one of ours" case - must not
	 * read meta off post 0.
	 */
	public function test_no_video_yields_no_overrides(): void {
		$this->assertSame( array(), FeatureOverrides::for_video( 0 ) );
	}

	/**
	 * The map is filterable, so an add-on can register its own feature.
	 */
	public function test_map_is_filterable(): void {
		$add = static function ( array $map ): array {
			$map['_ms_player_acme'] = 'acme';
			return $map;
		};
		add_filter( 'mediashield_player_overrides', $add );

		$this->assertContains( '_ms_player_acme', FeatureOverrides::keys() );

		remove_filter( 'mediashield_player_overrides', $add );
	}
}
