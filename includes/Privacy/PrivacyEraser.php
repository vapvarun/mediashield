<?php
/**
 * GDPR Personal Data Eraser.
 *
 * Anonymises watch sessions (no IP, no browser string, user_id 0), removes milestones, milestone-earned tag
 * records, and (when Pro is active) activity alerts for a given user email
 * via the WordPress privacy erasure system.
 *
 * @package MediaShield\Privacy
 */

namespace MediaShield\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PrivacyEraser
 *
 * GDPR Personal Data Eraser for MediaShield-owned PII.
 *
 * @since 1.0.0
 */
class PrivacyEraser {

	/**
	 * Register the eraser.
	 */
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Add MediaShield eraser to the list.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['mediashield'] = array(
			'eraser_friendly_name' => __( 'MediaShield Video Data', 'mediashield' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Erase (anonymize) personal data for the given email.
	 *
	 * @param string $email Email address of the user.
	 * @param int    $page  Page number for pagination.
	 * @return array Erasure result per WordPress privacy spec.
	 */
	public static function erase( string $email, int $page = 1 ): array {
		global $wpdb;

		$items_removed  = 0;
		$items_retained = 0;
		$user           = get_user_by( 'email', $email );

		/**
		 * Fires before MediaShield's GDPR erasure runs for an email.
		 *
		 * Third-party plugins that store user data via MediaShield (custom
		 * tables keyed on user_id, additional user meta keys, external CRMs)
		 * can hook this to run their own cleanup. They should increment the
		 * counts in the `$counters` object passed by reference so their
		 * removals roll into the GDPR receipt.
		 *
		 * @since 1.0.1
		 *
		 * @param string        $email    Email being erased.
		 * @param \WP_User|false $user     Matched user object, or false for guest emails.
		 * @param int           $page     Pagination page (always 1 for this eraser).
		 * @param object        $counters stdClass with int `items_removed` / `items_retained` props.
		 */
		$counters = (object) array(
			'items_removed'  => 0,
			'items_retained' => 0,
		);
		do_action( 'mediashield_privacy_before_erase', $email, $user, $page, $counters );

		if ( $user ) {
			// Anonymise watch sessions: the rows stay for the owner's totals, but
			// with no IP, no browser string and user_id 0 they no longer point
			// at the person (owner decision 2026-11-11, card 10488481105).
			// Pro removes its session-linked rows (playback events) through
			// mediashield_privacy_before_erase above, while user_id still
			// matches.
			$sessions_table = "{$wpdb->prefix}ms_watch_sessions";
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table queries for GDPR erasure.

			// An older Pro (below 1.4.2) does not hook the action above: its
			// own eraser finds playback events through sessions.user_id, which
			// the UPDATE below zeroes first, so the events were left behind.
			// Delete them here while the sessions still name the person.
			// ponytail: delete with the alerts block below once Pro below 1.4.2 is unsupported.
			if ( self::pro_below_142() ) {
				$items_removed += self::erase_old_pro_playback_events( (int) $user->ID, $sessions_table );
			}
			$sessions_updated = (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$sessions_table} SET user_id = 0, ip_address = '', user_agent = '' WHERE user_id = %d",
					$user->ID
				)
			);
			$items_removed   += $sessions_updated;

			// The archive table holds the same personal data - it is populated
			// by a straight INSERT..SELECT from the live table, IP address and
			// user agent included. Erasure skipped it entirely, so a data
			// subject on a site old enough to have archived rows got a success
			// response while their IP and user agent survived indefinitely
			// (BC#10217642097). Erasure that reports success without erasing
			// everything is worse than erasure that fails loudly.
			//
			// Still needed even though retention is now opt-in: rows archived
			// by the old unconditional behaviour exist on real sites, and the
			// restore migration may not have drained them yet.
			$archive_table = "{$wpdb->prefix}ms_watch_sessions_archive";
			$archive_exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
					DB_NAME,
					$archive_table
				)
			);

