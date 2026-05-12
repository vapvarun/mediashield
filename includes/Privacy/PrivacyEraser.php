<?php
/**
 * GDPR Personal Data Eraser.
 *
 * Anonymizes watch sessions, removes milestones, milestone-earned tag
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
			// Anonymize watch sessions (keep aggregate data, remove PII).
			$sessions_table = "{$wpdb->prefix}ms_watch_sessions";
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table queries for GDPR erasure.
			$sessions_updated = (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$sessions_table} SET ip_address = '', user_agent = '' WHERE user_id = %d AND ( ip_address != '' OR user_agent != '' )",
					$user->ID
				)
			);
			$items_removed += $sessions_updated;

			// Sessions themselves are retained (anonymized, not deleted) for aggregate analytics.
			$total_sessions = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$sessions_table} WHERE user_id = %d",
					$user->ID
				)
			);
			$items_retained += $total_sessions;

			// Remove milestone rows (the per-user reached_at timestamps for
			// 25/50/75/100% completion). Built on the Free side by the
			// MilestoneTracker; no need to gate on Pro.
			$milestones_table = "{$wpdb->prefix}ms_milestones";
			$milestones_deleted = (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$milestones_table} WHERE user_id = %d",
					$user->ID
				)
			);
			$items_removed += $milestones_deleted;
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// Delete the milestone-earned-tags blob (Free MilestoneTracker
			// writes this user meta on each milestone fire; commit 86570f7).
			// Single user meta row, returns 0 or 1.
			$user_tags_deleted = (int) delete_user_meta( $user->ID, '_ms_video_tags' );
			$items_removed    += $user_tags_deleted;

			// Remove activity alerts for this user (Pro table, only if it exists).
			if ( defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
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
}
