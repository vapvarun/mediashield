<?php
/**
 * Is a video still available at its platform?
 *
 * @package MediaShield\CPT
 */

namespace MediaShield\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one record of "the platform says this video cannot play".
 *
 * The player reports a refusal from the platform (YouTube or Vimeo says the
 * video is private, removed or not embeddable); the server confirms it with
 * the platform before writing, so a visitor cannot mark a working video
 * broken. The Videos list reads it as the status 'unavailable' (Basecamp
 * 10545260568). Saving the video clears it, and so do playback (a
 * heartbeat that reports progress, clear_if_playing()) and a later report
 * the platform answers as playable (report()). Vimeo's answer for
 * an unlisted or domain-limited video is not taken as a refusal (check()).
 *
 * Not the same question as the 'encoding' / 'failed' states Pro reports for
 * a Bunny upload: those are about an upload that never finished; this is a
 * finished video the platform no longer serves.
 */
class SourceStatus {

	/**
	 * Video meta: { code: string, at: int (UTC timestamp) } while unavailable.
	 */
	const META = '_ms_source_unavailable';

	/**
	 * Reasons a player may report.
	 */
	const CODES = array( 'removed', 'private', 'not_embeddable', 'unknown' );

	/**
	 * Seconds between two checks of the same video with its platform.
	 */
	const CHECK_INTERVAL = 600;

	/**
	 * Hook the list status and the clear-on-save.
	 */
	public static function register(): void {
		add_filter( 'mediashield_video_status', array( __CLASS__, 'list_status' ), 20, 2 );
		add_action( 'save_post_mediashield_video', array( __CLASS__, 'clear_on_save' ), 10, 3 );
	}

	/**
	 * Record that the platform will not play this video.
	 *
	 * @param int    $video_id Video ID.
	 * @param string $code     One of CODES.
	 */
	public static function mark_unavailable( int $video_id, string $code ): void {
		$code = in_array( $code, self::CODES, true ) ? $code : 'unknown';
		update_post_meta(
			$video_id,
			self::META,
			array(
				'code' => $code,
				'at'   => time(),
			)
		);
	}

	/**
	 * Forget a recorded refusal.
	 *
	 * @param int $video_id Video ID.
	 */
	public static function clear( int $video_id ): void {
		delete_post_meta( $video_id, self::META );
	}

	/**
	 * The video just played (a heartbeat reported progress): a recorded
	 * refusal is over. The re-check the docblock above promises.
	 *
	 * @param int $video_id Video ID.
	 */
	public static function clear_if_playing( int $video_id ): void {
		if ( null !== self::get( $video_id ) ) {
			self::clear( $video_id );
		}
	}

	/**
	 * The recorded refusal, or null while the video is available.
	 *
	 * @param int $video_id Video ID.
	 * @return array{code: string, at: int}|null
	 */
	public static function get( int $video_id ): ?array {
		$value = get_post_meta( $video_id, self::META, true );
		if ( ! is_array( $value ) || empty( $value['code'] ) ) {
			return null;
		}
		return array(
			'code' => (string) $value['code'],
			'at'   => (int) ( $value['at'] ?? 0 ),
		);
	}

