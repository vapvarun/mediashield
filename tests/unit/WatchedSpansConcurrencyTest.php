<?php
/**
 * Two players reporting for one member at once (Bunny end-to-end test, D4).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\WatchedSpans;
use WP_UnitTestCase;

/**
 * WatchedSpans keeps every player's spans when their writes interleave.
 */
class WatchedSpansConcurrencyTest extends WP_UnitTestCase {

	/**
	 * A member id with no user row: user meta needs none, and the
	 * interleaving test commits, so nothing is left behind but meta it deletes.
	 */
	private const MEMBER = 990042;

	/**
	 * Second database connection: the other heartbeat request.
	 *
	 * @var \mysqli|null
	 */
	private $other;

	/**
	 * Close the second connection and remove the committed meta.
	 */
	public function tear_down(): void {
		global $wpdb;
		if ( $this->other ) {
			$this->other->close();
			$this->other = null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test cleanup of committed rows.
		$wpdb->delete( $wpdb->usermeta, array( 'user_id' => self::MEMBER ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit the cleanup (autocommit was switched on).
		$wpdb->query( 'COMMIT' );
		wp_cache_delete( self::MEMBER, 'user_meta' );
		parent::tear_down();
	}

	/**
	 * Fails on 1.4.4 before the fix: player B's report lands between player
	 * A's read and write, and A's write drops it.
	 *
	 * The other request runs on a second connection inside A's write. It
	 * follows the same rule a real request does: it may write only when the
	 * member's lock is free; when it is not, it waits, which here means it
	 * runs right after A returns.
	 */
	public function test_interleaved_writers_keep_both_videos_spans(): void {
		global $wpdb;

		// Real requests run in autocommit; the test case's open transaction
		// would hide each connection's writes from the other.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test transaction control.
		$wpdb->query( 'COMMIT' );
		$wpdb->query( 'SET autocommit = 1' );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$long_ago = time() - 120;
		update_user_meta(
			self::MEMBER,
			WatchedSpans::META_KEY,
			array(
				101 => array(
					's' => array(),
					't' => $long_ago,
				),
				102 => array(
					's' => array(),
					't' => $long_ago,
				),
			)
		);

		$this->other = new \mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
		$other       = $this->other;
		$table       = $wpdb->usermeta;
		// Code that takes no lock leaves every name free, so the other
		// request goes straight in, as it did before the fix.
		$lock        = is_callable( array( WatchedSpans::class, 'lock_name' ) ) ? WatchedSpans::lock_name( self::MEMBER ) : 'ms_spans_unlocked';
		$waited      = false;
		$fired       = false;

		$hook = static function ( $check, $object_id, $meta_key ) use ( $other, $table, $lock, &$waited, &$fired ) {
			if ( $fired || self::MEMBER !== (int) $object_id || WatchedSpans::META_KEY !== $meta_key ) {
				return $check;
			}
			$fired = true;

			// Request B's turn while A is between its read and its write.
			$got = $other->query( "SELECT GET_LOCK( '" . $other->real_escape_string( $lock ) . "', 0 )" )->fetch_row()[0];
			if ( '1' !== (string) $got ) {
				$waited = true;
				return $check;
			}
			$key  = $other->real_escape_string( WatchedSpans::META_KEY );
			$row  = $other->query( "SELECT meta_value FROM {$table} WHERE user_id = " . self::MEMBER . " AND meta_key = '{$key}'" )->fetch_row();
			$all  = maybe_unserialize( $row[0] );
			$all[102]['s'] = array( array( 0.0, 20.0 ) );
			$other->query( "UPDATE {$table} SET meta_value = '" . $other->real_escape_string( maybe_serialize( $all ) ) . "' WHERE user_id = " . self::MEMBER . " AND meta_key = '{$key}'" );
			$other->query( "SELECT RELEASE_LOCK( '" . $other->real_escape_string( $lock ) . "' )" );
			return $check;
		};
		add_filter( 'update_user_metadata', $hook, 10, 3 );

		WatchedSpans::record( self::MEMBER, 101, array( array( 0, 30 ) ), 240.0, 30.0 );

		// B waited for A's lock; now it runs, as the real request would.
		if ( $waited ) {
			WatchedSpans::record( self::MEMBER, 102, array( array( 0, 20 ) ), 240.0, 20.0 );
		}
		remove_filter( 'update_user_metadata', $hook, 10 );

		wp_cache_delete( self::MEMBER, 'user_meta' );
		$this->assertSame( 30.0, WatchedSpans::seconds( self::MEMBER, 101 ) );
		$this->assertSame( 20.0, WatchedSpans::seconds( self::MEMBER, 102 ), 'Player B\'s spans were overwritten.' );
		$this->assertTrue( $waited, 'The other request found the member lock free while a write was in progress.' );
	}

	/**
	 * Fails before the fix: record() and start() worked from this request's
	 * cached copy, so a write another request made since was overwritten.
	 */
	public function test_record_and_start_read_the_record_fresh(): void {
		global $wpdb;

		update_user_meta(
			self::MEMBER,
			WatchedSpans::META_KEY,
			array(
				101 => array(
					's' => array(),
					't' => time() - 120,
				),
			)
		);
		get_user_meta( self::MEMBER, WatchedSpans::META_KEY, true ); // Primes this request's cache.

		// Another request saves video 102's spans behind this request's cache.
		$theirs = array(
			101 => array(
				's' => array(),
				't' => time() - 120,
			),
			102 => array(
				's' => array( array( 0.0, 15.0 ) ),
				't' => time(),
			),
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simulates another request's write.
		$wpdb->update(
			$wpdb->usermeta,
			array( 'meta_value' => maybe_serialize( $theirs ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			array(
				'user_id'  => self::MEMBER,
				'meta_key' => WatchedSpans::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
			)
		);

		WatchedSpans::record( self::MEMBER, 101, array( array( 0, 30 ) ), 240.0, 30.0 );
		$this->assertSame( 15.0, WatchedSpans::seconds( self::MEMBER, 102 ), 'record() overwrote a newer write.' );

		// The same for start(): another request adds video 103 behind the cache.
		$theirs        = get_user_meta( self::MEMBER, WatchedSpans::META_KEY, true );
		$theirs[103]   = array(
			's' => array( array( 0.0, 5.0 ) ),
			't' => time(),
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simulates another request's write.
		$wpdb->update(
			$wpdb->usermeta,
			array( 'meta_value' => maybe_serialize( $theirs ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			array(
				'user_id'  => self::MEMBER,
				'meta_key' => WatchedSpans::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
			)
		);

		WatchedSpans::start( self::MEMBER, 104 );
		wp_cache_delete( self::MEMBER, 'user_meta' );
		$this->assertSame( 5.0, WatchedSpans::seconds( self::MEMBER, 103 ), 'start() overwrote a newer write.' );
		$this->assertSame( 30.0, WatchedSpans::seconds( self::MEMBER, 101 ) );
	}
}
