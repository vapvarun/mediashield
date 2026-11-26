<?php
/**
 * HMAC-based video watch session manager.
 *
 * Token format: {session_id}:{video_id}:{user_id}:{created_ts}:{hmac}
 * Validation recomputes HMAC — no DB lookup required.
 *
 * @package MediaShield\Access
 */

namespace MediaShield\Access;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SessionManager
 *
 * HMAC-based video watch session manager.
 *
 * @since 1.0.0
 */
class SessionManager {

	/**
	 * Tries of a member's session start when the database ends it as a deadlock victim.
	 */
	private const DEADLOCK_ATTEMPTS = 3;

	/**
	 * Start or resume a watch session.
	 *
	 * Session dedup: if same user+video has an active session with heartbeat < 5 min ago,
	 * reuse it instead of creating a new row.
	 *
	 * Uses SELECT ... FOR UPDATE inside a transaction to prevent concurrent session
	 * race conditions, and runs that transaction again (up to DEADLOCK_ATTEMPTS
	 * tries) when the database ends it as a deadlock victim.
	 *
	 * @param int    $video_id Video CPT post ID.
	 * @param int    $user_id  User ID.
	 * @param string $ip       Client IP.
	 * @param string $ua       User agent string.
	 * @return array|false|\WP_Error Session data on success; false when the
	 *                               concurrent-stream limit refuses this device;
	 *                               a `mediashield_session_failed` WP_Error when
	 *                               the database could not record the session
	 *                               (a deadlock after every retry, a failed
	 *                               insert), which is not the viewer's doing.
	 */
	public static function start( int $video_id, int $user_id, string $ip = '', string $ua = '' ) {
		global $wpdb;

		$table = "{$wpdb->prefix}ms_watch_sessions";
		$now   = current_time( 'mysql', true );

		// -- Concurrent session limit check (with row locking) --
		$max_concurrent = (int) get_option( 'ms_max_concurrent_streams', 2 );

		// None and Basic never had a stream limit. They open a viewing only to
		// measure a lesson's "Watch X%" (cards 10541776164, 10537102216),
		// which is not protection, so the limit stays off for them.
		if ( self::tracking_only( $video_id ) ) {
			$max_concurrent = PHP_INT_MAX;
		}

		/*
		 * Guests have no identity, and every one of them lands on user_id = 0.
		 * Both queries below key on user_id, so treating a guest like a user
		 * produces two wrong answers:
		 *
		 *   1. The concurrent-stream count becomes a SITE-WIDE guest counter.
		 *      At the default limit of 2, the third simultaneous visitor to a
		 *      public video anywhere on the site is refused.
		 *   2. The dedup lookup matches ANOTHER guest's row, so visitor B is
		 *      handed visitor A's session token and resume position.
		 *
		 * (2) is the worse of the two - it leaks one viewer's progress to the
		 * next. Both only became reachable when guest playback was allowed, so
		 * they are fixed here rather than left for the first public video.
		 */
		//
		// A concurrent-stream limit exists to stop credential sharing. With no
		// account to share there is nothing to enforce, so guests skip it and
		// always get a fresh session. Limiting anonymous viewers would need a
		// real guest identity, which is a feature, not a bug fix.
		$is_guest = ( 0 === $user_id );

		if ( $is_guest ) {
			return self::start_guest_session( $video_id, $ip, $ua, $now );
		}

		// Every player on a page opens its session on load, so two of them
		// often start in the same instant. Each transaction takes range locks
		// on this member's rows and then inserts into the range the other
		// locked, and InnoDB ends one of them as a deadlock victim. MySQL's
		// own advice for a deadlock is to run the transaction again, so it is
		// retried a few times with a short, growing pause. Any other database
		// error is reported as before and not retried.
		for ( $attempt = 1; ; $attempt++ ) {
			$last   = $attempt >= self::DEADLOCK_ATTEMPTS;
			$result = self::start_member_session( $video_id, $user_id, $ip, $ua, $now, $max_concurrent, $last );
			if ( null !== $result ) {
				return $result;
			}
			if ( $last ) {
				return self::could_not_start();
			}
			usleep( $attempt * 50000 + wp_rand( 0, 50000 ) );
		}
	}

