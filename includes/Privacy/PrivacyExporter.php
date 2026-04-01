<?php
/**
 * GDPR Personal Data Exporter.
 *
 * Exports watch sessions and milestones for a given user email
 * via the WordPress privacy data export system.
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
 * GDPR Personal Data Exporter for watch sessions and milestones.
 *
 * @since 1.0.0
 */
class PrivacyExporter {

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
	 * @param string $email Email address of the user.
	 * @param int    $page  Page number for pagination.
	 * @return array Export data per WordPress privacy spec.
	 */
	public static function export( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;

		$per_page = 100;
		$offset   = ( $page - 1 ) * $per_page;
		$items    = array();

		// Export watch sessions.
		$sessions_table = "{$wpdb->prefix}ms_watch_sessions";
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query for GDPR export.
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, p.post_title
				 FROM {$sessions_table} s
				 LEFT JOIN {$wpdb->posts} p ON s.video_id = p.ID
				 WHERE s.user_id = %d
				 ORDER BY s.started_at DESC
				 LIMIT %d OFFSET %d",
				$user->ID,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $sessions as $session ) {
			$items[] = array(
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

		// Export milestones (only on first page to avoid duplicating).
		if ( 1 === $page ) {
			$milestones_table = "{$wpdb->prefix}ms_milestones";
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query for GDPR export.
			$milestones = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT m.*, p.post_title
					 FROM {$milestones_table} m
					 LEFT JOIN {$wpdb->posts} p ON m.video_id = p.ID
					 WHERE m.user_id = %d
					 ORDER BY m.reached_at DESC",
					$user->ID
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			foreach ( $milestones as $milestone ) {
				$items[] = array(
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
		}

		$done = count( $sessions ) < $per_page;

		return array(
			'data' => $items,
			'done' => $done,
		);
	}
}
