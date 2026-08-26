<?php
/**
 * Regression tests for settings that resolved to the wrong value.
 *
 * Each of these shipped: a control the owner set that no code read, a default
 * written out by hand next to the one in the schema, or a per-video override
 * honoured on one render path and ignored on another. They are pinned here
 * because every one of them was invisible - nothing errored, the admin looked
 * correct, and only the viewer saw the wrong behaviour.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Player\Protection;
use MediaShield\Upload\UploadManager;
use WP_UnitTestCase;

/**
 * Protection level, protection config defaults, and upload target resolution.
 */
class SettingsResolutionTest extends WP_UnitTestCase {

	/**
	 * Video under test.
	 *
	 * @var int
	 */
	private int $video_id = 0;

	/**
	 * Create a video fixture.
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
	 * A video with no override follows the owner's global default.
	 *
	 * Import and upload used to stamp the default onto the video as a per-video
	 * override, which meant changing the default later moved nothing.
	 */
	public function test_video_without_override_follows_the_global_default(): void {
		update_option( 'ms_default_protection', 'strict' );

		$this->assertSame( 'strict', Protection::resolve_level( $this->video_id ) );
	}

	/**
	 * An explicit per-video choice beats the global default.
	 */
	public function test_per_video_override_beats_the_global_default(): void {
		update_option( 'ms_default_protection', 'strict' );
		update_post_meta( $this->video_id, '_ms_protection_level', 'standard' );

		$this->assertSame( 'standard', Protection::resolve_level( $this->video_id ) );
	}

	/**
	 * With neither set, the last resort is 'standard' rather than an empty
	 * string - an empty protection level reached the player as "no protection".
	 */
	public function test_missing_option_and_override_fall_back_to_standard(): void {
		delete_option( 'ms_default_protection' );

		$this->assertSame( 'standard', Protection::resolve_level( $this->video_id ) );
		$this->assertSame( 'standard', Protection::default_level() );
	}

	/**
	 * get_config() must agree with the schema when the option row is missing.
	 *
	 * It read `get_option( 'ms_block_keyboard', true )` while the schema
	 * declares false, so on any install whose row was absent keyboard blocking
	 * switched itself on against the documented default.
	 */
	public function test_block_keyboard_default_matches_the_schema(): void {
		delete_option( 'ms_block_keyboard' );

		$config = Protection::get_config();

		$this->assertFalse( $config['block_keyboard'] );
	}

	/**
	 * "Self-hosted" in the upload-target dropdown maps to the real driver name.
	 */
	public function test_upload_target_self_resolves_to_the_self_hosted_driver(): void {
		update_option( 'ms_default_upload_target', 'self' );

		$this->assertSame( 'self_hosted', UploadManager::resolve_default_driver() );
	}

	/**
	 * "Auto" asks, and falls back to self-hosted when nothing answers - which
	 * is what happens on a Free-only install with no platform connections.
	 */
	public function test_upload_target_auto_falls_back_when_nothing_answers(): void {
		update_option( 'ms_default_upload_target', 'auto' );

		$this->assertSame( 'self_hosted', UploadManager::resolve_default_driver() );
	}

	/**
	 * "Auto" uses whatever answers the filter, which is how Pro supplies the
	 * first connected platform.
	 */
	public function test_upload_target_auto_uses_the_filtered_driver(): void {
		update_option( 'ms_default_upload_target', 'auto' );

		$supply = static fn (): string => 'self_hosted';
		add_filter( 'mediashield_default_upload_driver', $supply );

		$this->assertSame( 'self_hosted', UploadManager::resolve_default_driver() );

		remove_filter( 'mediashield_default_upload_driver', $supply );
	}

	/**
	 * A target naming a driver that does not exist must not be handed onward -
	 * the upload would fail on a driver lookup rather than anything meaningful.
	 */
	public function test_unknown_upload_target_falls_back_to_self_hosted(): void {
		update_option( 'ms_default_upload_target', 'not-a-real-driver' );

		$this->assertSame( 'self_hosted', UploadManager::resolve_default_driver() );
	}

	/**
	 * The ninth creation path: the edit screen itself.
	 *
	 * Eight paths were stopped from stamping the site default onto a video as a
	 * per-video override. The edit screen still did it - the metabox
	 * pre-selected Protection::resolve_level(), which returns the default when
	 * no override exists, there was no "Default" option to return to, and the
	 * save handler wrote whatever was posted. So opening a video and pressing
	 * Update, changing nothing, pinned it to the current default forever
	 * (BC#10235758016, QA bounce).
	 *
	 * The registered meta default must stay '' for the same reason: a non-empty
	 * one is returned by get_post_meta() for a row that does not exist, which
	 * makes "unset" indistinguishable from "explicitly set to the default".
	 */
	public function test_absent_override_reads_as_empty_not_as_the_default(): void {
		$raw = get_post_meta( $this->video_id, '_ms_protection_level', true );

		$this->assertSame(
			'',
			$raw,
			'A video with no override must read as empty. A registered default here would '
			. 'make the edit screen stamp it back on the next save.'
		);
	}

	/**
	 * Same trap on the platform key, which did have a registered default of
	 * "self" - so opening any video and saving marked it self-hosted with no
	 * source URL, the state that makes the player hang with no error.
	 */
	public function test_absent_platform_reads_as_empty_not_self(): void {
		$this->assertSame(
			'',
			get_post_meta( $this->video_id, '_ms_platform', true ),
			'An unset platform must not read back as "self".'
		);
	}
}