	/**
	 * One try at start() for a signed-in member, in one transaction.
	 *
	 * Returns null when the database ended the transaction as a deadlock
	 * victim (it is rolled back, nothing was written) so start() can run it
	 * again. On a try that will be retried, wpdb's own report of the deadlock
	 * is held back, since it would print into the JSON reply; any other error
	 * is reported as wpdb always does. The last try reports everything.
	 *
	 * @param int    $video_id       Video CPT post ID.
	 * @param int    $user_id        Member, never 0.
	 * @param string $ip             Client IP.
	 * @param string $ua             User agent string.
	 * @param string $now            Current GMT datetime in MySQL format.
	 * @param int    $max_concurrent Most other devices allowed.
	 * @param bool   $last           Whether this is the last try.
	 * @return array|false|\WP_Error|null Session data, false (stream limit), WP_Error (a failed insert), null (deadlock, try again).
	 */
	private static function start_member_session( int $video_id, int $user_id, string $ip, string $ua, string $now, int $max_concurrent, bool $last ) {
		global $wpdb;

		$table = "{$wpdb->prefix}ms_watch_sessions";
		$quiet = $last ? null : $wpdb->suppress_errors( true );

		// True when the query just run was ended by a deadlock (rolled back
		// here); any other error is reported now, while it is wpdb's last query.
		$deadlocked = static function () use ( $wpdb, &$quiet ): bool {
			if ( '' === (string) $wpdb->last_error ) {
				return false;
			}
			if ( self::is_deadlock() ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ends the failed transaction.
				$wpdb->query( 'ROLLBACK' );
				return true;
			}
			if ( null !== $quiet ) {
				$wpdb->suppress_errors( $quiet );
				$wpdb->print_error( $wpdb->last_error );
				$wpdb->suppress_errors( true );
			}
			return false;
		};
		// Once the transaction has ended, errors are reported as usual again:
		// what runs next (spans, hooks) is not part of the retry.
		$report = static function () use ( $wpdb, &$quiet ): void {
			if ( null !== $quiet ) {
				$wpdb->suppress_errors( $quiet );
				$quiet = null;
			}
		};

		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction start.
			$wpdb->query( 'START TRANSACTION' );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table with row locking.
			// Count the OTHER devices this account is watching on, not its sessions.
			// The limit exists to stop one login being shared, and every player on
			// a page opens its own session on load - so counting sessions refused
			// the third video on a lesson page before anything played
			// (BC#10370648695). A device is an IP + browser pair; the caller's own
			// is left out, so one viewer is never refused for their own tabs.
			// Only viewings that are playing count: one that played, or one
			// started in the last 15 s (players start a viewing at the first
			// play since 1.4.4 and report within 5 s, so a second device that
			// presses Play at once is still refused). A page left open on
			// another device, or a player from before 1.4.4 that opened its
			// viewing on load and never played, stops counting after 15 s.
			$active_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT ip_address, user_agent) FROM {$table}
				 WHERE user_id = %d AND is_active = 1
				 AND last_heartbeat > DATE_SUB(%s, INTERVAL 5 MINUTE)
				 AND NOT (ip_address = %s AND user_agent = %s)
				 AND ( " . self::played_sql() . ' OR started_at > DATE_SUB(%s, INTERVAL 15 SECOND) )
				 AND ' . self::limited_videos_sql() . ' FOR UPDATE',
					$user_id,
					$now,
					$ip,
					mb_substr( $ua, 0, 500 ),
					$now
				)
			);
			if ( $deadlocked() ) {
				return null;
			}

			// This device's open session on the same video (a reload, a second
			// tab), reused. Only this device's: another device handed it, as
			// before, shared the session and was never counted, so a login could
			// be watched on one device more than the limit (same video, then any).
			// A plain read: its rows are this member's active ones, which the
			// count above has already locked (and their gaps, so no other
			// start can add one). Locking them again through a second index
			// only widened the locks two players on one page could deadlock on.
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table}
				 WHERE video_id = %d AND user_id = %d AND is_active = 1
				 AND ip_address = %s AND user_agent = %s
				 AND last_heartbeat > DATE_SUB(%s, INTERVAL 5 MINUTE)
				 ORDER BY last_heartbeat DESC LIMIT 1",
					$video_id,
					$user_id,
					$ip,
					mb_substr( $ua, 0, 500 ),
					$now
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $deadlocked() ) {
				return null;
			}

			if ( $existing ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction commit.
				$wpdb->query( 'COMMIT' );
				$report();

				WatchedSpans::start( $user_id, $video_id );

				return array(
					'session_token'   => self::generate_token( (int) $existing->id, $video_id, $user_id, $existing->started_at ),
					'session_id'      => (int) $existing->id,
					// The place from the last viewing that played, not this
					// session's own (a reload without play left it at 0).
					'resume_position' => self::resume_enabled( $video_id ) ? self::last_played_position( $video_id, $user_id ) : 0.0,
					'is_resumed'      => true,
				);
			}

			// This device would be one more on top of the others.
			if ( $active_count >= $max_concurrent ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction rollback.
				$wpdb->query( 'ROLLBACK' );
				$report();

				/**
				 * Fires when a concurrent stream limit is reached.
				 *
				 * @since 1.0.0
				 *
				 * @param int $user_id          User ID.
				 * @param int $video_id         Video they tried to watch.
				 * @param int $active_count     Other devices currently watching.
				 * @param int $max_concurrent   Configured limit.
				 */
				do_action( 'mediashield_concurrent_limit_reached', $user_id, $video_id, $active_count, $max_concurrent );

				return false;
			}

			// Only when Resume Playback is enabled (per-video override, then the
			// global setting); otherwise no query and no position to prompt with.
			// Resume from the last viewing that actually played. Every page view
			// opens a session at 0, so "the newest session" lost the place after
			// one visit without pressing play (Basecamp 10531602227).
			$resume_position = self::resume_enabled( $video_id ) ? self::last_played_position( $video_id, $user_id ) : 0.0;

			// Parse device info from user agent.
			$device_type = self::parse_device_type( $ua );
			$browser     = self::parse_browser( $ua );

			// Create session token parts.
			$session_token_raw = wp_generate_password( 32, false );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
			$inserted = $wpdb->insert(
				$table,
				array(
					'video_id'       => $video_id,
					'user_id'        => $user_id,
					'session_token'  => $session_token_raw,
					'ip_address'     => $ip,
					'user_agent'     => mb_substr( $ua, 0, 500 ),
					'device_type'    => $device_type,
					'browser'        => $browser,
					'started_at'     => $now,
					'last_heartbeat' => $now,
					'total_seconds'  => 0,
					'max_position'   => 0,
					'completion_pct' => 0,
					'is_active'      => 1,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%d' )
			);

			if ( ! $inserted ) {
				if ( $deadlocked() ) {
					return null;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction rollback.
				$wpdb->query( 'ROLLBACK' );
				return self::could_not_start();
			}

			$session_id = (int) $wpdb->insert_id;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction commit.
			$wpdb->query( 'COMMIT' );
			$report();
			WatchedSpans::start( $user_id, $video_id );

			$token = self::generate_token( $session_id, $video_id, $user_id, $now );

			/**
			 * Fires when a new watch session starts.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $session_id Session row ID.
			 * @param int    $video_id   Video CPT post ID.
			 * @param int    $user_id    User ID.
			 * @param string $ip         Client IP address.
			 */
			do_action( 'mediashield_session_started', $session_id, $video_id, $user_id, $ip );

			return array(
				'session_token'   => $token,
				'session_id'      => $session_id,
				'resume_position' => $resume_position,
				'is_resumed'      => false,
			);
		} finally {
			$report();
		}
	}

	/**
	 * The answer when the database could not record a session.
	 *
	 * Not the stream limit (start() returns false for that, and the player
	 * says "Too many active streams"): this is a busy or failing database,
	 * so the viewer is asked to try again and nothing blames them.
	 *
	 * @return \WP_Error Code `mediashield_session_failed`, HTTP 503.
	 */
	private static function could_not_start(): \WP_Error {
		return new \WP_Error(
			'mediashield_session_failed',
			__( 'The video could not start. Please try again.', 'mediashield' ),
			array( 'status' => 503 )
		);
	}

	/**
	 * Did wpdb's last query fail because InnoDB ended its transaction as a deadlock victim?
	 *
	 * MySQL error 1213; the message is checked as well for drivers that do
	 * not expose the number.
	 *
	 * @return bool
	 */
	private static function is_deadlock(): bool {
		global $wpdb;
		$dbh = $wpdb->dbh;
		if ( $dbh instanceof \mysqli && 1213 === mysqli_errno( $dbh ) ) { // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_errno -- wpdb keeps only the message; the number is the reliable test.
			return true;
		}
		return false !== stripos( (string) $wpdb->last_error, 'Deadlock found' );
	}

	/**
	 * Start a watch session for an anonymous viewer.
	 *
	 * Reachable only when the operator has turned `ms_require_login` off, which
	 * is the public-marketing-video case. Deliberately does NOT reuse start()'s
	 * dedup / concurrency path: both key on user_id, and every guest is
	 * user_id = 0, so they would collide across unrelated visitors (see the
	 * note in start()).
	 *
	 * Always inserts a fresh row. No dedup lookup, because two guests are not
	 * the same viewer. No resume position, because there is no identity to
	 * resume against - handing a visitor the previous visitor's position would
	 * be a privacy leak, not a feature. No transaction, because with nothing to
	 * lock against there is no race to guard.
	 *
	 * @since 1.3.0
	 *
	 * @param int    $video_id Video CPT post ID.
	 * @param string $ip       Client IP address.
	 * @param string $ua       Client user agent.
	 * @param string $now      Current GMT datetime in MySQL format.
	 * @return array|\WP_Error Session data on success; could_not_start() if the insert failed.
	 */
	private static function start_guest_session( int $video_id, string $ip, string $ua, string $now ) {
		global $wpdb;

		$table = "{$wpdb->prefix}ms_watch_sessions";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$inserted = $wpdb->insert(
			$table,
			array(
				'video_id'       => $video_id,
				'user_id'        => 0,
				'session_token'  => wp_generate_password( 32, false ),
				'ip_address'     => $ip,
				'user_agent'     => mb_substr( $ua, 0, 500 ),
				'device_type'    => self::parse_device_type( $ua ),
				'browser'        => self::parse_browser( $ua ),
				'started_at'     => $now,
				'last_heartbeat' => $now,
				'total_seconds'  => 0,
				'max_position'   => 0,
				'completion_pct' => 0,
				'is_active'      => 1,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%d' )
		);

		if ( ! $inserted ) {
			return self::could_not_start();
		}

		$session_id = (int) $wpdb->insert_id;

		/** This action is documented in includes/Access/SessionManager.php */
		do_action( 'mediashield_session_started', $session_id, $video_id, 0, $ip );

		return array(
			'session_token'   => self::generate_token( $session_id, $video_id, 0, $now ),
			'session_id'      => $session_id,
			'resume_position' => 0.0,
			'is_resumed'      => false,
		);
	}

	/**
	 * Whether Resume Playback should offer a previous position for a video.
	 *
	 * Per-video override (`_ms_player_resume` meta) is tri-state: 'on'/'off' force
	 * the behaviour; anything else (empty/pristine) defers to the global
	 * `ms_player_resume` setting. Mirrors the override resolution the player JS
	 * uses for the other feature toggles.
	 *
	 * @param int $video_id Video CPT post ID.
	 * @return bool True if a resume prompt may be shown.
	 */
	private static function resume_enabled( int $video_id ): bool {
		$override = get_post_meta( $video_id, '_ms_player_resume', true );
		if ( 'on' === $override ) {
			return true;
		}
		if ( 'off' === $override ) {
			return false;
		}
		return (bool) \MediaShield\Core\Settings::get( 'ms_player_resume' );
	}

	/**
	 * Seconds short of the length that still count as the end (the stored
	 * length is whole seconds: a 17.808 s upload is stored as 18).
	 */
	public const END_SLACK = 1.0;

	/**
	 * How far through the video a position is, as a percent (0-100).
	 *
	 * Within END_SLACK of the length is the end, 100%: a stored length is
	 * whole seconds, so an upload played to its last frame (17.808 s of a
	 * stored 18) stopped at 98.9%, never fired the 100% milestone and never
	 * showed as Completed (UX test MSUX-02).
	 *
	 * @since 1.4.4
	 *
	 * @param float $position Position in seconds.
	 * @param float $duration Length in seconds (effective_duration()).
	 * @return float
	 */
	public static function completion( float $position, float $duration ): float {
		if ( $duration <= 0 ) {
			return 0.0;
		}
		if ( $duration > 2 * self::END_SLACK && $position >= $duration - self::END_SLACK ) {
			return 100.0;
		}
		return min( 100.0, ( $position / $duration ) * 100 );
	}

	/**
	 * The duration to measure progress against.
	 *
	 * The stored `_ms_duration` is an anti-spoof floor, not a prerequisite:
	 * with none set, the length the player reports is used. Every detector that
	 * needs a duration asks this, so none silently switches off on a video whose
	 * Duration field was left at 0 (BC#10317812134).
	 *
	 * @param int   $video_id        Video post ID.
	 * @param float $client_duration Duration reported by the player.
	 * @return float
	 */
	public static function effective_duration( int $video_id, float $client_duration ): float {
		$stored = (float) get_post_meta( $video_id, '_ms_duration', true );
		return $stored > 0 ? max( $stored, $client_duration ) : $client_duration;
	}

	/**
	 * Process a heartbeat from the client.
	 *
	 * @param string $token    HMAC session token.
	 * @param float  $position Current playback position in seconds.
	 * @param float  $duration Total video duration in seconds.
	 * @param bool       $playing  Whether video is currently playing.
	 * @param bool       $focused  Whether browser tab is focused.
	 * @param array|null $spans    Stretches played since the last report, [ [start, end], ... ];
	 *                             null from a player that does not report them.
	 * @return bool Success.
	 */
	public static function heartbeat( string $token, float $position, float $duration, bool $playing = true, bool $focused = true, ?array $spans = null ): bool {
		$parsed = self::validate_token( $token );

		if ( ! $parsed ) {
			return false;
		}

		// A place or length below zero is not a place in the video (Basecamp 10526150191).
		$position = max( 0.0, $position );
		$duration = max( 0.0, $duration );

		global $wpdb;
		$table = "{$wpdb->prefix}ms_watch_sessions";
		$now   = current_time( 'mysql', true );

		// Cross-reference the client-reported duration against the stored
		// `_ms_duration` meta. The client value is read from the player API and is
		// trivially spoofable — a tiny `duration` would inflate completion_pct to
		// 100% and trigger false milestones. When an operator has recorded a real
		// duration on the video, treat it as the authoritative lower bound for the
		// denominator so completion can never be over-reported. (If no stored
		// duration exists we fall back to the client value, preserving behaviour
		// for videos whose length is unknown.)
		$effective_duration = self::effective_duration( (int) $parsed['video_id'], $duration );

		$completion_pct = self::completion( $position, $effective_duration );

		// Seconds really watched (Basecamp 10530930729): the stretches the
		// player reports, checked against the time since its last report.
		// A guest (nothing to keep spans against) and a player without span
		// reports (a page cached before 1.4.3) count the time since the last
		// report instead (below).
		// The same write keeps where a member stopped, for the resume offer,
		// from every report: heartbeat, pause-time beats and the end beacon.
		$user_id  = (int) $parsed['user_id'];
		$accepted = $user_id > 0 ? WatchedSpans::record( $user_id, (int) $parsed['video_id'], $spans ?? array(), $effective_duration, $position ) : 0.0;
		// A guest or a player without span reports: the time since the last
		// report, at most one 30 s beat, while playing. A flat 30 per beat
		// stored a 6 s guest viewing as 30 s, since the early beat at 5 s
		// (Free review #5).
		$flat    = null === $spans || $user_id <= 0;
		$watched = $flat ? 0 : (int) round( $accepted );
		$timed   = $flat && $playing ? 1 : 0;

		// Single atomic update with GREATEST for max_position. total_seconds
		// comes before last_heartbeat: MySQL assigns left to right, so it
		// still reads the previous report's time.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table update.
		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET
				total_seconds = total_seconds + %d + IF( %d = 1, LEAST( 30, GREATEST( 0, TIMESTAMPDIFF( SECOND, last_heartbeat, %s ) ) ), 0 ),
				last_heartbeat = %s,
				max_position = GREATEST(max_position, %f),
				completion_pct = %f
			WHERE id = %d AND is_active = 1",
				$watched,
				$timed,
				$now,
				$now,
				$position,
				$completion_pct,
				$parsed['session_id']
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// The video played: if the platform was recorded as refusing it, that
		// is over (SourceStatus; Free review #3).
		if ( $rows > 0 && ( $position > 0 || $accepted > 0 ) ) {
			\MediaShield\CPT\SourceStatus::clear_if_playing( (int) $parsed['video_id'] );
		}

		// Check milestones after updating completion percentage.
		if ( $rows > 0 && $completion_pct > 0 ) {
			\MediaShield\Milestones\MilestoneTracker::check(
				$parsed['video_id'],
				$parsed['user_id'],
				$completion_pct,
				$parsed['session_id']
			);
		}

		return $rows > 0;
	}

	/**
	 * The position to offer this viewer under Resume Playback, or 0.
	 *
	 * The member's last_played_position() when Resume is on for the video. The player reads it from the page (data-resume-position,
	 * Renderer) so it can offer "Resume from m:ss" before any viewing starts;
	 * a viewing starts only at the first play (Basecamp 10545260660).
	 *
	 * @since 1.4.4
	 *
	 * @param int $video_id Video CPT post ID.
	 * @param int $user_id  Viewer.
	 * @return float Seconds.
	 */
	public static function resume_position( int $video_id, int $user_id ): float {
		if ( $user_id <= 0 || ! self::resume_enabled( $video_id ) ) {
			return 0.0;
		}
		return self::last_played_position( $video_id, $user_id );
	}

	/**
	 * Where this viewer stopped last time they watched this video.
	 *
	 * The exact stop point the player last reported, rewinds included, like
	 * YouTube and Netflix (owner decision on Basecamp 10531602227; kept in
	 * WatchedSpans). A member with no recorded stop (viewings from before
	 * 1.4.3) gets the furthest point of their most recent session that
	 * played; a session opened by a page view without play is skipped.
	 * Answers the resume offer; the client still offers it only between 10 s
	 * and 95%. 0 for a guest or a video never played.
	 *
	 * @since 1.4.3
	 *
	 * @param int $video_id Video CPT post ID.
	 * @param int $user_id  Viewer.
	 * @return float Seconds.
	 */
	public static function last_played_position( int $video_id, int $user_id ): float {
		if ( $video_id <= 0 || $user_id <= 0 ) {
			return 0.0;
		}
		$stop = WatchedSpans::stop( $user_id, $video_id );
		if ( null !== $stop ) {
			return $stop;
		}
		global $wpdb;
		$table  = $wpdb->prefix . 'ms_watch_sessions';
		$played = self::played_sql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, idx_video_user; live read.
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT max_position FROM {$table} WHERE video_id = %d AND user_id = %d AND {$played} ORDER BY last_heartbeat DESC LIMIT 1", $video_id, $user_id ) );
	}

	/**
	 * SQL condition for a session that actually played.
	 *
	 * The session/start route is also the access gate, so every page view opens a
	 * session; one that never played (no position, no seconds) is a load,
	 * not a view, and is left out of the analytics totals and averages
	 * (Basecamp 10499286227).
	 *
	 * @param string $alias Table alias, e.g. 's', or '' for none.
	 * @return string
	 */
	public static function played_sql( string $alias = '' ): string {
		$c = '' === $alias ? '' : $alias . '.';
		return "({$c}max_position > 0 OR {$c}total_seconds > 0)";
	}

	/**
	 * Is a viewing of this video only measuring watch time?
	 *
	 * At None and Basic a viewing is opened only for a lesson's watch rule:
	 * no stream limit, no watermark (Basecamp 10541776164, 10537102216).
	 *
	 * @since 1.4.4
	 *
	 * @param int $video_id Video CPT post ID.
	 * @return bool
	 */
	public static function tracking_only( int $video_id ): bool {
		return in_array( \MediaShield\Player\Protection::resolve_level( $video_id ), array( 'none', 'basic' ), true );
	}

	/**
	 * SQL: the session's video is at a level the stream limit covers (Standard, Strict).
	 *
	 * A member measuring a None or Basic lesson on one device is not using
	 * up a stream on another. Reads the video's own level from postmeta
	 * (meta_key index); a video without one follows the site default.
	 *
	 * @return string Condition on video_id.
	 */
	private static function limited_videos_sql(): string {
		global $wpdb;
		$low = in_array( \MediaShield\Player\Protection::default_level(), array( 'none', 'basic' ), true );

		return $low
			? "video_id IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ms_protection_level' AND meta_value IN ( 'standard', 'strict' ) )"
			: "video_id NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ms_protection_level' AND meta_value IN ( 'none', 'basic' ) )";
	}

	/**
	 * SQL for "one viewer" when grouping sessions: the member, or for a guest
	 * the session itself (guests share user_id 0 and are not one person).
	 *
	 * Completion is counted one way on every screen (Basecamp 10545260603):
	 * a viewer's completion of a video is the furthest completion_pct of any
	 * session of theirs that played, and an average is over viewers and
	 * videos, not over page loads. Group by video and this, take
	 * MAX(completion_pct), then average. My Videos shows the same MAX.
	 *
	 * @since 1.4.4
	 *
	 * @param string $alias Table alias, e.g. 's', or '' for none.
	 * @return string
	 */
	public static function viewer_sql( string $alias = '' ): string {
		$c = '' === $alias ? '' : $alias . '.';
		return "(CASE WHEN {$c}user_id = 0 THEN -{$c}id ELSE {$c}user_id END)";
	}

	/**
	 * Has this member watched enough of this video to complete its lesson?
	 *
	 * The one place the "Require X% watched" rule (Settings
	 * `ms_lms_require_watch_pct`) is worked out, for the player, Learnomy and
	 * Pro's LearnDash adapter. Watched is time spent watching, not the
	 * furthest point: the parts played at least once, merged across sittings,
	 * so skipping ahead earns nothing and a rewatch counts once (WatchedSpans;
	 * owner decision on Basecamp 10530930729). Who is exempt is the caller's
	 * call: LearnDash skips site admins, Learnomy follows its own enforcement
	 * mode. For milestones read `ms_milestones`, for the furthest point
	 * `max_position`.
	 *
	 * @since 1.4.3
	 *
	 * @param int $video_id Video CPT post ID.
	 * @param int $user_id  Member; 0 (a guest) has watched nothing.
	 * @return array{required: int, watched: int, met: bool}|null Null when the rule is off or there is no video.
	 */
	public static function watch_requirement( int $video_id, int $user_id ): ?array {
		$required = (int) \MediaShield\Core\Settings::get( 'ms_lms_require_watch_pct' );
		if ( $required <= 0 || $video_id <= 0 ) {
			return null;
		}
		$watched = $user_id > 0 ? WatchedSpans::percent( $user_id, $video_id ) : 0;
		return array(
			'required' => $required,
			'watched'  => $watched,
			'met'      => $watched >= $required,
		);
	}

	/**
	 * End a watch session.
	 *
	 * A position sent with the end (the page is closing) is recorded first,
	 * so leaving before the first heartbeat still keeps the place to resume.
	 *
	 * @param string $token    HMAC session token.
	 * @param float  $position Last playback position in seconds, 0 when unknown.
	 * @param float  $duration Video length in seconds, 0 when unknown.
	 * @param array|null $spans Stretches played since the last report.
	 * @return bool Success.
	 */
	public static function end( string $token, float $position = 0.0, float $duration = 0.0, ?array $spans = null ): bool {
		$parsed = self::validate_token( $token );

		if ( ! $parsed ) {
			return false;
		}

		if ( $position > 0 || $spans ) {
			self::heartbeat( $token, $position, $duration, false, true, $spans ?? array() );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table update.
		$result = $wpdb->update(
			"{$wpdb->prefix}ms_watch_sessions",
			array(
				'is_active'      => 0,
				'last_heartbeat' => current_time( 'mysql', true ),
			),
			array( 'id' => $parsed['session_id'] ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( $result ) {
			/**
			 * Fires when a watch session ends.
			 *
			 * @since 1.0.0
			 *
			 * @param int $session_id Session ID.
			 * @param int $video_id   Video CPT post ID.
			 * @param int $user_id    User ID.
			 */
			do_action( 'mediashield_session_ended', $parsed['session_id'], $parsed['video_id'], $parsed['user_id'] );
		}

		return (bool) $result;
	}

	/**
	 * Revoke all active sessions for a user (bulk access revocation).
	 *
	 * @param int $user_id User ID.
	 * @return int Number of sessions revoked.
	 */
	public static function revoke_user( int $user_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table bulk update.
		$count = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}ms_watch_sessions
			 SET is_active = 0, last_heartbeat = %s
			 WHERE user_id = %d AND is_active = 1",
				current_time( 'mysql', true ),
				$user_id
			)
		);

		if ( $count > 0 ) {
			/**
			 * Fires when all sessions for a user are revoked.
			 *
			 * @since 1.0.0
			 *
			 * @param int $user_id User ID.
			 * @param int $count   Number of sessions revoked.
			 */
			do_action( 'mediashield_user_access_revoked', $user_id, $count );
		}

		return $count;
	}

	/**
	 * Generate an HMAC session token.
	 *
	 * Uses pipe delimiter to avoid conflicts with datetime colons/spaces.
	 * Token format: session_id|video_id|user_id|unix_ts|hmac
	 *
	 * @param int    $session_id Session row ID.
	 * @param int    $video_id   Video CPT post ID.
	 * @param int    $user_id    User ID.
	 * @param string $created_ts Created timestamp (MySQL datetime).
	 * @return string HMAC token.
	 */
	private static function generate_token( int $session_id, int $video_id, int $user_id, string $created_ts ): string {
		$ts      = strtotime( $created_ts );
		$payload = "{$session_id}|{$video_id}|{$user_id}|{$ts}";
		$hmac    = hash_hmac( 'sha256', $payload, AUTH_SALT );

		return "{$payload}|{$hmac}";
	}

	/**
	 * Validate an HMAC session token without DB lookup.
	 *
	 * @param string $token           Token string.
	 * @param int    $max_age_seconds Max token age in seconds (default 24 hours).
	 * @return array|false Parsed parts or false if invalid/expired.
	 */
	public static function validate_token( string $token, int $max_age_seconds = 86400 ) {
		$parts = explode( '|', $token );

		if ( count( $parts ) !== 5 ) {
			return false;
		}

		list( $session_id, $video_id, $user_id, $ts, $hmac ) = $parts;

		// Sign the fields exactly as received. Casting the timestamp first let
		// "1700000000x" and "+1700000000" validate as the same token, so one
		// session had endless spellings (and endless rate-limit buckets).
		$payload       = "{$session_id}|{$video_id}|{$user_id}|{$ts}";
		$expected_hmac = hash_hmac( 'sha256', $payload, AUTH_SALT );

		if ( ! hash_equals( $expected_hmac, $hmac ) ) {
			return false;
		}

		// Check token expiration.
		$ts = (int) $ts;
		if ( ( time() - $ts ) > $max_age_seconds ) {
			return false;
		}

		return array(
			'session_id' => (int) $session_id,
			'video_id'   => (int) $video_id,
			'user_id'    => (int) $user_id,
			'created_ts' => gmdate( 'Y-m-d H:i:s', $ts ),
		);
	}

	/**
	 * Parse device type from user agent.
	 *
	 * @param string $ua User agent string.
	 * @return string Device type: mobile, tablet, or desktop.
	 */
	private static function parse_device_type( string $ua ): string {
		$ua_lower = strtolower( $ua );

		if ( preg_match( '/mobile|android.*mobile|iphone|ipod/', $ua_lower ) ) {
			return 'mobile';
		}

		if ( preg_match( '/tablet|ipad|kindle|silk/', $ua_lower ) ) {
			return 'tablet';
		}

		return 'desktop';
	}

	/**
	 * Parse browser name from user agent.
	 *
	 * @param string $ua User agent string.
	 * @return string Browser name.
	 */
	private static function parse_browser( string $ua ): string {
		if ( str_contains( $ua, 'Edg/' ) ) {
			return 'Edge';
		}
		if ( str_contains( $ua, 'Chrome/' ) ) {
			return 'Chrome';
		}
		if ( str_contains( $ua, 'Firefox/' ) ) {
			return 'Firefox';
		}
		if ( str_contains( $ua, 'Safari/' ) ) {
			return 'Safari';
		}

		return 'Other';
	}
}
