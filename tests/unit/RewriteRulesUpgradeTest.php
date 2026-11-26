<?php
/**
 * Rewrite rules after an update.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Core\Activator;
use MediaShield\Core\Migrator;
use WP_UnitTestCase;

/**
 * The v13 step drops saved rules so WordPress rebuilds them without /playlist/.
 */
class RewriteRulesUpgradeTest extends WP_UnitTestCase {

	/**
	 * Fails before 1.4.4: a site at v12 kept its /playlist/<slug>/ rule, which
	 * then sent that address to the home page (Basecamp 10545260546).
	 */
	public function test_upgrade_from_v12_drops_the_playlist_route(): void {
		global $wp_rewrite, $wpdb;
		$before = get_option( 'rewrite_rules' );
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		\MediaShield\CPT\PlaylistPostType::register_post_type();

		// What a 1.4.3 site saved: rules with the playlist route.
		update_option( 'rewrite_rules', array( 'playlist/([^/]+)/?$' => 'index.php?mediashield_playlist=$matches[1]' ) );
		update_option( 'ms_db_version', 12 );

		Migrator::run();

		$this->assertFalse( get_option( 'rewrite_rules' ), 'The saved rules are dropped.' );
		$this->assertSame( MEDIASHIELD_DB_VERSION, (int) get_option( 'ms_db_version' ) );

		$rules = $wp_rewrite->wp_rewrite_rules();
		$this->assertEmpty(
			array_filter( array_keys( $rules ), static fn( $rule ) => str_starts_with( $rule, 'playlist/' ) ),
			'Rebuilt rules carry no playlist route.'
		);

		$wp_rewrite->set_permalink_structure( '' );
		update_option( 'rewrite_rules', $before );
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test isolation: dbDelta committed the transaction.
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
	 * A site already at v13 keeps its rules: the step runs once.
	 */
	public function test_site_at_v13_keeps_its_rules(): void {
		$rules = array( 'custom/?$' => 'index.php?custom=1' );
		update_option( 'rewrite_rules', $rules );
		update_option( 'ms_db_version', 13 );

		Migrator::run();

		$this->assertSame( $rules, get_option( 'rewrite_rules' ) );
	}
}