			$archived_updated = 0;
			if ( $archive_exists ) {
				$archived_updated = (int) $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$archive_table} SET user_id = 0, ip_address = '', user_agent = '' WHERE user_id = %d",
						$user->ID
					)
				);
				$items_removed   += $archived_updated;
			}

			// The anonymised sessions are kept for the totals, but with no IP,
			// browser string or user they are no longer personal data, so they
			// count as removed only. Counting them as retained as well made
			// WordPress say some personal data was not erased.

			// Remove milestone rows (the per-user reached_at timestamps for
			// 25/50/75/100% completion). Built on the Free side by the
			// MilestoneTracker; no need to gate on Pro.
			$milestones_table   = "{$wpdb->prefix}ms_milestones";
			$milestones_deleted = (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$milestones_table} WHERE user_id = %d",
					$user->ID
				)
			);
			$items_removed     += $milestones_deleted;
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// Delete the milestone-earned-tags blob (Free MilestoneTracker
			// writes this user meta on each milestone fire; commit 86570f7).
			// Single user meta row, returns 0 or 1.
			$user_tags_deleted = (int) delete_user_meta( $user->ID, '_ms_video_tags' );
			$items_removed    += $user_tags_deleted;

			// Remove activity alerts for this user (Pro table). Pro 1.4.2 and
			// later exports and erases its own table; this stays only for an
			// older Pro running next to this Free version.
			// ponytail: delete this block once Pro below 1.4.2 is unsupported.
			if ( self::pro_below_142() ) {
				$alerts_table = "{$wpdb->prefix}ms_activity_alerts";

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
				$table_exists = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
						DB_NAME,
						$alerts_table
					)
				);

				if ( $table_exists ) {
					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Pro table delete for GDPR erasure.
					$alerts_deleted = (int) $wpdb->query(
						$wpdb->prepare(
							"DELETE FROM {$alerts_table} WHERE user_id = %d",
							$user->ID
						)
					);
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

					$items_removed += $alerts_deleted;
				}
			}
		}

		// Roll in counts any third-party listener accumulated above.
		$items_removed  += (int) $counters->items_removed;
		$items_retained += (int) $counters->items_retained;

		// Return integer counts (WP's privacy spec wants ints, not bools —
		// casting to bool collapses "we removed 47 rows" down to `true`,
		// reading "1 item removed" on the admin GDPR receipt for every
		// user with any data at all).
		$result = array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => array(),
			'done'           => true,
		);

		/**
		 * Filter the GDPR erasure result before returning it to WP core.
		 *
		 * Third-party plugins can hook this for late-stage cleanup that needs
		 * to see what MediaShield already erased, or to append `messages[]`
		 * strings that surface in the user's GDPR receipt.
		 *
		 * @since 1.0.1
		 *
		 * @param array         $result Result array.
		 * @param string        $email  Email being erased.
		 * @param \WP_User|false $user   Matched user object, or false for guest emails.
		 * @param int           $page   Pagination page (always 1 for this eraser).
		 */
		return apply_filters( 'mediashield_privacy_erase_result', $result, $email, $user, $page );
	}

	/**
	 * Is an older MediaShield Pro (below 1.4.2) active next to this Free?
	 *
	 * Pro 1.4.2 erases and exports its own tables (alerts, playback events)
	 * through mediashield_privacy_before_erase; an older Pro does not.
	 *
	 * @return bool
	 */
	public static function pro_below_142(): bool {
		return defined( 'MEDIASHIELD_PRO_VERSION' ) && version_compare( MEDIASHIELD_PRO_VERSION, '1.4.2', '<' );
	}

	/**
	 * Delete an older Pro's playback events for a user, and the heatmap cache
	 * rows of the videos they touched, while the sessions still carry user_id.
	 *
	 * @param int    $user_id        User being erased.
	 * @param string $sessions_table Watch sessions table name.
	 * @return int Events deleted.
	 */
	private static function erase_old_pro_playback_events( int $user_id, string $sessions_table ): int {
		global $wpdb;

		$events = "{$wpdb->prefix}ms_playback_events";
		$cache  = "{$wpdb->prefix}ms_heatmap_cache";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Pro tables, GDPR erasure.
		if ( $events !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events ) ) ) {
			return 0;
		}

		$video_ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT ws.video_id FROM {$events} pe INNER JOIN {$sessions_table} ws ON pe.session_id = ws.id WHERE ws.user_id = %d",
					$user_id
				)
			)
		);

		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE pe FROM {$events} pe INNER JOIN {$sessions_table} ws ON pe.session_id = ws.id WHERE ws.user_id = %d",
				$user_id
			)
		);

		if ( $video_ids && $cache === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cache ) ) ) {
			$wpdb->query( "DELETE FROM {$cache} WHERE video_id IN (" . implode( ',', $video_ids ) . ')' );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $deleted;
	}
}
