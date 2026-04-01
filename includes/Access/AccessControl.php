<?php
/**
 * Video access control — login gate, domain restriction, filterable access.
 *
 * @package MediaShield\Access
 */

namespace MediaShield\Access;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AccessControl
 *
 * Video access control — login gate, domain restriction, filterable access.
 *
 * @since 1.0.0
 */
class AccessControl {

	/**
	 * Check if a user can watch a video.
	 *
	 * @param int $video_id Video CPT post ID.
	 * @param int $user_id  User ID (0 for guests).
	 * @return array{allowed: bool, reason: string}
	 */
	public static function can_watch( int $video_id, int $user_id = 0 ): array {
		// Admins always pass.
		if ( $user_id && user_can( $user_id, 'manage_options' ) ) {
			return self::allow();
		}

		// Login gate.
		if ( get_option( 'ms_require_login', true ) && ! $user_id ) {
			return self::deny( __( 'Please log in to watch this video.', 'mediashield' ) );
		}

		// Domain restriction.
		$allowed_domains = get_option( 'ms_allowed_domains', '' );
		if ( ! empty( $allowed_domains ) ) {
			$domain_check = self::check_domain( $allowed_domains );
			if ( ! $domain_check['allowed'] ) {
				return $domain_check;
			}
		}

		// Build initial result.
		$result = self::allow();

		/**
		 * Filter whether a user can watch a video.
		 *
		 * Pro hooks into this for role-based access, email gating, etc.
		 *
		 * @since 1.0.0
		 *
		 * @param array $result   {allowed: bool, reason: string}
		 * @param int   $video_id Video CPT post ID.
		 * @param int   $user_id  User ID.
		 */
		$result = apply_filters( 'mediashield_can_watch', $result, $video_id, $user_id );

		return $result;
	}

	/**
	 * Check HTTP referer against allowed domain whitelist.
	 *
	 * @param string $allowed_domains Comma-separated list of domains.
	 * @return array{allowed: bool, reason: string}
	 */
	private static function check_domain( string $allowed_domains ): array {
		$referer = wp_get_referer();

		// No referer header = direct access or same-origin; allow.
		if ( empty( $referer ) ) {
			return self::allow();
		}

		$referer_host = wp_parse_url( $referer, PHP_URL_HOST );
		$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );

		// Same domain always allowed.
		if ( $referer_host === $site_host ) {
			return self::allow();
		}

		// Check against whitelist.
		$domains = array_map( 'trim', explode( ',', $allowed_domains ) );
		$domains = array_filter( $domains );

		foreach ( $domains as $domain ) {
			if ( $referer_host === $domain || str_ends_with( $referer_host, '.' . $domain ) ) {
				return self::allow();
			}
		}

		return self::deny( __( 'Playback is not allowed from this domain.', 'mediashield' ) );
	}

	/**
	 * Build an "allowed" result.
	 */
	private static function allow(): array {
		return array(
			'allowed' => true,
			'reason'  => '',
		);
	}

	/**
	 * Build a "denied" result.
	 *
	 * @param string $reason Denial reason message.
	 * @return array
	 */
	private static function deny( string $reason ): array {
		return array(
			'allowed' => false,
			'reason'  => $reason,
		);
	}
}
