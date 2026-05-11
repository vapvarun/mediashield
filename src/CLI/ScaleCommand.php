<?php
/**
 * WP-CLI: Scale-readiness commands for MediaShield.
 *
 * Two commands:
 *   - `wp mediashield scale seed`      — populate a real-shape dataset
 *                                        so benchmarks measure against
 *                                        production-scale tables.
 *   - `wp mediashield scale benchmark` — run hot-path queries and
 *                                        report per-query timings + a
 *                                        pass/fail against the budget.
 *   - `wp mediashield scale teardown`  — remove all seeded synthetic data.
 *
 * Mandatory for every Wbcom plugin. Lifted from wp-plugin-onboard skill —
 * customise the BUDGETS_MS map and the queries inside `benchmark()` for
 * MediaShield's hot paths. Keep the seed/teardown shape so CI can compose
 * the same gate across plugins.
 *
 * MediaShield hot paths gated here:
 *   - SessionController::heartbeat       — every 30s per active stream (PK lookup, ≤5ms)
 *   - AccessControl::can_watch           — per stream start (≤5ms)
 *   - StreamController::stream_video     — per video request (HMAC verify, ≤30ms)
 *   - MilestoneTracker::record           — per milestone hit (idempotency check, ≤20ms)
 *   - AnalyticsController::get_overview  — per dashboard load (aggregation, ≤30ms)
 *
 * @package MediaShield
 * @since   1.0.0
 */

namespace MediaShield\CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Seed-and-measure scale benchmark — required to claim production-readiness.
 *
 * Default budgets are calibrated for a typical Local-by-Flywheel MySQL 8 box;
 * tighten for production hosts with dedicated MySQL.
 */
final class ScaleCommand {

	/**
	 * Per-query timing budgets (milliseconds). A query exceeding its budget
	 * fails the benchmark gate.
	 *
	 * @var array<string,float>
	 */
	private const BUDGETS_MS = array(
		// Heartbeat — single-row UPDATE on PK (id, session_token).
		'session_heartbeat'        => 5.0,
		// can_watch — concurrent-stream count (indexed scan on user_id + is_active).
		'access_can_watch'         => 5.0,
		// Stream handoff — HMAC verify + session lookup (indexed scan).
		'stream_signed_lookup'     => 30.0,
		// Milestone record — INSERT with UNIQUE (video_id, user_id, milestone_pct) idempotency.
		'milestone_record'         => 20.0,
		// Analytics overview — total sessions/users/milestones aggregation.
		'analytics_overview'       => 30.0,
	);

	/**
	 * Synthetic seed user IDs start at this value. Cleanup: WHERE user_id >= BASE_UID.
	 */
	private const BASE_UID = 1000000;

	/**
	 * Synthetic video IDs start here so we don't collide with real CPT posts.
	 */
	private const BASE_VID = 9000000;

