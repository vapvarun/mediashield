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

use MediaShield\Core\Settings;

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

		// Login gate — denial message comes from the user-configured setting so
		// site owners can customize copy without overriding translations.
		if ( Settings::get( 'ms_require_login' ) && ! $user_id ) {
			return self::deny( Settings::get( 'ms_login_overlay_text' ) );
		}

		// Per-video role gate. `_ms_access_role` is set from the Video CPT
		// metabox; an empty value or "any" means no role restriction. Pro can
		// still extend this via the mediashield_can_watch filter below.
		$role_check = self::check_role( $video_id, $user_id );
		if ( ! $role_check['allowed'] ) {
			return $role_check;
		}

		// Domain restriction.
		$allowed_domains = Settings::get( 'ms_allowed_domains' );
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
	 * Enforce a per-video role requirement when `_ms_access_role` is set.
	 *
	 * Stored as a single role slug on the video CPT; empty string or the
	 * sentinel "any" disables the gate.
	 *
	 * @param int $video_id Video CPT post ID.
	 * @param int $user_id  User ID (0 for guests).
	 * @return array{allowed: bool, reason: string}
	 */
	private static function check_role( int $video_id, int $user_id ): array {
		$required = (string) get_post_meta( $video_id, '_ms_access_role', true );
		if ( '' === $required || 'any' === $required ) {
			return self::allow();
		}

		// Logged-out users can never satisfy a role requirement.
		if ( ! $user_id ) {
			return self::deny( Settings::get( 'ms_login_overlay_text' ) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( $required, (array) $user->roles, true ) ) {
			return self::deny( Settings::get( 'ms_access_denied_text' ) );
		}

		return self::allow();
	}

	/**
	 * Check HTTP referer against the allowed-domain whitelist.
	 *
	 * Empty Referer is treated as a bypass attempt — when a whitelist is
	 * configured, the safe default is to deny rather than allow. Sites that
	 * legitimately need to support same-origin direct access from
	 * privacy-respecting browsers can opt in via the
	 * `mediashield_allow_empty_referer` filter.
	 *
	 * @param string $allowed_domains Whitelist as the owner typed it. See
	 *                                parse_domains() for accepted separators.
	 * @return array{allowed: bool, reason: string}
	 */
	private static function check_domain( string $allowed_domains ): array {
		// `wp_get_referer()` strips external hosts via wp_validate_redirect(),
		// which makes any cross-origin referer indistinguishable from "no
		// referer". `wp_get_raw_referer()` returns the actual header so we can
		// match against the whitelist.
		$referer = wp_get_raw_referer();

		if ( empty( $referer ) ) {
			/**
			 * Filter whether to allow video playback when the HTTP Referer
			 * header is absent. Defaults to false (deny) — keeps the whitelist
			 * intent intact, since stripping the Referer is the typical bypass.
			 *
			 * @since 1.1.0
			 *
			 * @param bool $allow Default false.
			 */
			$allow_empty = (bool) apply_filters( 'mediashield_allow_empty_referer', false );
			return $allow_empty ? self::allow() : self::deny( Settings::get( 'ms_access_denied_text' ) );
		}

		$referer_host = wp_parse_url( $referer, PHP_URL_HOST );
		$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );

		// Same domain always allowed.
		if ( $referer_host === $site_host ) {
			return self::allow();
		}

		// Check against whitelist.
		foreach ( self::parse_domains( $allowed_domains ) as $domain ) {
			if ( $referer_host === $domain || str_ends_with( $referer_host, '.' . $domain ) ) {
				return self::allow();
			}
		}

		return self::deny( Settings::get( 'ms_access_denied_text' ) );
	}

	/**
	 * Turn the Allowed Domains field into a list of hostnames to match.
	 *
	 * WHY THIS IS DELIBERATELY FORGIVING
	 *
	 * This used to be `explode( ',', ... )` while the field's own help text
	 * said "One domain per line." An owner who followed the instruction
	 * produced a single string containing newlines, which matches no referer -
	 * and because the whitelist is only consulted when it is non-empty, filling
	 * it in correctly switched on a check that could never pass. The result was
	 * that every cross-origin embed was denied, which is the exact opposite of
	 * what the owner was configuring, and it presents as a playback bug rather
	 * than a settings mistake, so the field is the last place they would look.
	 *
	 * Rather than pick a separator and be wrong for every site already storing
	 * the other one, both are accepted. Whitespace, newlines and commas all
	 * separate entries, in any combination.
	 *
	 * A pasted URL is also accepted. "Domain" is not a distinction most people
	 * make when the thing in their clipboard is `https://example.com/embed/`,
	 * and the failure mode for getting it wrong here is silent and total, so
	 * the host is extracted rather than the entry being discarded.
	 *
	 * Note this returns bare hostnames only; the caller matches both an exact
	 * hit and any subdomain of the entry.
	 *
	 * @param string $allowed_domains Raw field value.
	 * @return string[] Lowercased hostnames, no duplicates, no empties.
	 */
	private static function parse_domains( string $allowed_domains ): array {
		$entries = preg_split( '/[\s,]+/', $allowed_domains, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $entries ) ) {
			return array();
		}

		$domains = array();

		foreach ( $entries as $entry ) {
			$entry = strtolower( trim( $entry ) );

			// A pasted URL, or a bare host with a path or port stuck to it.
			// wp_parse_url() only finds a host when there is a scheme, so add
			// one for the bare case rather than parsing the string by hand.
			if ( false !== strpos( $entry, '/' ) || false !== strpos( $entry, ':' ) ) {
				$probe = ( false === strpos( $entry, '//' ) ) ? 'https://' . $entry : $entry;
				$host  = wp_parse_url( $probe, PHP_URL_HOST );
				if ( is_string( $host ) && '' !== $host ) {
					$entry = $host;
				}
			}

			// Trailing dot is legal in DNS and never appears in a Referer host.
			$entry = rtrim( $entry, '.' );

			if ( '' !== $entry ) {
				$domains[] = $entry;
			}
		}

		return array_values( array_unique( $domains ) );
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
