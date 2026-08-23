<?php
/**
 * Regression tests for analytics retention and GDPR coverage.
 *
 * BC#10217642180 (reports silently lost history at month 25) and
 * BC#10217642097 (erasure and export skipped the archive) share one root
 * cause: rows were moved to a table nothing else knew about.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Cron\Cleanup;
use MediaShield\Privacy\PrivacyEraser;
use WP_UnitTestCase;

/**
 * Retention window, archive restore, and privacy coverage.
 */
class RetentionAndPrivacyTest extends WP_UnitTestCase {

	/**
	 * Video under test.
	 *
	 * @var int
	 */
	private int $video_id = 0;

	/**
	 * Sessions table name.
	 *
	 * @var string
	 */
	private string $sessions = '';

	/**
	 * Archive table name.
	 *
	 * @var string
	 */
	private string $archive = '';

	/**
	 * Prepare fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wpdb;
		$this->sessions = $wpdb->prefix . 'ms_watch_sessions';
		$this->archive  = $wpdb->prefix . 'ms_watch_sessions_archive';

		// Start from empty custom tables. PHPUnit's per-test transaction cannot
		// be relied on here: both archive_old_sessions() and SessionManager wrap
		// their work in their own START TRANSACTION, and MySQL implicitly
		// commits the pending transaction when a new one begins — so rows
		// written through those paths survive the rollback and accumulate
		// across runs. Only ever touches the dedicated test database.
		$wpdb->query( "DELETE FROM {$this->sessions}" );
		$wpdb->query( "DELETE FROM {$this->archive}" );

		$this->video_id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Count rows this test created, by token prefix.
	 *
	 * Whole-table counts are unsafe here: SessionManager::start() runs its own
	 * START TRANSACTION, and MySQL implicitly commits the pending transaction
	 * when a new one begins — so PHPUnit's per-test rollback does not undo rows
	 * written by tests that went through SessionManager. Scoping by token keeps
	 * these assertions about this test's own data.
	 *
	 * @param string $table  Table to count in.
	 * @param string $prefix Session-token prefix.
	 * @return int
	 */
	private function count_rows( string $table, string $prefix ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE session_token LIKE %s", $wpdb->esc_like( $prefix ) . '%' )
		);
	}

	/**
	 * Insert a session row directly.
	 *
	 * @param string $table   Target table.
	 * @param int    $user_id Owner.
	 * @param string $started GMT datetime.
	 * @param string $token   Session token.
	 */
	private function insert_session( string $table, int $user_id, string $started, string $token ): void {
		global $wpdb;

		$wpdb->insert(
			$table,
			array(
				'video_id'       => $this->video_id,
				'user_id'        => $user_id,
				'session_token'  => $token,
				'ip_address'     => '203.0.113.5',
				'user_agent'     => 'Test-UA',
				'device_type'    => 'desktop',
				'browser'        => 'chrome',
				'started_at'     => $started,
				'last_heartbeat' => $started,
				'total_seconds'  => 60,
				'max_position'   => 30,
				'completion_pct' => 50,
				'is_active'      => 0,
			)
		);
	}

	/**
	 * Retention is opt-in. An owner who never chose a policy keeps everything.
	 */
	public function test_archiving_is_off_by_default(): void {
		global $wpdb;

		$this->assertSame( 0, (int) get_option( 'ms_session_retention_months' ), 'Default retention must be "keep everything".' );

		$this->insert_session( $this->sessions, 1, '2019-01-01 00:00:00', 'ret-ancient' );
		Cleanup::archive_old_sessions();

		$this->assertSame(
			1,
			$this->count_rows( $this->sessions, 'ret-ancient' ),
			'Nothing may be archived while retention is unset.'
		);
		$this->assertSame( 0, $this->count_rows( $this->archive, 'ret-ancient' ) );
	}

	/**
	 * With a window set, only rows past it move.
	 */
	public function test_retention_window_archives_only_old_rows(): void {
		global $wpdb;

		update_option( 'ms_session_retention_months', 18 );

		$this->insert_session( $this->sessions, 1, '2019-01-01 00:00:00', 'win-old' );
		$this->insert_session( $this->sessions, 1, gmdate( 'Y-m-d H:i:s' ), 'win-fresh' );

		Cleanup::archive_old_sessions();

		$this->assertSame( 0, $this->count_rows( $this->sessions, 'win-old' ), 'The old row must leave the live table.' );
		$this->assertSame( 1, $this->count_rows( $this->archive, 'win-old' ), 'The old row must land in the archive.' );
		$this->assertSame( 1, $this->count_rows( $this->sessions, 'win-fresh' ), 'A recent row must stay put.' );
	}

	/**
	 * The restore must MOVE rows, not drop them.
	 *
	 * The first implementation used INSERT IGNORE ... SELECT *, which carried
	 * each archived row's original id back with it. AUTO_INCREMENT had since
	 * reissued those ids, so the colliding rows were silently ignored and then
	 * deleted from the archive — a migration written to recover history would
	 * have destroyed it. This asserts the row count, which is what caught it.
	 */
	public function test_restore_moves_rows_even_when_ids_collide(): void {
		global $wpdb;

		// A live row whose id the archived row will deliberately collide with.
		$this->insert_session( $this->sessions, 1, gmdate( 'Y-m-d H:i:s' ), 'coll-live' );
		$existing_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->sessions} WHERE session_token = %s", 'coll-live' ) );

		// Force the archived row onto that exact primary key.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$this->archive} (id, video_id, user_id, session_token, ip_address, user_agent, device_type, browser, started_at, last_heartbeat, total_seconds, max_position, completion_pct, is_active)
				 VALUES (%d, %d, 1, 'coll-archived', '203.0.113.5', 'Test-UA', 'desktop', 'chrome', '2019-01-01 00:00:00', '2019-01-01 00:00:00', 1800, 30, 50, 0)",
				$existing_id,
				$this->video_id
			)
		);

		Cleanup::restore_archived_sessions();

		$this->assertSame(
			0,
			$this->count_rows( $this->archive, 'coll-archived' ),
			'The archive should be drained.'
		);
		$this->assertSame(
			1,
			$this->count_rows( $this->sessions, 'coll-archived' ),
			'The archived row must land in the live table, not vanish.'
		);
		$this->assertSame(
			'1800',
			(string) $wpdb->get_var( $wpdb->prepare( "SELECT total_seconds FROM {$this->sessions} WHERE session_token = %s", 'coll-archived' ) ),
			'Restored history must keep its values.'
		);
	}

	/**
	 * BC#10217642097 — erasure reported success while IP and user agent
	 * survived in the archive indefinitely.
	 */
	public function test_erasure_anonymises_the_archive_too(): void {
		global $wpdb;

		$user = self::factory()->user->create( array( 'user_email' => 'subject@example.test' ) );

		$this->insert_session( $this->sessions, $user, gmdate( 'Y-m-d H:i:s' ), 'live' );
		$this->insert_session( $this->archive, $user, '2019-01-01 00:00:00', 'archived' );

		PrivacyEraser::erase( 'subject@example.test', 1 );

		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->archive} WHERE user_id = %d AND ( ip_address != '' OR user_agent != '' )",
				$user
			)
		);

		$this->assertSame( 0, $remaining, 'Archived personal data must be erased too.' );
	}
}