	/**
	 * Seed a real-shape dataset.
	 *
	 * Default profile: 10000 users × 10 watch_sessions × 4 milestones each
	 *                  → ~100k sessions + ~400k milestones across
	 *                    ms_watch_sessions + ms_milestones.
	 *
	 * ## OPTIONS
	 *
	 * [--users=<n>]
	 * : Number of synthetic users. Default: 10000.
	 *
	 * [--sessions-per-user=<n>]
	 * : Watch sessions per user. Default: 10.
	 *
	 * [--milestones-per-session=<n>]
	 * : Milestones per session (1-4 — 25/50/75/100). Default: 4.
	 *
	 * [--batch=<n>]
	 * : Insert batch size. Default: 5000.
	 *
	 * ## EXAMPLES
	 *
	 *   wp mediashield scale seed
	 *   wp mediashield scale seed --users=10000 --sessions-per-user=10
	 *   wp mediashield scale seed --users=100000 --sessions-per-user=5
	 *
	 * @param array $args       Positional args (unused).
	 * @param array $assoc_args Named args.
	 */
	public function seed( array $args, array $assoc_args ): void {
		global $wpdb;

		$user_count       = max( 1, (int) ( $assoc_args['users'] ?? 10000 ) );
		$sessions_per_u   = max( 1, (int) ( $assoc_args['sessions-per-user'] ?? 10 ) );
		$milestones_per_s = max( 1, min( 4, (int) ( $assoc_args['milestones-per-session'] ?? 4 ) ) );
		$batch_size       = max( 100, (int) ( $assoc_args['batch'] ?? 5000 ) );

		$total_sessions   = $user_count * $sessions_per_u;
		$total_milestones = $total_sessions * $milestones_per_s;

		\WP_CLI::line( "Seeding {$user_count} users × {$sessions_per_u} sessions = {$total_sessions} sessions + {$total_milestones} milestones…" );

		$started        = microtime( true );
		$sessions_table = $wpdb->prefix . 'ms_watch_sessions';
		$milestones_tbl = $wpdb->prefix . 'ms_milestones';

		// Sanity: tables must exist before seeding.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$has_sessions   = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$sessions_table}'" );
		$has_milestones = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$milestones_tbl}'" );
		// phpcs:enable
		if ( ! $has_sessions || ! $has_milestones ) {
			\WP_CLI::error( "Required tables missing ({$sessions_table}, {$milestones_tbl}). Activate the plugin first." );
			return;
		}

		// Pick a video CPT to point at; create a synthetic one if none exists.
		$video_id = (int) get_posts(
			array(
				'post_type'   => 'mediashield_video',
				'numberposts' => 1,
				'fields'      => 'ids',
				'post_status' => 'any',
			)
		)[0] ?? 0;

		if ( $video_id <= 0 ) {
			$video_id = wp_insert_post(
				array(
					'post_type'   => 'mediashield_video',
					'post_title'  => 'ScaleCommand synthetic video',
					'post_status' => 'publish',
				)
			);
			if ( ! $video_id || is_wp_error( $video_id ) ) {
				\WP_CLI::error( 'Could not create a synthetic mediashield_video CPT — check post type registration.' );
				return;
			}
			\WP_CLI::line( "Created synthetic video #{$video_id}." );
		} else {
			\WP_CLI::line( "Using existing video #{$video_id} for session FK." );
		}

		$now            = current_time( 'mysql', true );
		$session_rows   = 0;
		$milestone_rows = 0;
		$ms_pcts        = array( 25, 50, 75, 100 );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		for ( $u = 0; $u < $user_count; $u++ ) {
			$uid = self::BASE_UID + $u;
			$values  = array();
			$mvalues = array();
			for ( $s = 0; $s < $sessions_per_u; $s++ ) {
				$token   = bin2hex( random_bytes( 16 ) );
				$values[] = $wpdb->prepare(
					'(%d,%d,%s,%s,%s,%s,%s,%s,%s,%d,%d,%d,%d)',
					$video_id,
					$uid,
					$token,
					'127.0.0.1',
					'ScaleCommand/1.0',
					'desktop',
					'chrome',
					$now,
					$now,
					300,
					280,
					93,
					0
				);

				for ( $m = 0; $m < $milestones_per_s; $m++ ) {
					$mvalues[] = $wpdb->prepare(
						'(%d,%d,%d,%s,%d)',
						$video_id,
						$uid,
						$ms_pcts[ $m ],
						$now,
						0
					);
				}

				if ( count( $values ) >= $batch_size ) {
					$wpdb->query(
						"INSERT INTO {$sessions_table} (video_id,user_id,session_token,ip_address,user_agent,device_type,browser,started_at,last_heartbeat,total_seconds,max_position,completion_pct,is_active) VALUES "
						. implode( ',', $values )
					);
					$session_rows += count( $values );
					$values        = array();
				}
				if ( count( $mvalues ) >= $batch_size ) {
					$wpdb->query(
						"INSERT IGNORE INTO {$milestones_tbl} (video_id,user_id,milestone_pct,reached_at,session_id) VALUES "
						. implode( ',', $mvalues )
					);
					$milestone_rows += count( $mvalues );
					$mvalues         = array();
				}
			}
			if ( $values ) {
				$wpdb->query(
					"INSERT INTO {$sessions_table} (video_id,user_id,session_token,ip_address,user_agent,device_type,browser,started_at,last_heartbeat,total_seconds,max_position,completion_pct,is_active) VALUES "
					. implode( ',', $values )
				);
				$session_rows += count( $values );
			}
			if ( $mvalues ) {
				$wpdb->query(
					"INSERT IGNORE INTO {$milestones_tbl} (video_id,user_id,milestone_pct,reached_at,session_id) VALUES "
					. implode( ',', $mvalues )
				);
				$milestone_rows += count( $mvalues );
			}

			if ( ( $u + 1 ) % 1000 === 0 ) {
				\WP_CLI::line( sprintf( '  %d / %d users seeded…', $u + 1, $user_count ) );
			}
		}
		// phpcs:enable

