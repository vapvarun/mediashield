<?php
/**
 * The "used in" content scan runs only for a response that shows it.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Big-site check (Basecamp 10545260458): every /wp/v2/mediashield-videos
 * listing ran a post_content LIKE scan of up to 500 posts, block pickers
 * included, which never show "used in".
 */
class UsageScanOnlyForListTest extends WP_UnitTestCase {

	/**
	 * SQL statements that scan post content for shortcodes and blocks.
	 *
	 * @var int
	 */
	private int $scans = 0;

	public function set_up(): void {
		parent::set_up();
		// As in a real REST request, so the old code's REST_REQUEST check
		// would prime on the picker request too.
		if ( ! defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress's own constant.
		}
		\MediaShield\REST\VideoListController::register_hooks();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		self::factory()->post->create_many( 3, array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		add_filter(
			'query',
			function ( $sql ) {
				// The post_content scan (VideoUsage::scan() since the no-cap fix).
				if ( false !== strpos( (string) $sql, "post_type NOT IN ( 'revision', 'mediashield_video', 'mediashield_playlist'" ) && false !== strpos( (string) $sql, 'post_content LIKE' ) ) {
					++$this->scans;
				}
				return $sql;
			}
		);
	}

	/**
	 * GET the collection with these params; return the scans it ran.
	 *
	 * @param array $params Query params.
	 */
	private function scans_for( array $params ): int {
		$this->scans = 0;
		// Cold cache (labelled change): since the usage cache, a repeat request reads the cache instead.
		\MediaShield\CPT\VideoUsage::forget();
		$request = new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos' );
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		return $this->scans;
	}

	public function test_picker_request_runs_no_scan(): void {
		$this->assertSame( 0, $this->scans_for( array( '_fields' => 'id,title,ms_card' ) ) );
	}

	public function test_videos_list_request_scans_once(): void {
		$this->assertSame( 1, $this->scans_for( array( 'context' => 'edit' ) ) );
		$this->assertSame( 1, $this->scans_for( array( '_fields' => 'id,title,ms_list' ) ) );
	}
}
