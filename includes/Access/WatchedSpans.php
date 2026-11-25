<?php
/**
 * How much of a video a member has actually watched.
 *
 * @package MediaShield\Access
 */

namespace MediaShield\Access;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The parts of each video a member has really played, merged.
 *
 * Answers "what share of this video has this member watched?" by time spent
 * watching, not by the furthest point reached: dragging the scrubber to the
 * end earns nothing, and watching the first minute twice counts once
 * (owner decision on Basecamp 10530930729). The player reports the stretches
 * it played since its last heartbeat; each report is checked against the
 * wall-clock time since the previous one (at most 2x speed plus a little
 * slack), so a forged report cannot claim more than was possible to watch.
 *
 * The same entry keeps where the member last stopped (`p`), the place the
 * player was at in its latest report, so the resume offer returns them to
 * that exact point even after rewinding, as YouTube and Netflix do (owner
 * decision on Basecamp 10531602227).
 *
 * Stored per member in one user-meta row, `_ms_watched_spans`, an array keyed
 * by video ID: `[ 's' => [ [start, end], ... ], 't' => last report time,
 * 'p' => stop position, 'd' => longest length the player reported ]`. No new table. Exported and removed with the
 * member's other MediaShield data (privacy exporter and eraser, uninstall).
 *
 * For milestones (25/50/75/100) read `ms_milestones`; for the furthest point,
 * `ms_watch_sessions.max_position`; for where to resume, stop().
 *
 * @since 1.4.3
 */
class WatchedSpans {

	/**
	 * User-meta key.
	 */
	public const META_KEY = '_ms_watched_spans';

	/**
	 * Video meta: the longest length any viewer's player reported, in seconds.
	 *
	 * The share's length when the video has no Duration. Kept on the video,
	 * not the member: one honest player sets it for everyone, and a member
	 * can no longer shrink their own to fake 100% (Free review #9).
	 */
	public const MEASURED = '_ms_measured_duration';

	/**
	 * Fastest playback the player offers.
	 */
	private const MAX_RATE = 2.0;

	/**
	 * Seconds of slack per report (timer jitter, a report landing late).
	 */
	private const SLACK = 3.0;

	/**
	 * Most spans kept per video once merged; the shortest gaps close first.
	 */
	private const MAX_SPANS = 40;

	/**
	 * Seconds a writer waits for another request's write to this member's
	 * record to finish.
	 */
	private const LOCK_WAIT = 2;

	/**
	 * Note that a session started, so the first report is measured from now.
	 *
	 * @param int $user_id  Member.
	 * @param int $video_id Video CPT post ID.
	 */
	public static function start( int $user_id, int $video_id ): void {
		if ( $user_id <= 0 || $video_id <= 0 ) {
			return;
		}
		self::locked(
			$user_id,
			static function ( array $all ) use ( $user_id, $video_id ): void {
				$entry            = $all[ $video_id ] ?? array( 's' => array() );
				$entry['t']       = time();
				$all[ $video_id ] = $entry;
				update_user_meta( $user_id, self::META_KEY, $all );
			}
		);
	}

	/**
	 * Add the stretches the player says it played since its last report, and
	 * note where the player is now.
	 *
	 * A position of 0 is not stored as the stop: a page view that never
	 * played reports 0, and it must not erase the place from the last viewing.
	 *
	 * @param int   $user_id  Member.
	 * @param int   $video_id Video CPT post ID.
	 * @param array $spans    [ [start, end], ... ] in seconds of the video.
	 * @param float $duration Video length in seconds, 0 when unknown.
	 * @param float $position Where the player is now, in seconds; 0 leaves the stop as it was.
	 * @return float Seconds accepted from this report (0 when refused).
	 */
	public static function record( int $user_id, int $video_id, array $spans, float $duration = 0.0, float $position = 0.0 ): float {
		if ( $user_id <= 0 || $video_id <= 0 ) {
			return 0.0;
		}
		return self::locked(
			$user_id,
			static function ( array $all ) use ( $user_id, $video_id, $spans, $duration, $position ): float {
				return self::add_report( $user_id, $video_id, $all, $spans, $duration, $position );
			}
		);
	}

