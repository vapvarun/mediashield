<?php
/**
 * Signed, short-lived embed links.
 *
 * WHY THESE ARE SIGNED RATHER THAN COOKIE-AUTHENTICATED
 *
 * The consumer is a native mobile app rendering the player in a web frame. That
 * frame carries no WordPress session, so a normal logged-in page would render
 * the login gate instead of the video. The token therefore IS the credential:
 * it names the video, the viewer it was minted for, and the moment it stops
 * working.
 *
 * The token is not the authorisation, though. `AccessControl::can_watch()` runs
 * again when the page renders, for the user named in the token — so a viewer
 * whose access was revoked in the fifteen minutes since the link was minted is
 * refused, and a token cannot outlive the permission it was issued under. The
 * signature answers "who is this for", the access check answers "may they still
 * watch".
 *
 * @package MediaShield
 * @since   1.3.0
 */

namespace MediaShield\Embed;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and verifies signed embed URLs.
 */
class EmbedLink {

	/**
	 * Query var carrying the token on the front end.
	 */
	public const QUERY_VAR = 'mediashield_embed';

	/**
	 * How long a link stays valid, in seconds.
	 *
	 * Deliberately short. A consumer is expected to ask for a link at playback
	 * time rather than store one: a cached link is a link that outlives the
	 * reason it was issued.
	 */
	public const TTL = 900;

	/**
	 * Mint a link for one viewer and one video.
	 *
	 * @param int $video_id Video CPT post ID.
	 * @param int $user_id  The viewer this link is for. 0 for a guest.
	 * @param int $ttl      Lifetime in seconds. Defaults to self::TTL.
	 * @return string Absolute URL, or '' when the video cannot be embedded.
	 */
	public static function url( int $video_id, int $user_id = 0, int $ttl = 0 ): string {
		$video = get_post( $video_id );

		// Never advertise a URL for something that is not there. A consumer can
		// then tell "this lesson has no video" from "here is a link that fails",
		// which is the difference between a clear empty state and a broken one.
		if ( ! $video || 'mediashield_video' !== $video->post_type || 'publish' !== $video->post_status ) {
			return '';
		}

		$ttl     = $ttl > 0 ? $ttl : self::TTL;
		$expires = time() + $ttl;
		$token   = self::sign( $video_id, $user_id, $expires );

		/**
		 * Filter a minted embed URL.
		 *
		 * @since 1.3.0
		 *
		 * @param string $url      The embed URL.
		 * @param int    $video_id Video CPT post ID.
		 * @param int    $user_id  Viewer the link was minted for.
		 */
		return (string) apply_filters(
			'mediashield_embed_url',
			add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) ),
			$video_id,
			$user_id
		);
	}

	/**
	 * Read a token back, if it is intact and still in date.
	 *
	 * @param string $token Raw token from the query string.
	 * @return array{video_id:int,user_id:int}|null Null when unusable.
	 */
	public static function verify( string $token ): ?array {
		$parts = explode( '.', $token );
		if ( 4 !== count( $parts ) ) {
			return null;
		}

		list( $video_id, $user_id, $expires, $signature ) = $parts;

		$video_id = absint( $video_id );
		$user_id  = absint( $user_id );
		$expires  = absint( $expires );

		if ( ! $video_id || ! $expires ) {
			return null;
		}

		// Expiry first: a stale token is the common case and needs no crypto.
		if ( $expires < time() ) {
			return null;
		}

		// hash_equals, never ===, so a wrong signature costs the same time as a
		// right one and cannot be guessed a character at a time.
		if ( ! hash_equals( self::signature( $video_id, $user_id, $expires ), (string) $signature ) ) {
			return null;
		}

		return array(
			'video_id' => $video_id,
			'user_id'  => $user_id,
		);
	}

	/**
	 * The token: the claim and its signature.
	 */
	private static function sign( int $video_id, int $user_id, int $expires ): string {
		return implode(
			'.',
			array(
				$video_id,
				$user_id,
				$expires,
				self::signature( $video_id, $user_id, $expires ),
			)
		);
	}

	/**
	 * Signature over the whole claim.
	 *
	 * Keyed on `wp_salt( 'auth' )`, so links do not survive a salt rotation —
	 * which is what an operator rotating salts is asking for.
	 */
	private static function signature( int $video_id, int $user_id, int $expires ): string {
		return hash_hmac(
			'sha256',
			implode( '|', array( $video_id, $user_id, $expires ) ),
			wp_salt( 'auth' )
		);
	}
}
