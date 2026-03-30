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

class SessionManager {

	/**
	 * Start or resume a watch session.
	 *
	 * Session dedup: if same user+video has an active session with heartbeat < 5 min ago,
	 * reuse it instead of creating a new row.
	 *
	 * Uses SELECT ... FOR UPDATE inside a transaction to prevent concurrent session
	 * race conditions.
	 *
	 * @param int    $video_id Video CPT post ID.
	 * @param int    $user_id  User ID.
	 * @param string $ip       Client IP.
	 * @param string $ua       User agent string.
	 * @return array|false Session data on success, false on failure (concurrent limit).
	 */
	public static function start( int $video_id, int $user_id, string $ip = '', string $ua = '' ) {
		global $wpdb;

		$table = "{$wpdb->prefix}ms_watch_sessions";
		$now   = current_time( 'mysql', true );

		// -- Concurrent session limit check (with row locking) --
		$max_concurrent = (int) get_option( 'ms_max_concurrent_streams', 2 );

		$wpdb->query( 'START TRANSACTION' );

		$active_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table}
			 WHERE user_id = %d AND is_active = 1
			 AND last_heartbeat > DATE_SUB(%s, INTERVAL 5 MINUTE)
			 FOR UPDATE",
			$user_id,
			$now
		) );

		// Check for existing session on same video (dedup, locked).
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE video_id = %d AND user_id = %d AND is_active = 1
			 AND last_heartbeat > DATE_SUB(%s, INTERVAL 5 MINUTE)
			 ORDER BY last_heartbeat DESC LIMIT 1
			 FOR UPDATE",
			$video_id,
			$user_id,
			$now
		) );

		if ( $existing ) {
			$wpdb->query( 'COMMIT' );

			return array(
				'session_token'   => self::generate_token( (int) $existing->id, $video_id, $user_id, $existing->started_at ),
				'session_id'      => (int) $existing->id,
				'resume_position' => (float) $existing->max_position,
				'is_resumed'      => true,
			);
		}

		// Subtract 1 from active count if we're replacing a session on same video (not the case here).
		if ( $active_count >= $max_concurrent ) {
			$wpdb->query( 'ROLLBACK' );

			/**
			 * Fires when a concurrent stream limit is reached.
			 *
			 * @since 1.0.0
			 *
			 * @param int $user_id          User ID.
			 * @param int $video_id         Video they tried to watch.
			 * @param int $active_count     Current active session count.
			 * @param int $max_concurrent   Configured limit.
			 */
			do_action( 'mediashield_concurrent_limit_reached', $user_id, $video_id, $active_count, $max_concurrent );

			return false;
		}

		// Get resume position from most recent session (active or not).
		$resume_position = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT max_position FROM {$table}
			 WHERE video_id = %d AND user_id = %d
			 ORDER BY last_heartbeat DESC LIMIT 1",
			$video_id,
			$user_id
		) );

		// Parse device info from user agent.
		$device_type = self::parse_device_type( $ua );
		$browser     = self::parse_browser( $ua );

		// Create session token parts.
		$session_token_raw = wp_generate_password( 32, false );

		$inserted = $wpdb->insert( $table, array(
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
		), array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%d' ) );

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$session_id = (int) $wpdb->insert_id;

		$wpdb->query( 'COMMIT' );

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
	}

	/**
	 * Process a heartbeat from the client.
	 *
	 * @param string $token    HMAC session token.
	 * @param float  $position Current playback position in seconds.
	 * @param float  $duration Total video duration in seconds.
	 * @param bool   $playing  Whether video is currently playing.
	 * @param bool   $focused  Whether browser tab is focused.
	 * @return bool Success.
	 */
	public static function heartbeat( string $token, float $position, float $duration, bool $playing = true, bool $focused = true ): bool {
		$parsed = self::validate_token( $token );

		if ( ! $parsed ) {
			return false;
		}

		global $wpdb;
		$table = "{$wpdb->prefix}ms_watch_sessions";
		$now   = current_time( 'mysql', true );

		$completion_pct = $duration > 0 ? min( 100, ( $position / $duration ) * 100 ) : 0;

		// Single atomic update with GREATEST for max_position.
		$rows = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET
				last_heartbeat = %s,
				total_seconds = total_seconds + %d,
				max_position = GREATEST(max_position, %f),
				completion_pct = %f
			WHERE id = %d AND is_active = 1",
			$now,
			$playing ? 30 : 0,
			$position,
			$completion_pct,
			$parsed['session_id']
		) );

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
	 * End a watch session.
	 *
	 * @param string $token HMAC session token.
	 * @return bool Success.
	 */
	public static function end( string $token ): bool {
		$parsed = self::validate_token( $token );

		if ( ! $parsed ) {
			return false;
		}

		global $wpdb;

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

		$count = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}ms_watch_sessions
			 SET is_active = 0, last_heartbeat = %s
			 WHERE user_id = %d AND is_active = 1",
			current_time( 'mysql', true ),
			$user_id
		) );

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

		// Check token expiration.
		$ts = (int) $ts;
		if ( ( time() - $ts ) > $max_age_seconds ) {
			return false;
		}

		$payload       = "{$session_id}|{$video_id}|{$user_id}|{$ts}";
		$expected_hmac = hash_hmac( 'sha256', $payload, AUTH_SALT );

		if ( ! hash_equals( $expected_hmac, $hmac ) ) {
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
		if ( str_contains( $ua, 'Safari/' ) && ! str_contains( $ua, 'Chrome/' ) ) {
			return 'Safari';
		}

		return 'Other';
	}
}