	/**
	 * Add one report to the member's fresh record and save it (record(), under the lock).
	 *
	 * @param int   $user_id  Member.
	 * @param int   $video_id Video CPT post ID.
	 * @param array $all      Every video's record, read under the lock.
	 * @param array $spans    [ [start, end], ... ] in seconds of the video.
	 * @param float $duration Video length in seconds, 0 when unknown.
	 * @param float $position Where the player is now, in seconds.
	 * @return float Seconds accepted from this report.
	 */
	private static function add_report( int $user_id, int $video_id, array $all, array $spans, float $duration, float $position ): float {
		$entry = $all[ $video_id ] ?? array( 's' => array() );
		$now   = time();
		$since = isset( $entry['t'] ) ? max( 0, $now - (int) $entry['t'] ) : 0;

		$clean = array();
		$total = 0.0;
		foreach ( $spans as $span ) {
			if ( ! is_array( $span ) || count( $span ) < 2 ) {
				continue;
			}
			$a = max( 0.0, (float) reset( $span ) );
			$b = max( 0.0, (float) end( $span ) );
			if ( $duration > 0 ) {
				$a = min( $a, $duration );
				$b = min( $b, $duration );
			}
			if ( $b <= $a ) {
				continue;
			}
			$clean[] = array( $a, $b );
			$total  += $b - $a;
		}

		$entry['t'] = $now;
		// The length the player measures, for a video whose Duration field
		// was left at 0 (a pasted URL): the longest one reported, so a later
		// short report never shrinks it (percent()).
		if ( $duration > 0 ) {
			$entry['d'] = max( (float) ( $entry['d'] ?? 0 ), round( $duration, 1 ) );
			self::note_length( $video_id, $duration );
		}
		if ( $position > 0 ) {
			$entry['p'] = round( $duration > 0 ? min( $position, $duration ) : $position, 1 );
		}
		// More video than could have played since the last report: refuse it all.
		$accepted = ( $clean && $total <= $since * self::MAX_RATE + self::SLACK ) ? $total : 0.0;
		if ( $accepted > 0 ) {
			$entry['s'] = self::merge( array_merge( (array) ( $entry['s'] ?? array() ), $clean ) );
		}
		$all[ $video_id ] = $entry;
		update_user_meta( $user_id, self::META_KEY, $all );

		return $accepted;
	}

	/**
	 * Where the member last stopped in this video: the position of the
	 * player's latest report, rewinds included.
	 *
	 * Answers the resume offer. Not the furthest point (that is
	 * `ms_watch_sessions.max_position`) and not time watched (seconds()).
	 * The player still offers it only from 10 s and before 95% of the video.
	 *
	 * @param int $user_id  Member.
	 * @param int $video_id Video CPT post ID.
	 * @return float|null Seconds; null when no stop was recorded (a viewing from before 1.4.3).
	 */
	public static function stop( int $user_id, int $video_id ): ?float {
		$entry = self::all( $user_id )[ $video_id ] ?? array();
		return isset( $entry['p'] ) ? (float) $entry['p'] : null;
	}

	/**
	 * Seconds of the video watched at least once.
	 *
	 * @param int $user_id  Member.
	 * @param int $video_id Video CPT post ID.
	 * @return float
	 */
	public static function seconds( int $user_id, int $video_id ): float {
		$total = 0.0;
		foreach ( (array) ( self::all( $user_id )[ $video_id ]['s'] ?? array() ) as $span ) {
			$total += (float) $span[1] - (float) $span[0];
		}
		return $total;
	}

	/**
	 * Share of the video watched, as a whole percent (0-100).
	 *
	 * @param int   $user_id  Member.
	 * @param int   $video_id Video CPT post ID.
	 * The length is the one given, else the video's Duration field, else the
	 * longest length any viewer's player measured (MEASURED): most videos added by pasting a
	 * URL keep Duration 0, and with no length the share stayed 0% for ever,
	 * so a "Require X% watched" lesson could never be completed.
	 *
	 * @param float $duration Video length in seconds; 0 reads it from the video.
	 * @return int
	 */
	public static function percent( int $user_id, int $video_id, float $duration = 0.0 ): int {
		$duration = $duration > 0 ? $duration : (float) get_post_meta( $video_id, '_ms_duration', true );
		if ( $duration <= 0 ) {
			$duration = self::measured_length( $user_id, $video_id );
		}
		if ( $duration <= 0 ) {
			return 0;
		}
		return (int) min( 100, floor( self::seconds( $user_id, $video_id ) / $duration * 100 ) );
	}

