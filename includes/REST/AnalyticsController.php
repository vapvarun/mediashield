<?php
/**
 * REST API controller for analytics data.
 *
 * Routes:
 *   GET /mediashield/v1/analytics/overview  — Dashboard overview stats
 *   GET /mediashield/v1/videos/<id>/stats   — Per-video stats
 *   GET /mediashield/v1/analytics/milestones — Paginated milestones
 *   GET /mediashield/v1/analytics/users      — User watch history
 *   GET /mediashield/v1/analytics/users/<id> — Single user drill-down
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class AnalyticsController extends WP_REST_Controller {

	/** @var string */
	protected $namespace = 'mediashield/v1';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route( $this->namespace, '/analytics/overview', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_overview' ),
			'permission_callback' => array( $this, 'admin_check' ),
			'args'                => array(
				'period' => array(
					'type'              => 'string',
					'default'           => '7d',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		register_rest_route( $this->namespace, '/videos/(?P<id>\d+)/stats', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_video_stats' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );

		register_rest_route( $this->namespace, '/analytics/milestones', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_milestones' ),
			'permission_callback' => array( $this, 'admin_check' ),
			'args'                => array(
				'per_page' => array( 'type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint' ),
				'page'     => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
				'video_id' => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
			),
		) );

		register_rest_route( $this->namespace, '/analytics/users', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_users' ),
			'permission_callback' => array( $this, 'admin_check' ),
			'args'                => array(
				'per_page' => array( 'type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint' ),
				'page'     => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
				'search'   => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		register_rest_route( $this->namespace, '/analytics/users/(?P<user_id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_user_detail' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );

		register_rest_route( $this->namespace, '/analytics/my-videos', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_my_videos' ),
			'permission_callback' => array( $this, 'logged_in_check' ),
		) );
	}

	public function admin_check( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /analytics/overview — dashboard stats.
	 */
	public function get_overview( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$period   = $request->get_param( 'period' );
		$interval = self::period_to_interval( $period );
		$sessions = "{$wpdb->prefix}ms_watch_sessions";

		// Total videos.
		$total_videos = (int) wp_count_posts( 'mediashield_video' )->publish;

		// Sessions in period. $interval is from a hardcoded allowlist — safe to interpolate.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_sessions = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$sessions} WHERE started_at >= DATE_SUB(NOW(), INTERVAL {$interval})"
		);

		// Avg completion in period.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$avg_completion = (float) $wpdb->get_var(
			"SELECT AVG(completion_pct) FROM {$sessions} WHERE started_at >= DATE_SUB(NOW(), INTERVAL {$interval}) AND completion_pct > 0"
		);

		// Active viewers (heartbeat in last 5 minutes).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$active_viewers = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id) FROM {$sessions} WHERE is_active = 1 AND last_heartbeat >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
		);

		// Sessions per day for chart.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sessions_per_day = $wpdb->get_results(
			"SELECT DATE(started_at) AS date, COUNT(*) AS count
			 FROM {$sessions}
			 WHERE started_at >= DATE_SUB(NOW(), INTERVAL {$interval})
			 GROUP BY DATE(started_at)
			 ORDER BY date ASC"
		);

		// Top 5 videos by sessions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$top_videos = $wpdb->get_results(
			"SELECT s.video_id, COUNT(*) AS session_count, AVG(s.completion_pct) AS avg_completion, p.post_title
			 FROM {$sessions} s
			 INNER JOIN {$wpdb->posts} p ON s.video_id = p.ID
			 WHERE s.started_at >= DATE_SUB(NOW(), INTERVAL {$interval})
			 GROUP BY s.video_id
			 ORDER BY session_count DESC
			 LIMIT 5"
		);

		return rest_ensure_response( array(
			'total_videos'    => $total_videos,
			'total_sessions'  => $total_sessions,
			'avg_completion'  => round( $avg_completion, 1 ),
			'active_viewers'  => $active_viewers,
			'sessions_chart'  => $sessions_per_day ?: array(),
			'top_videos'      => array_map( function ( $row ) {
				return array(
					'video_id'       => (int) $row->video_id,
					'title'          => sanitize_text_field( $row->post_title ),
					'session_count'  => (int) $row->session_count,
					'avg_completion' => round( (float) $row->avg_completion, 1 ),
				);
			}, $top_videos ?: array() ),
		) );
	}

	/**
	 * GET /videos/<id>/stats — per-video analytics.
	 */
	public function get_video_stats( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$video_id = (int) $request['id'];
		$sessions = "{$wpdb->prefix}ms_watch_sessions";

		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(*) AS total_sessions,
				COUNT(DISTINCT user_id) AS unique_viewers,
				AVG(completion_pct) AS avg_completion,
				SUM(total_seconds) AS total_watch_time,
				MAX(last_heartbeat) AS last_watched
			 FROM {$sessions}
			 WHERE video_id = %d",
			$video_id
		) );

		if ( ! $stats ) {
			return rest_ensure_response( array(
				'total_sessions'   => 0,
				'unique_viewers'   => 0,
				'avg_completion'   => 0,
				'total_watch_time' => 0,
				'last_watched'     => null,
			) );
		}

		return rest_ensure_response( array(
			'total_sessions'   => (int) $stats->total_sessions,
			'unique_viewers'   => (int) $stats->unique_viewers,
			'avg_completion'   => round( (float) $stats->avg_completion, 1 ),
			'total_watch_time' => (int) $stats->total_watch_time,
			'last_watched'     => $stats->last_watched,
		) );
	}

	/**
	 * GET /analytics/milestones — paginated milestone list.
	 */
	public function get_milestones( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );
		$video_id = $request->get_param( 'video_id' );
		$offset   = ( $page - 1 ) * $per_page;

		$where = '';
		$args  = array();
		if ( $video_id > 0 ) {
			$where = 'AND m.video_id = %d';
			$args[] = $video_id;
		}

		$count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}ms_milestones m WHERE 1=1 {$where}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			empty( $args ) ? $count_query : $wpdb->prepare( $count_query, ...$args )
		);

		$list_query = "SELECT m.*, p.post_title AS video_title, u.display_name AS user_name
			 FROM {$wpdb->prefix}ms_milestones m
			 LEFT JOIN {$wpdb->posts} p ON m.video_id = p.ID
			 LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
			 WHERE 1=1 {$where}
			 ORDER BY m.reached_at DESC
			 LIMIT %d OFFSET %d";
		$query_args = array_merge( $args, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $list_query, ...$query_args ) );

		$items = array_map( function ( $row ) {
			return array(
				'id'            => (int) $row->id,
				'video_id'      => (int) $row->video_id,
				'video_title'   => sanitize_text_field( $row->video_title ?: '' ),
				'user_id'       => (int) $row->user_id,
				'user_name'     => sanitize_text_field( $row->user_name ?: '' ),
				'milestone_pct' => (int) $row->milestone_pct,
				'reached_at'    => $row->reached_at,
			);
		}, $rows ?: array() );

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * GET /analytics/users — users with watch history.
	 */
	public function get_users( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );
		$search   = $request->get_param( 'search' );
		$offset   = ( $page - 1 ) * $per_page;

		$where = '';
		$args  = array();
		if ( ! empty( $search ) ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where  = 'AND (u.display_name LIKE %s OR u.user_email LIKE %s)';
			$args[] = $like;
			$args[] = $like;
		}

		$count_query = "SELECT COUNT(DISTINCT s.user_id)
			 FROM {$wpdb->prefix}ms_watch_sessions s
			 INNER JOIN {$wpdb->users} u ON s.user_id = u.ID
			 WHERE s.user_id > 0 {$where}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			empty( $args ) ? $count_query : $wpdb->prepare( $count_query, ...$args )
		);

		$list_query = "SELECT s.user_id, u.display_name, u.user_email,
					COUNT(DISTINCT s.video_id) AS videos_watched,
					AVG(s.completion_pct) AS avg_completion,
					MAX(s.last_heartbeat) AS last_active
			 FROM {$wpdb->prefix}ms_watch_sessions s
			 INNER JOIN {$wpdb->users} u ON s.user_id = u.ID
			 WHERE s.user_id > 0 {$where}
			 GROUP BY s.user_id
			 ORDER BY last_active DESC
			 LIMIT %d OFFSET %d";
		$query_args = array_merge( $args, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $list_query, ...$query_args ) );

		$items = array_map( function ( $row ) {
			return array(
				'user_id'         => (int) $row->user_id,
				'display_name'    => sanitize_text_field( $row->display_name ),
				'email'           => sanitize_email( $row->user_email ),
				'videos_watched'  => (int) $row->videos_watched,
				'avg_completion'  => round( (float) $row->avg_completion, 1 ),
				'last_active'     => $row->last_active,
			);
		}, $rows ?: array() );

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * GET /analytics/users/<id> — single user drill-down.
	 */
	public function get_user_detail( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$user_id = (int) $request['user_id'];

		$sessions = $wpdb->get_results( $wpdb->prepare(
			"SELECT s.video_id, p.post_title, s.completion_pct, s.total_seconds,
					s.max_position, s.last_heartbeat, s.started_at
			 FROM {$wpdb->prefix}ms_watch_sessions s
			 LEFT JOIN {$wpdb->posts} p ON s.video_id = p.ID
			 WHERE s.user_id = %d
			 ORDER BY s.last_heartbeat DESC
			 LIMIT 100",
			$user_id
		) );

		$user = get_userdata( $user_id );

		return rest_ensure_response( array(
			'user' => array(
				'id'           => $user_id,
				'display_name' => $user ? sanitize_text_field( $user->display_name ) : '',
				'email'        => $user ? sanitize_email( $user->user_email ) : '',
			),
			'sessions' => array_map( function ( $row ) {
				return array(
					'video_id'       => (int) $row->video_id,
					'title'          => sanitize_text_field( $row->post_title ?: '' ),
					'completion_pct' => round( (float) $row->completion_pct, 1 ),
					'total_seconds'  => (int) $row->total_seconds,
					'max_position'   => (float) $row->max_position,
					'last_watched'   => $row->last_heartbeat,
					'started_at'     => $row->started_at,
				);
			}, $sessions ?: array() ),
		) );
	}

	/**
	 * Permission check: user must be logged in.
	 */
	public function logged_in_check( WP_REST_Request $request ): bool {
		return is_user_logged_in();
	}

	/**
	 * GET /analytics/my-videos — current user's watch history.
	 */
	public function get_my_videos( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$user_id  = get_current_user_id();
		$sessions = "{$wpdb->prefix}ms_watch_sessions";

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.video_id, p.post_title,
						MAX( s.completion_pct ) AS completion_pct,
						MAX( s.max_position ) AS max_position,
						SUM( s.total_seconds ) AS total_seconds,
						MAX( s.last_heartbeat ) AS last_watched
				 FROM {$sessions} s
				 INNER JOIN {$wpdb->posts} p ON s.video_id = p.ID AND p.post_status = 'publish'
				 WHERE s.user_id = %d
				 GROUP BY s.video_id
				 ORDER BY last_watched DESC",
				$user_id
			)
		);

		$items = array_map( function ( $row ) {
			$thumbnail_url = get_the_post_thumbnail_url( (int) $row->video_id, 'medium' );

			return array(
				'video_id'       => (int) $row->video_id,
				'title'          => sanitize_text_field( $row->post_title ?: '' ),
				'thumbnail_url'  => $thumbnail_url ? esc_url( $thumbnail_url ) : '',
				'completion_pct' => round( (float) $row->completion_pct, 1 ),
				'max_position'   => (float) $row->max_position,
				'total_seconds'  => (int) $row->total_seconds,
				'last_watched'   => $row->last_watched,
			);
		}, $rows ?: array() );

		return rest_ensure_response( $items );
	}

	/**
	 * Convert period string to SQL INTERVAL.
	 */
	private static function period_to_interval( string $period ): string {
		return match ( $period ) {
			'today' => '1 DAY',
			'7d'    => '7 DAY',
			'30d'   => '30 DAY',
			'90d'   => '90 DAY',
			default => '7 DAY',
		};
	}
}
