<?php
/**
 * Playlist pages after an update from a release whose activation saved rules
 * without the playlist post type.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Core\Activator;
use MediaShield\Core\Migrator;
use WP_UnitTestCase;

/**
 * The v7 step drops the stale rules so WordPress rebuilds them with /playlist/.
 */
class RewriteRulesUpgradeTest extends WP_UnitTestCase {

	/**
	 * Fails before the fix: a site at DB version 6 kept rules with no playlist route.
	 */
	public function test_upgrade_from_v6_rebuilds_rules_with_playlist_route(): void {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		// The suite registered the post type with plain permalinks, which adds no
		// route; register it again as a site with pretty permalinks would.
		\MediaShield\CPT\PlaylistPostType::register_post_type();

		// What a 1.4.0 activation saved: rules built without the playlist type.
		update_option( 'rewrite_rules', array( '(.?.+?)/?$' => 'index.php?pagename=$matches[1]' ) );
		update_option( 'ms_db_version', 6 );

		Migrator::run();

		$this->assertFalse( get_option( 'rewrite_rules' ), 'The stale rules are dropped.' );
		$this->assertSame( MEDIASHIELD_DB_VERSION, (int) get_option( 'ms_db_version' ) );

		$rules = $wp_rewrite->wp_rewrite_rules();
		$this->assertNotEmpty(
			array_filter( array_keys( $rules ), static fn( $rule ) => str_starts_with( $rule, 'playlist/' ) ),
			'Rebuilt rules carry the playlist route.'
		);
	}

	/**
	 * Fails before the fix: re-activating after replacing the files wrote the new
	 * version without running the upgrade steps, so the v6 file claim never queued.
	 */
	public function test_reactivation_runs_pending_upgrade_steps(): void {
		update_option( 'ms_db_version', 5 );
		delete_option( 'ms_file_claims_pending' );
		self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );

		Activator::activate();

		$this->assertNotFalse( get_option( 'ms_file_claims_pending' ), 'The v6 claim job is queued.' );
		$this->assertSame( MEDIASHIELD_DB_VERSION, (int) get_option( 'ms_db_version' ) );
	}

	/**
	 * A site already at v7 keeps its rules: the step runs once.
	 */
	public function test_site_at_v7_keeps_its_rules(): void {
		global $wpdb;
		$before = get_option( 'rewrite_rules' );
		$rules  = array( 'custom/?$' => 'index.php?custom=1' );
		update_option( 'rewrite_rules', $rules );
		update_option( 'ms_db_version', 7 );

		Migrator::run();

		$this->assertSame( $rules, get_option( 'rewrite_rules' ) );

		// Since v8 a v7 site runs the migrator, whose dbDelta commits the test
		// transaction, so the custom rules above would outlive this test and
		// break permalinks in later ones. Put back what was there and commit that.
		update_option( 'rewrite_rules', $before );
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test isolation, see above.
	}
}