	/**
	 * Keep the longest length a player reported for this video.
	 *
	 * @param int   $video_id Video CPT post ID.
	 * @param float $duration Length the player reported, in seconds.
	 */
	private static function note_length( int $video_id, float $duration ): void {
		$duration = round( $duration, 1 );
		if ( $duration > (float) get_post_meta( $video_id, self::MEASURED, true ) ) {
			update_post_meta( $video_id, self::MEASURED, $duration );
		}
	}

	/**
	 * The video's measured length (MEASURED). A video measured only before
	 * 1.4.4 kept the length on the member ('d'): that seeds the video once,
	 * and every later honest report can only raise it.
	 *
	 * @param int $user_id  Member.
	 * @param int $video_id Video CPT post ID.
	 * @return float Seconds, 0 when never measured.
	 */
	private static function measured_length( int $user_id, int $video_id ): float {
		$measured = (float) get_post_meta( $video_id, self::MEASURED, true );
		if ( $measured > 0 ) {
			return $measured;
		}
		$own = (float) ( self::all( $user_id )[ $video_id ]['d'] ?? 0 );
		if ( $own > 0 ) {
			self::note_length( $video_id, $own );
		}
		return $own;
	}

	/**
	 * Sort and join overlapping or touching spans; keep at most MAX_SPANS.
	 *
	 * @param array $spans [ [start, end], ... ].
	 * @return array
	 */
	public static function merge( array $spans ): array {
		usort(
			$spans,
			static function ( $x, $y ) {
				return $x[0] <=> $y[0];
			}
		);
		$out = array();
		foreach ( $spans as $span ) {
			$last = count( $out ) - 1;
			if ( $last >= 0 && $span[0] <= $out[ $last ][1] + 1 ) {
				$out[ $last ][1] = max( $out[ $last ][1], $span[1] );
			} else {
				$out[] = array( round( (float) $span[0], 1 ), round( (float) $span[1], 1 ) );
			}
		}
		// Too many fragments: close the smallest gaps, which only ever rounds
		// a little unwatched time in, never a long skip.
		$n = count( $out );
		while ( $n > self::MAX_SPANS ) {
			$best = 1;
			for ( $i = 2; $i < $n; $i++ ) {
				if ( $out[ $i ][0] - $out[ $i - 1 ][1] < $out[ $best ][0] - $out[ $best - 1 ][1] ) {
					$best = $i;
				}
			}
			$out[ $best - 1 ][1] = $out[ $best ][1];
			array_splice( $out, $best, 1 );
			--$n;
		}
		return $out;
	}

	/**
	 * Run one read-modify-write of a member's record with no other writer in between.
	 *
	 * Every player on a page heartbeats, so two requests for one member often
	 * land in the same second. Each read the one user-meta row, changed its
	 * own video and wrote the whole row back, and the later write dropped the
	 * earlier one's spans. A MySQL named lock per member serialises the
	 * writers, and the record is read from the database (not this request's
	 * object cache) once the lock is held, so each writer starts from the
	 * last one's result. If the lock is not free within LOCK_WAIT seconds the
	 * write still goes ahead on the fresh read: a slow database must not cost
	 * the member the report.
	 *
	 * @param int      $user_id Member.
	 * @param callable $change  Receives the fresh record, writes it, returns the result.
	 * @return mixed What $change returns.
	 */
	private static function locked( int $user_id, callable $change ) {
		global $wpdb;

		$name = self::lock_name( $user_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A named lock, not a table read.
		$held = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $name, self::LOCK_WAIT ) );

		try {
			wp_cache_delete( $user_id, 'user_meta' );
			return $change( self::all( $user_id ) );
		} finally {
			if ( $held ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Releases the named lock taken above.
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $name ) );
			}
		}
	}

	/**
	 * The MySQL named lock a writer of this member's record holds.
	 *
	 * Named locks are server-wide, so the name carries the database and the
	 * user table prefix as well as the member; it stays under MySQL's 64
	 * characters.
	 *
	 * @param int $user_id Member.
	 * @return string
	 */
	public static function lock_name( int $user_id ): string {
		global $wpdb;
		return 'ms_spans_' . substr( md5( $wpdb->dbname . '|' . $wpdb->base_prefix ), 0, 12 ) . '_' . $user_id;
	}

	/**
	 * Every video's record for a member.
	 *
	 * @param int $user_id Member.
	 * @return array
	 */
	private static function all( int $user_id ): array {
		$all = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $all ) ? $all : array();
	}
}