		$elapsed = round( microtime( true ) - $started, 1 );
		\WP_CLI::success(
			sprintf(
				'Seeded %d sessions + %d milestones in %ss.',
				$session_rows,
				$milestone_rows,
				$elapsed
			)
		);
		\WP_CLI::line( 'Cleanup: wp mediashield scale teardown' );
	}

	/**
	 * Remove all seeded synthetic data.
	 *
	 * ## EXAMPLES
	 *
	 *   wp mediashield scale teardown
	 *
	 * @param array $args       Positional args (unused).
	 * @param array $assoc_args Named args (unused).
	 */
	public function teardown( array $args, array $assoc_args ): void {
		global $wpdb;
		$base    = self::BASE_UID;
		$deleted = 0;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		foreach ( array( 'ms_watch_sessions', 'ms_watch_sessions_archive', 'ms_milestones' ) as $tbl ) {
			$full = $wpdb->prefix . $tbl;
			if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$full}'" ) ) {
				continue;
			}
			$deleted += (int) $wpdb->query( "DELETE FROM {$full} WHERE user_id >= {$base}" );
		}

		// Drop the synthetic CPT used as the FK target if it has the canonical title.
		$synth = get_posts(
			array(
				'post_type'   => 'mediashield_video',
				'numberposts' => -1,
				'fields'      => 'ids',
				'post_status' => 'any',
				'title'       => 'ScaleCommand synthetic video',
			)
		);
		foreach ( $synth as $pid ) {
			wp_delete_post( $pid, true );
		}
		// phpcs:enable

		\WP_CLI::success( "Removed {$deleted} synthetic rows (uid >= {$base}) + " . count( $synth ) . ' synthetic CPT(s).' );
	}

	/**
	 * Time the hot-path queries against the current dataset.
	 *
	 * Pass criteria: every query under its budget. Fails CI if any query
	 * exceeds the budget — that's the gate for shipping to a large site.
	 *
	 * ## EXAMPLES
	 *
	 *   wp mediashield scale benchmark
	 *
	 * @param array $args       Positional args (unused).
	 * @param array $assoc_args Named args (unused).
	 */
	public function benchmark( array $args, array $assoc_args ): void {
		global $wpdb;
		wp_cache_flush();

		$sessions_table = $wpdb->prefix . 'ms_watch_sessions';
		$milestones_tbl = $wpdb->prefix . 'ms_milestones';

		// Pick a representative seeded user.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$uid = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$sessions_table} WHERE user_id >= %d ORDER BY user_id ASC LIMIT 1",
				self::BASE_UID
			)
		);
		// phpcs:enable
		if ( $uid <= 0 ) {
			\WP_CLI::warning( 'No seeded data — run `wp mediashield scale seed` first.' );
			return;
		}

		// Pick a sample session row to drive heartbeat/stream timings.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, session_token, video_id FROM {$sessions_table} WHERE user_id = %d LIMIT 1",
				$uid
			),
			ARRAY_A
		);
		// phpcs:enable
		if ( ! $row ) {
			\WP_CLI::warning( 'No session row for seeded user.' );
			return;
		}
		$session_id    = (int) $row['id'];
		$session_token = (string) $row['session_token'];
		$video_id      = (int) $row['video_id'];

		\WP_CLI::line( "Benchmarking against uid={$uid}, session={$session_id}, video={$video_id}." );
		\WP_CLI::line( str_repeat( '─', 70 ) );

		$results = array();

		// Heartbeat — single-row PK update.
		$results['session_heartbeat'] = self::time_op(
			function () use ( $wpdb, $sessions_table, $session_id ) {
				wp_cache_flush();
				// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$sessions_table} SET last_heartbeat = %s, total_seconds = total_seconds + 30 WHERE id = %d",
						current_time( 'mysql', true ),
						$session_id
					)
				);
				// phpcs:enable
			}
		);

		// can_watch — concurrent-stream count.
		$results['access_can_watch'] = self::time_op(
			function () use ( $wpdb, $sessions_table, $uid, $video_id ) {
				wp_cache_flush();
				// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$sessions_table} WHERE user_id = %d AND is_active = 1",
						$uid
					)
				);
				// phpcs:enable
			}
		);

		// Stream — signed-token lookup + freshness check.
		$results['stream_signed_lookup'] = self::time_op(
			function () use ( $wpdb, $sessions_table, $session_token, $video_id ) {
				wp_cache_flush();
				// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
				$wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, user_id, started_at FROM {$sessions_table} WHERE session_token = %s AND video_id = %d LIMIT 1",
						$session_token,
						$video_id
					)
				);
				// phpcs:enable
			}
		);

		// Milestone record — idempotent INSERT IGNORE on UNIQUE (video_id,user_id,pct).
		$results['milestone_record'] = self::time_op(
			function () use ( $wpdb, $milestones_tbl, $uid, $video_id ) {
				wp_cache_flush();
				// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$milestones_tbl} (video_id, user_id, milestone_pct, reached_at, session_id) VALUES (%d, %d, %d, %s, %d)",
						$video_id,
						$uid,
						25,
						current_time( 'mysql', true ),
						0
					)
				);
				// phpcs:enable
			}
		);

		// Analytics overview — totals.
		$results['analytics_overview'] = self::time_op(
			function () use ( $wpdb, $sessions_table, $milestones_tbl ) {
				wp_cache_flush();
				// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
				$wpdb->get_var( "SELECT COUNT(*) FROM {$sessions_table}" );
				$wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$sessions_table}" );
				$wpdb->get_var( "SELECT COUNT(*) FROM {$milestones_tbl} WHERE milestone_pct = 100" );
				// phpcs:enable
			}
		);

		// Render.
		$failures = 0;
		\WP_CLI::line( str_pad( 'Query', 28 ) . str_pad( 'Time', 12 ) . str_pad( 'Budget', 12 ) . 'Status' );
		\WP_CLI::line( str_repeat( '─', 70 ) );
		foreach ( $results as $key => $ms ) {
			$budget = self::BUDGETS_MS[ $key ] ?? 100.0;
			$pass   = $ms <= $budget;
			if ( ! $pass ) {
				$failures++;
			}
			\WP_CLI::line(
				sprintf(
					'%s%s%s%s',
					str_pad( $key, 28 ),
					str_pad( number_format( $ms, 2 ) . 'ms', 12 ),
					str_pad( number_format( $budget, 1 ) . 'ms', 12 ),
					$pass ? "\033[32mPASS\033[0m" : "\033[31mFAIL (over by " . number_format( $ms - $budget, 2 ) . "ms)\033[0m"
				)
			);
		}
		\WP_CLI::line( str_repeat( '─', 70 ) );
		if ( 0 === $failures ) {
			\WP_CLI::success( 'All queries within budget — production-ready against this dataset.' );
		} else {
			\WP_CLI::error( "{$failures} query(s) over budget — investigate before shipping to a large site." );
		}
	}

	/**
	 * Time a closure in milliseconds.
	 *
	 * @param callable $op Closure to time.
	 * @return float Milliseconds elapsed.
	 */
	private static function time_op( callable $op ): float {
		$started = microtime( true );
		$op();
		return round( ( microtime( true ) - $started ) * 1000, 3 );
	}
}