	/**
	 * Ask the platform whether it still plays the video.
	 *
	 * YouTube and Vimeo answer through oEmbed: 200 for a video anyone may
	 * embed, 401 / 403 / 404 for one that is private, removed or not
	 * embeddable. Other platforms have no public check, so the answer is
	 * null (unknown) and only someone who may edit the video can record it.
	 *
	 * @param int $video_id Video ID.
	 * @return bool|null True playable, false refused, null cannot tell.
	 */
	public static function check( int $video_id ): ?bool {
		$platform = (string) get_post_meta( $video_id, '_ms_platform', true );
		$id       = (string) get_post_meta( $video_id, '_ms_platform_video_id', true );

		if ( '' === $id ) {
			return null;
		}

		$hashed = false;
		if ( 'youtube' === $platform ) {
			$endpoint = 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( 'https://www.youtube.com/watch?v=' . $id );
		} elseif ( 'vimeo' === $platform ) {
			// The stored link keeps an unlisted video's hash (vimeo.com/123/abc,
			// ?h=abc); without it Vimeo refuses a video that plays fine here.
			$source   = (string) get_post_meta( $video_id, '_ms_source_url', true );
			$link     = false !== stripos( $source, 'vimeo.com' ) ? $source : 'https://vimeo.com/' . $id;
			$hashed   = (bool) preg_match( '#vimeo\.com/(?:video/)?\d+/[0-9a-f]+|[?&]h=[0-9a-f]+#i', $link );
			$endpoint = 'https://vimeo.com/api/oembed.json?url=' . rawurlencode( $link );
		} else {
			return null;
		}

		// As this site: an embed limited to the site's domain answers its Referer.
		$response = wp_safe_remote_get(
			$endpoint,
			array(
				'timeout' => 5,
				'headers' => array( 'Referer' => home_url( '/' ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $status ) {
			return true;
		}
		// Vimeo answers 403 for a video limited to certain sites and 404 for
		// an unlisted one asked without its hash: neither says the video is
		// gone, so a viewer's report alone records nothing (Free review #3).
		if ( 'vimeo' === $platform && ( 403 === $status || ( 404 === $status && $hashed ) ) ) {
			return null;
		}
		if ( in_array( $status, array( 401, 403, 404 ), true ) ) {
			return false;
		}
		return null;
	}

	/**
	 * A player says the platform refused the video: confirm and record it.
	 *
	 * Checked with the platform at most once per CHECK_INTERVAL per video; a
	 * report on a video already recorded re-checks it and clears the record
	 * when the platform plays it again. A platform with no public check is recorded only when the reporter may
	 * edit the video.
	 *
	 * @param int    $video_id Video ID.
	 * @param string $code     Reason the player saw.
	 * @return bool Whether the video is now recorded as unavailable.
	 */
	public static function report( int $video_id, string $code ): bool {
		$recorded = null !== self::get( $video_id );

		$lock = 'ms_source_check_' . $video_id;
		if ( get_transient( $lock ) ) {
			return $recorded;
		}
		set_transient( $lock, 1, self::CHECK_INTERVAL );

		$playable = self::check( $video_id );
		// Already recorded: the platform plays it again (the owner made it
		// public), so the record goes; any other answer keeps it.
		if ( $recorded ) {
			if ( true === $playable ) {
				self::clear( $video_id );
				return false;
			}
			return true;
		}
		// The platform says it plays, or cannot say and the reporter is a
		// viewer: nothing is recorded. Someone who edits the video saw the
		// refusal on their own screen, which is enough where there is no check.
		if ( true === $playable || ( null === $playable && ! current_user_can( 'edit_post', $video_id ) ) ) {
			if ( null === $playable ) {
				delete_transient( $lock );
			}
			return false;
		}

		self::mark_unavailable( $video_id, $code );
		return true;
	}

	/**
	 * The Videos list status: 'unavailable' while a refusal is recorded.
	 *
	 * @param string $status   Status so far.
	 * @param int    $video_id Video ID.
	 * @return string
	 */
	public static function list_status( $status, $video_id ) {
		return null !== self::get( (int) $video_id ) ? 'unavailable' : $status;
	}

	/**
	 * The owner saved the video (a new link, perhaps): check again from scratch.
	 *
	 * @param int      $video_id Video ID.
	 * @param \WP_Post $post     Post.
	 * @param bool     $update   Whether this is an update.
	 */
	public static function clear_on_save( $video_id, $post, $update ): void {
		if ( $update && ! wp_is_post_revision( (int) $video_id ) ) {
			self::clear( (int) $video_id );
			delete_transient( 'ms_source_check_' . (int) $video_id );
		}
	}
}
