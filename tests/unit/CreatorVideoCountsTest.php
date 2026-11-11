<?php
/**
 * A creator's Videos status links count only their own videos
 * (BC#10499835623), by the rule that scopes the list itself.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * Status-link counts on edit.php?post_type=mediashield_video.
 */
class CreatorVideoCountsTest extends WP_UnitTestCase {

	/**
	 * Author granted upload_mediashield.
	 *
	 * @var int
	 */
	private int $author = 0;

	/**
	 * One published video for the author; two published, two drafts and one
	 * trashed for an administrator.
	 */
	public function set_up(): void {
		parent::set_up();
		global $pagenow;

		$admin        = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->author = self::factory()->user->create( array( 'role' => 'author' ) );
		get_userdata( $this->author )->add_cap( 'upload_mediashield' );

		$video = array( 'post_type' => 'mediashield_video' );
		self::factory()->post->create( $video + array( 'post_status' => 'publish', 'post_author' => $this->author ) );
		self::factory()->post->create_many( 2, $video + array( 'post_status' => 'publish', 'post_author' => $admin ) );
		self::factory()->post->create_many( 2, $video + array( 'post_status' => 'draft', 'post_author' => $admin ) );
		self::factory()->post->create( $video + array( 'post_status' => 'trash', 'post_author' => $admin ) );

		$pagenow = 'edit.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		set_current_screen( 'edit-mediashield_video' );
	}

	/**
	 * Leave the admin screen.
	 */
	public function tear_down(): void {
		global $pagenow;
		$pagenow = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * The creator's counts name only the videos the list shows them.
	 */
	public function test_creator_counts_only_their_own_videos(): void {
		wp_set_current_user( $this->author );

		$counts = wp_count_posts( 'mediashield_video', 'readable' );

		$this->assertSame( 1, (int) $counts->publish );
		$this->assertSame( 0, (int) $counts->draft );
		$this->assertSame( 0, (int) $counts->trash );
	}

	/**
	 * Someone who can edit others' posts keeps the site-wide counts.
	 */
	public function test_editor_keeps_every_count(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$counts = wp_count_posts( 'mediashield_video', 'readable' );

		$this->assertSame( 3, (int) $counts->publish );
		$this->assertSame( 2, (int) $counts->draft );
		$this->assertSame( 1, (int) $counts->trash );
	}

	/**
	 * Other screens and other post types are untouched.
	 */
	public function test_other_post_types_keep_their_counts(): void {
		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_current_user( $this->author );

		$this->assertSame( 1, (int) wp_count_posts( 'post' )->publish );
	}
}
