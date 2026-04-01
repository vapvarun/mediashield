<?php
/**
 * Milestone tracking — fires actions at configurable completion thresholds.
 *
 * Uses INSERT IGNORE with UNIQUE KEY (video_id, user_id, milestone_pct) for
 * deduplication — milestones never fire twice for the same user+video+pct.
 *
 * @package MediaShield\Milestones
 */

namespace MediaShield\Milestones;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MilestoneTracker
 *
 * Milestone tracking — fires actions at configurable completion thresholds.
 *
 * @since 1.0.0
 */
class MilestoneTracker {

	/**
	 * Check and record milestones for a watch session.
	 *
	 * Called from SessionManager::heartbeat() after updating completion_pct.
	 * For each threshold <= completion_pct, attempts INSERT IGNORE. If the
	 * insert succeeds (row is new), fires the milestone action hooks.
	 *
	 * @param int   $video_id       Video CPT post ID.
	 * @param int   $user_id        User ID.
	 * @param float $completion_pct Current completion percentage (0-100).
	 * @param int   $session_id     Session row ID.
	 * @return array List of newly reached milestone percentages.
	 */
	public static function check( int $video_id, int $user_id, float $completion_pct, int $session_id = 0 ): array {
		if ( $completion_pct <= 0 || $user_id <= 0 || $video_id <= 0 ) {
			return array();
		}

		/**
		 * Filter the milestone thresholds.
		 *
		 * @since 1.0.0
		 *
		 * @param array $thresholds Default thresholds.
		 * @param int   $video_id   Video CPT post ID.
		 */
		$thresholds = apply_filters( 'mediashield_milestone_thresholds', array( 25, 50, 75, 100 ), $video_id );

		// Merge per-video milestone thresholds from _ms_milestone_tags meta.
		$video_milestones = get_post_meta( $video_id, '_ms_milestone_tags', true );
		if ( is_array( $video_milestones ) ) {
			foreach ( array_keys( $video_milestones ) as $pct ) {
				if ( ! in_array( (int) $pct, $thresholds, true ) ) {
					$thresholds[] = (int) $pct;
				}
			}
		}

		// Sort ascending and ensure integers.
		$thresholds = array_map( 'intval', $thresholds );
		sort( $thresholds );

		global $wpdb;
		$table = "{$wpdb->prefix}ms_milestones";
		$now   = current_time( 'mysql', true );
		$fired = array();

		foreach ( $thresholds as $pct ) {
			if ( $completion_pct < $pct ) {
				break; // No more thresholds can be reached.
			}

			// INSERT IGNORE — dedup via UNIQUE KEY (video_id, user_id, milestone_pct).
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table insert with IGNORE.
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (video_id, user_id, milestone_pct, reached_at, session_id)
				 VALUES (%d, %d, %d, %s, %d)",
					$video_id,
					$user_id,
					$pct,
					$now,
					$session_id
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// $result > 0 means a new row was inserted (not a duplicate).
			if ( $result > 0 ) {
				$fired[] = $pct;

				/**
				 * Fires when a user reaches a milestone on a video.
				 *
				 * Pro hooks into this for email, webhook, and tag actions.
				 *
				 * @since 1.0.0
				 *
				 * @param int $user_id    User ID.
				 * @param int $video_id   Video CPT post ID.
				 * @param int $pct        Milestone percentage reached.
				 * @param int $session_id Session row ID.
				 */
				// Assign per-video milestone tag to user.
				if ( is_array( $video_milestones ) && ! empty( $video_milestones[ $pct ]['enabled'] ) && ! empty( $video_milestones[ $pct ]['tag'] ) ) {
					$tag = sanitize_text_field( $video_milestones[ $pct ]['tag'] );
					// Store as serialized user meta: video_id + tag.
					$user_tags = get_user_meta( $user_id, '_ms_video_tags', true );
					if ( ! is_array( $user_tags ) ) {
						$user_tags = array();
					}
					$user_tags[ $video_id . '_' . $pct ] = array(
						'video_id'  => $video_id,
						'pct'       => $pct,
						'tag'       => $tag,
						'earned_at' => current_time( 'mysql', true ),
					);
					update_user_meta( $user_id, '_ms_video_tags', $user_tags );
				}

				do_action( 'mediashield_milestone_reached', $user_id, $video_id, $pct, $session_id );

				/**
				 * Fires for a specific milestone percentage.
				 *
				 * Example: mediashield_milestone_25, mediashield_milestone_100.
				 *
				 * @since 1.0.0
				 *
				 * @param int $user_id  User ID.
				 * @param int $video_id Video CPT post ID.
				 */
				do_action( "mediashield_milestone_{$pct}", $user_id, $video_id );
			}
		}

		return $fired;
	}

	/**
	 * Get all milestones reached by a user for a video.
	 *
	 * @param int $video_id Video CPT post ID.
	 * @param int $user_id  User ID.
	 * @return array Array of milestone objects.
	 */
	public static function get_for_video( int $video_id, int $user_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ms_milestones
			 WHERE video_id = %d AND user_id = %d
			 ORDER BY milestone_pct ASC",
				$video_id,
				$user_id
			)
		);

		return ! empty( $results ) ? $results : array();
	}

	/**
	 * Get milestone summary for a video (across all users).
	 *
	 * @param int $video_id Video CPT post ID.
	 * @return array Array of {milestone_pct, user_count}.
	 */
	public static function get_summary( int $video_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT milestone_pct, COUNT(DISTINCT user_id) AS user_count
			 FROM {$wpdb->prefix}ms_milestones
			 WHERE video_id = %d
			 GROUP BY milestone_pct
			 ORDER BY milestone_pct ASC",
				$video_id
			)
		);

		return ! empty( $results ) ? $results : array();
	}
}
