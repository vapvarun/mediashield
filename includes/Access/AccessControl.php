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
	 * @return array{allowed: bool, reason: string, link?: array{title: string, url: string}|null} `link` only on a refusal from mediashield_can_watch.
	 */
	public static function can_watch( int $video_id, int $user_id = 0 ): array {
		// Admins always pass.
		if ( $user_id && user_can( $user_id, 'manage_options' ) ) {
			return self::allow();
		}

		// Login gate — denial message comes from the user-configured setting so
		// site owners can customize copy without overriding translations.
		if ( self::login_gate_applies( $video_id, $user_id ) ) {
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
		 * Pro and LMS integrations hook in to add their own access rules.
		 *
		 * @since 1.0.0
		 *
		 * @param array $result   {allowed: bool, reason: string}. A refusal may add
		 *                        link {title, url} (where to go) and keep_reason
		 *                        (true: show reason as written, with that link,
		 *                        instead of "This video is part of <course>.
		 *                        Enroll to watch it.").
		 *                        Since 1.4.3.
		 * @param int   $video_id Video CPT post ID.
		 * @param int   $user_id  User ID.
		 */
		$result = apply_filters( 'mediashield_can_watch', $result, $video_id, $user_id );

		// A refusal from an integration (a course the viewer is not enrolled
		// in) carries where to get access, so the player can name the course
		// and link to it. Role, domain and login refusals return above and
		// never get one: their own message is the right answer. An integration
		// that already knows the right link (an enrolled student whose lesson
		// is withdrawn goes back to their course) keeps it.
		if ( is_array( $result ) && empty( $result['allowed'] ) ) {
			$result['link'] = empty( $result['link'] )
				? self::access_link( $video_id, $user_id )
				: self::clean_link( $result['link'] );
		}

		return $result;
	}

	/**
	 * Where can a viewer who is refused this video get access?
	 *
	 * Asked only after an integration refused (see can_watch()). The answer is
	 * the course that plays the video: its name and the page to enrol from.
	 * Learnomy is answered by Integrations\Learnomy, LearnDash and other
	 * linked LMS lessons by Pro. Null when no course is known; the player then
	 * shows the refusal's own words.
	 *
	 * @since 1.4.3
	 *
	 * @param int $video_id Video CPT post ID.
	 * @param int $user_id  Viewer, 0 for a guest.
	 * @return array{title: string, url: string}|null
	 */
	public static function access_link( int $video_id, int $user_id = 0 ): ?array {
		/**
		 * Where a viewer refused this video can get access: the course playing it.
		 *
		 * @since 1.4.3
		 *
		 * @param array|null $link     { title: course name, url: course page } or null.
		 * @param int        $video_id Video CPT post ID.
		 * @param int        $user_id  Viewer, 0 for a guest.
		 */
		return self::clean_link( apply_filters( 'mediashield_video_access_link', null, $video_id, $user_id ) );
	}

	/**
	 * A refusal link made safe to hand to the player: a plain-text title and
	 * a URL that survives esc_url_raw(), or null when either is missing.
	 *
	 * @since 1.4.3
	 *
	 * @param mixed $link { title, url } from an integration.
	 * @return array{title: string, url: string}|null
	 */
	private static function clean_link( $link ): ?array {
		if ( ! is_array( $link ) || empty( $link['url'] ) || empty( $link['title'] ) ) {
			return null;
		}
		$url = esc_url_raw( (string) $link['url'] );
		return '' === $url ? null : array(
			'title' => \MediaShield\Support\Text::plain( (string) $link['title'] ),
			'url'   => $url,
		);
	}

	/**
	 * Must this viewer log in before this video can play?
	 *
	 * True only for a guest while Require Login is on, unless an integration
	 * lets guests watch this one video (a free-preview lesson, say). The one
	 * answer the access check, /session/start and the player markup share, so
	 * the login overlay and the stream URL can never disagree. Passing the
	 * login gate is not permission to watch: can_watch() still runs the role,
	 * domain and mediashield_can_watch checks.
	 *
	 * @since 1.4.1
	 *
	 * @param int $video_id Video CPT post ID.
	 * @param int $user_id  User ID (0 for guests).
	 * @return bool
	 */
	public static function login_gate_applies( int $video_id, int $user_id ): bool {
		if ( $user_id || ! Settings::get( 'ms_require_login' ) ) {
			return false;
		}

		/**
		 * Filter whether a guest may watch this video while Require Login is on.
		 *
		 * Return true to skip the login gate for this video only. The role,
		 * allowed-domain and mediashield_can_watch checks still apply.
		 *
		 * @since 1.4.1
		 *
		 * @param bool $allowed  Default false.
		 * @param int  $video_id Video CPT post ID.
		 */
		return ! (bool) apply_filters( 'mediashield_guest_can_watch', false, $video_id );
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
	 * A page this site renders, and a logged-in member's REST call, are this
	 * site and always pass. Otherwise an empty Referer is treated as a bypass
	 * attempt — when a whitelist is configured, the safe default is to deny
	 * rather than allow. Sites that
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
		// Sec-Fetch-* are set by the browser and cannot be removed by the page
		// doing the framing. A browser too old to send them sends neither.
		$dest    = isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ) ) : '';
		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;

		if ( empty( $referer ) ) {
			// The whitelist is about where the player is EMBEDDED. A page this
			// site renders is this site, whatever link brought the visitor in
			// (bookmark, email, typed, the redirect after login), and a REST
			// call from a logged-in member carries a nonce only this site's
			// pages hand out. Neither can be a page elsewhere, so the site's
			// own students are never blocked (Basecamp 10499053739). A page
			// framed elsewhere, and a guest's player request with no Referer,
			// are still refused unless the filter opts in.
			// A lesson framed on another site with referrerpolicy=no-referrer
			// is still told apart from a direct visit by Sec-Fetch-Dest. A
			// browser too old to send it is treated as a direct visit, so
			// students are never blocked.
			$site   = isset( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ) ) : '';
			$framed = in_array( $dest, array( 'iframe', 'frame', 'embed', 'object' ), true );
			if ( ! $framed && ! $is_rest && ! wp_doing_ajax() ) {
				return self::allow();
			}
			if ( $is_rest && is_user_logged_in() && 'cross-site' !== $site ) {
				return self::allow();
			}

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

		// A top-level visit is this site's own page, whatever site linked to
		// it: a student clicking their lesson link in a newsletter or forum
		// is not an embed (Basecamp 10501816150). Only the browser's own
		// Sec-Fetch-Dest: document says so; without it the Referer decides.
		if ( 'document' === $dest && ! $is_rest && ! wp_doing_ajax() ) {
			return self::allow();
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
