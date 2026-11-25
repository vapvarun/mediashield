<?php
/**
 * Two players on one page starting together (Bunny end-to-end test, D2).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\SessionManager;
use WP_UnitTestCase;

/**
 * A member's session start survives a deadlock, and other errors still surface.
 *
 * A real deadlock needs two connections racing; here the query the database
 * ended is replaced, once or every time, by a stored procedure that raises
 * the same error InnoDB raises (1213, "Deadlock found ..."), or another one.
 */
class SessionStartDeadlockTest extends WP_UnitTestCase {

	/**
	 * Video under test.
	 *
	 * @var int
	 */
	private $video;

	/**
	 * Member under test.
	 *
	 * @var int
	 */
	private $member;

	/**
	 * How many times the targeted query ran (and failed) per test.
	 *
	 * @var int
	 */
	private $fails = 0;

	/**
	 * The raising procedure. DDL commits, so it is made once for the class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test fixture.
		$wpdb->query( 'DROP PROCEDURE IF EXISTS ms_test_raise' );
		$wpdb->query(
			"CREATE PROCEDURE ms_test_raise( IN code INT )
			BEGIN
				IF code = 1213 THEN
					SIGNAL SQLSTATE '40001' SET MYSQL_ERRNO = 1213, MESSAGE_TEXT = 'Deadlock found when trying to get lock; try restarting transaction';
				ELSE
					SIGNAL SQLSTATE 'HY000' SET MYSQL_ERRNO = 1105, MESSAGE_TEXT = 'Something else went wrong';
				END IF;
			END"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Drop the procedure.
	 */
	public static function tear_down_after_class(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test fixture.
		$wpdb->query( 'DROP PROCEDURE IF EXISTS ms_test_raise' );
		parent::tear_down_after_class();
	}

	/**
	 * A member and a video.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->video  = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		$this->fails = 0;
	}

	/**
	 * Make the session-table query matching $pattern raise $code, $times times.
	 *
	 * @param string $pattern Regex on the SQL.
	 * @param int    $code    1213 (deadlock) or anything else.
	 * @param int    $times   How many runs fail.
	 */
	private function fail_query( string $pattern, int $code, int $times ): void {
		add_filter(
			'query',
			function ( $sql ) use ( $pattern, $code, $times ) {
				global $wpdb;
				if ( false === strpos( $sql, $wpdb->prefix . 'ms_watch_sessions' ) || ! preg_match( $pattern, $sql ) || $this->fails >= $times ) {
					return $sql;
				}
				++$this->fails;
				return 'CALL ms_test_raise( ' . $code . ' )';
			}
		);
	}

	/**
	 * The insert ended as a deadlock victim once: start() runs again and the
	 * player gets its session, with no database error printed into the reply.
	 *
	 * Fails on HEAD: the failed insert rolled back and start() returned false,
	 * which the REST route turns into "Too many active streams".
	 */
	public function test_a_deadlocked_insert_is_retried(): void {
		$this->fail_query( '/^\s*INSERT INTO/i', 1213, 1 );

		list( $session, $printed ) = $this->start();

		$this->assertIsArray( $session, 'A deadlock was reported to the player as a refusal.' );
		$this->assertFalse( $session['is_resumed'] );
		$this->assertSame( 1, $this->fails );
		$this->assertStringNotContainsString( 'Deadlock', $printed );
		$this->assertSame( 1, $this->sessions() );
	}

	/**
	 * The stream-limit count (SELECT ... FOR UPDATE) is where the Bunny test
	 * page deadlocked: retried the same way.
	 */
	public function test_a_deadlocked_lock_read_is_retried(): void {
		$this->fail_query( '/FOR UPDATE/i', 1213, 1 );

		list( $session, $printed ) = $this->start();

		$this->assertIsArray( $session );
		$this->assertSame( 1, $this->fails );
		$this->assertStringNotContainsString( 'Deadlock', $printed );
		$this->assertSame( 1, $this->sessions() );
	}

	/**
	 * A deadlock every time: a bounded number of tries, then false, and the
	 * last one is reported as wpdb reports any error.
	 */
	public function test_retries_are_bounded_and_the_last_deadlock_is_reported(): void {
		global $wpdb;
		$this->fail_query( '/^\s*INSERT INTO/i', 1213, 99 );

		list( $session, $printed ) = $this->start();

		$this->assertFalse( $session );
		$this->assertSame( 3, $this->fails );
		$this->assertSame( 1, substr_count( $printed, 'Deadlock found' ), 'Only the last try reports the deadlock.' );
		$this->assertFalse( (bool) $wpdb->suppress_errors( false ), 'start() left wpdb errors suppressed.' );
		$this->assertSame( 0, $this->sessions() );
	}

	/**
	 * Any other error is not retried and is not hidden.
	 */
	public function test_other_errors_are_not_retried_or_hidden(): void {
		$this->fail_query( '/^\s*INSERT INTO/i', 1105, 99 );

		list( $session, $printed ) = $this->start();

		$this->assertFalse( $session );
		$this->assertSame( 1, $this->fails, 'A non-deadlock error was retried.' );
		$this->assertStringContainsString( 'Something else went wrong', $printed, 'A non-deadlock error was hidden.' );
	}

	/**
	 * Start the member's session with wpdb showing errors, as on a site with
	 * WP_DEBUG display on, and return the result and what was printed.
	 *
	 * @return array{0: array|false, 1: string}
	 */
	private function start(): array {
		global $wpdb;
		$shown = $wpdb->show_errors( true );
		$quiet = $wpdb->suppress_errors( false );
		$log   = ini_set( 'error_log', '/dev/null' ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- keeps wpdb's error_log() copy out of the test output.
		ob_start();
		try {
			$session = SessionManager::start( $this->video, $this->member, '203.0.113.7', 'UA-D2' );
		} finally {
			$printed = (string) ob_get_clean();
			ini_set( 'error_log', (string) $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- restores the setting changed above.
			$wpdb->show_errors( $shown );
		}
		$wpdb->suppress_errors( $quiet );
		return array( $session, $printed );
	}

	/**
	 * This member's session rows.
	 *
	 * @return int
	 */
	private function sessions(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test read.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ms_watch_sessions WHERE user_id = %d", $this->member ) );
	}
}
