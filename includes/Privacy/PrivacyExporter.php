<?php
/**
 * GDPR Personal Data Exporter.
 *
 * Exports watch sessions, milestones, and the milestone-earned tags
 * blob for a given user email via the WordPress privacy data export system.
 *
 * @package MediaShield\Privacy
 */

namespace MediaShield\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PrivacyExporter
 *
 * GDPR Personal Data Exporter for MediaShield-owned PII.
 *
 * @since 1.0.0
 */
class PrivacyExporter {

	/**
	 * Page size for every group's pagination. Matches WP core's built-in
	 * exporters so memory + per-request export latency stay predictable on
	 * sites with very large datasets.
	 */
	private const PER_PAGE = 100;

	/**
	 * Register the exporter.
	 */
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
	}

	/**
	 * Add MediaShield exporter to the list.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['mediashield'] = array(
			'exporter_friendly_name' => __( 'MediaShield Video Data', 'mediashield' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Export personal data for the given email.
	 *
	 * Each group paginates independently against the same `$page` argument WP
	 * passes in. `done` is true only when every group has exhausted its
	 * results on the current page so very-large datasets aren't truncated.
	 *
	 * @param string $email Email address of the user.
	 * @param int    $page  Page number for pagination.
	 * @return array Export data per WordPress privacy spec.
	 */
	public static function export( string $email, int $page = 1 ): array {
		$page   = max( 1, (int) $page );
		$user   = get_user_by( 'email', $email );
		$offset = ( $page - 1 ) * self::PER_PAGE;

		$items = array();
		$done  = true;

		if ( $user ) {
			// Group 1 — watch sessions.
			$sessions = self::query_sessions( (int) $user->ID, $offset );
			foreach ( $sessions as $session ) {
				$items[] = self::format_session( $session );
			}
			if ( count( $sessions ) === self::PER_PAGE ) {
				$done = false;
			}

			// Group 2 — milestone reached_at rows.
			$milestones = self::query_milestones( (int) $user->ID, $offset );
			foreach ( $milestones as $milestone ) {
				$items[] = self::format_milestone( $milestone );
			}
			if ( count( $milestones ) === self::PER_PAGE ) {
				$done = false;
			}

			// Group 3 — user-meta milestone tags (single serialised row on
			// the user record; bounded by the number of (video, pct) the
			// user has earned a tag on — pagination unnecessary).
			if ( 1 === $page ) {
				foreach ( self::query_milestone_tags( (int) $user->ID ) as $entry ) {
					$items[] = $entry;
				}
			}
		}

		/**
		 * Filter the GDPR export result before returning it to WP core.
		 *
		 * Third-party plugins that store user data via MediaShield (custom
		 * tables, additional user meta, external CRM mirrors) can hook this
		 * to append their own groups to the export. Each entry must follow
		 * WP's exporter shape: `array( 'group_id', 'group_label', 'item_id',
		 * 'data' => array( array( 'name', 'value' ), … ) )`. Set
		 * `$result['done'] = false` while the listener still has more rows
		 * on a follow-up page.
		 *
		 * @since 1.0.1
		 *
		 * @param array          $result Result with `data` (item array) and `done` (bool).
		 * @param string         $email  Email being exported.
		 * @param \WP_User|false $user   Matched user object, or false for guest emails.
		 * @param int            $page   Pagination page (1-based).
		 */
		return apply_filters(
			'mediashield_privacy_export_result',
			array(
				'data' => $items,
				'done' => $done,
			),
			$email,
			$user,
			$page
		);
	}

	/**
	 * Page of watch-session rows for a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $offset  SQL offset.
	 * @return array<int, object>
	 */
	private static function query_sessions( int $user_id, int $offset ): array {
		global $wpdb;
		$sessions = "{$wpdb->prefix}ms_watch_sessions";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query for GDPR export.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, p.post_title
				 FROM {$sessions} s
				 LEFT JOIN {$wpdb->posts} p ON s.video_id = p.ID
				 WHERE s.user_id = %d
				 ORDER BY s.started_at DESC, s.id DESC
				 LIMIT %d OFFSET %d",
				$user_id,
				self::PER_PAGE,
				$offset
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Format a watch-session row for the WP exporter receipt.
	 *
	 * @param object $session Session row joined with the video post title.
	 * @return array WP-spec exporter item.
	 */
	private static function format_session( object $session ): array {
		return array(
			'group_id'    => 'mediashield-sessions',
			'group_label' => __( 'MediaShield Watch Sessions', 'mediashield' ),
			'item_id'     => "mediashield-session-{$session->id}",
			'data'        => array(
				array(
					'name'  => __( 'Video', 'mediashield' ),
					'value' => sanitize_text_field( ! empty( $session->post_title ) ? $session->post_title : '' ),
				),
				array(
					'name'  => __( 'Started At', 'mediashield' ),
					'value' => $session->started_at,
				),
				array(
					'name'  => __( 'Completion', 'mediashield' ),
					'value' => round( (float) $session->completion_pct, 1 ) . '%',
				),
				array(
					'name'  => __( 'Total Seconds', 'mediashield' ),
					'value' => (int) $session->total_seconds,
				),
				array(
					'name'  => __( 'IP Address', 'mediashield' ),
					'value' => $session->ip_address ?? '',
				),
				array(
					'name'  => __( 'User Agent', 'mediashield' ),
					'value' => $session->user_agent ?? '',
				),
			),
		);
	}

	/**
	 * Page of milestone rows for a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $offset  SQL offset.
	 * @return array<int, object>
	 */
	private static function query_milestones( int $user_id, int $offset ): array {
		global $wpdb;
		$milestones = "{$wpdb->prefix}ms_milestones";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query for GDPR export.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, p.post_title
				 FROM {$milestones} m
				 LEFT JOIN {$wpdb->posts} p ON m.video_id = p.ID
				 WHERE m.user_id = %d
				 ORDER BY m.reached_at DESC, m.id DESC
				 LIMIT %d OFFSET %d",
				$user_id,
				self::PER_PAGE,
				$offset
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Format a milestone row for the WP exporter receipt.
	 *
	 * @param object $milestone Milestone row joined with the video post title.
	 * @return array WP-spec exporter item.
	 */
	private static function format_milestone( object $milestone ): array {
		return array(
			'group_id'    => 'mediashield-milestones',
			'group_label' => __( 'MediaShield Video Milestones', 'mediashield' ),
			'item_id'     => "mediashield-milestone-{$milestone->id}",
			'data'        => array(
				array(
					'name'  => __( 'Video', 'mediashield' ),
					'value' => sanitize_text_field( ! empty( $milestone->post_title ) ? $milestone->post_title : '' ),
				),
				array(
					'name'  => __( 'Milestone', 'mediashield' ),
					'value' => (int) $milestone->milestone_pct . '%',
				),
				array(
					'name'  => __( 'Reached At', 'mediashield' ),
					'value' => $milestone->reached_at,
				),
			),
		);
	}

	/**
	 * Build exporter entries for the `_ms_video_tags` user-meta blob (the
	 * map of milestone tags the user has earned, written by MilestoneTracker
	 * since commit 86570f7).
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array> Zero or more WP-spec exporter items.
	 */
	private static function query_milestone_tags( int $user_id ): array {
		$tag_map = get_user_meta( $user_id, '_ms_video_tags', true );
		if ( ! is_array( $tag_map ) || empty( $tag_map ) ) {
			return array();
		}

		$items = array();
		foreach ( $tag_map as $key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$video_id    = isset( $entry['video_id'] ) ? (int) $entry['video_id'] : 0;
			$video_title = $video_id > 0 ? get_the_title( $video_id ) : '';
			$items[]     = array(
				'group_id'    => 'mediashield-milestone-tags',
				'group_label' => __( 'MediaShield Milestone Tags', 'mediashield' ),
				'item_id'     => "mediashield-milestone-tag-{$key}",
				'data'        => array(
					array(
						'name'  => __( 'Video', 'mediashield' ),
						'value' => sanitize_text_field( (string) $video_title ),
					),
					array(
						'name'  => __( 'Milestone %', 'mediashield' ),
						'value' => isset( $entry['pct'] ) ? (int) $entry['pct'] : '',
					),
					array(
						'name'  => __( 'Tag', 'mediashield' ),
						'value' => sanitize_text_field( (string) ( $entry['tag'] ?? '' ) ),
					),
					array(
						'name'  => __( 'Earned At', 'mediashield' ),
						'value' => (string) ( $entry['earned_at'] ?? '' ),
					),
				),
			);
		}
		return $items;
	}
}
