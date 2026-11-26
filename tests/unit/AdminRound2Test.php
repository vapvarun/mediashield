<?php
/**
 * QA round 2 on Reign: one "which links work" sentence, and only ready
 * videos offered in choosers.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoStatus;
use MediaShield\Support\Platforms;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Each test fails on the code before the fix (noted per test).
 */
class AdminRound2Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		\MediaShield\CPT\VideoPostType::register_meta();
		\MediaShield\REST\VideoListController::register_hooks();
		VideoStatus::register();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		remove_all_filters( 'mediashield_plays_bunny' );
		parent::tear_down();
	}

	private function video( string $title, string $source, string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => $status,
				'post_title'  => $title,
			)
		);
		if ( '' !== $source ) {
			update_post_meta( $id, '_ms_source_url', $source );
		}
		VideoStatus::on_save( $id );
		return $id;
	}

	/**
	 * Before: Bunny was named on the refusal whenever Pro was loaded and the
	 * edit screen hint never named it, so screens disagreed.
	 */
	public function test_every_surface_names_bunny_from_one_answer(): void {
		$this->assertStringNotContainsString( 'Bunny', Platforms::unplayable_message() );

		add_filter( 'mediashield_plays_bunny', '__return_true' );
		$this->assertStringContainsString( 'Bunny Stream', Platforms::unplayable_message() );
		$this->assertStringContainsString( 'Bunny Stream', Platforms::link_hint() );

		$video = self::factory()->post->create_and_get( array( 'post_type' => 'mediashield_video', 'post_status' => 'auto-draft' ) );
		ob_start();
		\MediaShield\CPT\VideoPostType::render_source_meta_box( $video );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( esc_html( Platforms::link_hint() ), $html, 'The edit screen uses the same sentence.' );
	}

	/**
	 * Before: ms_state=ready widened status=publish to private too, and the
	 * picker did not ask for ready at all.
	 */
	public function test_picker_lists_only_published_videos_with_a_source(): void {
		$ready   = $this->video( 'Ready one', 'https://vimeo.com/76979871' );
		$this->video( 'BAD LINK TEST', '' );
		$this->video( 'Draft one', 'https://youtu.be/jNQXAC9IVRw', 'draft' );
		$this->video( 'Private one', 'https://youtu.be/jNQXAC9IVRw', 'private' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos' );
		$request->set_param( 'status', 'publish' );
		$request->set_param( 'ms_state', 'ready' );
		$request->set_param( '_fields', 'id,title,ms_card' );
		$ids = wp_list_pluck( rest_do_request( $request )->get_data(), 'id' );

		$this->assertSame( array( $ready ), $ids );
	}

	/**
	 * Before: no answer ([]), so Learnomy had no MediaShield read to use.
	 */
	public function test_library_choices_filter(): void {
		$a = $this->video( 'Alpha lesson clip', 'https://vimeo.com/76979871' );
		$b = $this->video( 'Beta lesson clip', 'https://youtu.be/jNQXAC9IVRw' );
		$this->video( 'BAD LINK TEST', '' );
		$this->video( 'Draft clip', 'https://youtu.be/jNQXAC9IVRw', 'draft' );

		$all = apply_filters( 'mediashield_library_choices', array(), array() );
		$this->assertEqualsCanonicalizing( array( $a, $b ), array_keys( $all ) );
		$this->assertSame( 'Alpha lesson clip', $all[ $a ] );

		$page1 = apply_filters( 'mediashield_library_choices', array(), array( 'per_page' => 1 ) );
		$page2 = apply_filters( 'mediashield_library_choices', array(), array( 'per_page' => 1, 'page' => 2 ) );
		$this->assertCount( 1, $page1 );
		$this->assertCount( 1, $page2 );
		$this->assertNotSame( array_keys( $page1 ), array_keys( $page2 ) );

		$this->assertSame( array( $b ), array_keys( apply_filters( 'mediashield_library_choices', array(), array( 'search' => 'Beta' ) ) ) );

		$capped = VideoStatus::library_choices( array(), array( 'per_page' => 5000 ) );
		$this->assertLessThanOrEqual( VideoStatus::CHOICES_MAX, count( $capped ) );
	}
}
