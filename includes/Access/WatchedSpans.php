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
 * Stored per member in one user-meta row, `_ms_watched_spans`, an array keyed
 * by video ID: `[ 's' => [ [start, end], ... ], 't' => last report time ]`.
 * No new table. Removed with the member's other MediaShield data (privacy
 * eraser, uninstall).
 *
 * For milestones (25/50/75/100) read `ms_milestones`; for the furthest point,
 * `ms_watch_sessions.max_position`.
 *
 * @since 1.4.3
 */
class WatchedSpans {

	/**
	 * User-meta key.
	 */
	public const META_KEY = '_ms_watched_spans';

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
	 * Note that a session started, so the first report is measured from now.
	 *
	 * @param int $user_id  Member.
	 * @param int $video_id Video CPT post ID.
	 */
	public static function start( int $user_id, int $video_id ): void {
		if ( $user_id <= 0 || $video_id <= 0 ) {
			return;
		}
		$all              = self::all( $user_id );
		$entry            = $all[ $video_id ] ?? array( 's' => array() );
		$entry['t']       = time();
		$all[ $video_id ] = $entry;
		update_user_meta( $user_id, self::META_KEY, $all );
	}

	/**
	 * Add the stretches the player says it played since its last report.
	 *
	 * @param int   $user_id  Member.
	 * @param int   $video_id Video CPT post ID.
	 * @param array $spans    [ [start, end], ... ] in seconds of the video.
	 * @param float $duration Video length in seconds, 0 when unknown.
	 * @return float Seconds accepted from this report (0 when refused).
	 */
	public static function record( int $user_id, int $video_id, array $spans, float $duration = 0.0 ): float {
		if ( $user_id <= 0 || $video_id <= 0 ) {
			return 0.0;
		}
		$all   = self::all( $user_id );
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
	 * @param float $duration Video length in seconds; 0 reads it from the video.
	 * @return int
	 */
	public static function percent( int $user_id, int $video_id, float $duration = 0.0 ): int {
		$duration = $duration > 0 ? $duration : (float) get_post_meta( $video_id, '_ms_duration', true );
		if ( $duration <= 0 ) {
			return 0;
		}
		return (int) min( 100, floor( self::seconds( $user_id, $video_id ) / $duration * 100 ) );
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
