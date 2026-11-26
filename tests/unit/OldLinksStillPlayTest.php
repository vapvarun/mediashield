<?php
/**
 * A link 1.4.3 saved with a platform is not "No source" (ponytail #4).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoStatus;
use WP_UnitTestCase;

/**
 * 1.4.3 saved any unrecognised link as a video file and plays it; the v14
 * backfill flagged those videos No source and took them out of the choosers.
 */
class OldLinksStillPlayTest extends WP_UnitTestCase {

	/**
	 * A video as 1.4.3 saved it: the link, and the platform, no platform id.
	 *
	 * @param string $link     Link.
	 * @param string $platform Stored platform ('' for none).
	 */
	private function old_video( string $link, string $platform = 'self' ): int {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $id, '_ms_source_url', $link );
		if ( '' !== $platform ) {
			update_post_meta( $id, '_ms_platform', $platform );
		}
		return $id;
	}

	/**
	 * Fails before: both read No source.
	 */
	public function test_stored_link_with_platform_plays(): void {
		$cdn  = $this->old_video( 'https://media.example.com/watch?id=42' );
		$none = $this->old_video( 'https://media.example.com/watch?id=43', '' );

		$this->assertFalse( VideoStatus::lacks_source( $cdn ), 'Saved with a platform: it plays as in 1.4.3.' );
		$this->assertTrue( VideoStatus::lacks_source( $none ), 'No platform and not recognised: nothing to play.' );

		delete_post_meta( $cdn, VideoStatus::NO_SOURCE );
		VideoStatus::backfill();
		$this->assertSame( '', get_post_meta( $cdn, VideoStatus::NO_SOURCE, true ), 'The backfill agrees.' );
		$this->assertSame( '1', get_post_meta( $none, VideoStatus::NO_SOURCE, true ) );
	}

	/**
	 * Fails before: a site already past v14 kept the wrong flag.
	 */
	public function test_upgrade_clears_the_flag_v14_set(): void {
		$cdn = $this->old_video( 'https://media.example.com/watch?id=44' );
		update_post_meta( $cdn, VideoStatus::NO_SOURCE, '1' );
		update_option( 'ms_db_version', 16 );

		\MediaShield\Core\Migrator::run();

		$this->assertSame( '', get_post_meta( $cdn, VideoStatus::NO_SOURCE, true ) );
		$this->assertSame( MEDIASHIELD_DB_VERSION, (int) get_option( 'ms_db_version' ) );
	}
}
