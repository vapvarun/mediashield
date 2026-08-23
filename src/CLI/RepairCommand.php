<?php
/**
 * WP-CLI repair routines for videos saved with bad platform detection.
 *
 * @package MediaShield\CLI
 */

namespace MediaShield\CLI;

defined( 'ABSPATH' ) || exit;

use WP_CLI;
use WP_Query;

/**
 * Repairs video records that platform auto-detection got wrong.
 *
 * WHY THIS EXISTS
 *
 * Pasting a Bunny dashboard URL (the address bar while looking at a video in
 * Bunny Stream) used to match no known pattern, so detection fell through to
 * its default of "self-hosted" and saved an empty platform video id. Nothing
 * said so: the admin row read "Self-hosted", which looks like a successful
 * detection, and the player then pointed a <video> element at a URL serving an
 * HTML page - which does not error, it hangs (BC#10225483994).
 *
 * Detection now recognises those URLs, but that only helps the NEXT paste. Any
 * customer who already pasted from the dashboard has broken videos and no way
 * to find them: nothing in the admin marks them, and the only symptom is a
 * player that never starts. On the site where this was found, 19 of 26 videos
 * were affected.
 *
 * @since 1.3.0
 */
final class RepairCommand {

	/**
	 * Finds videos saved as self-hosted from a Bunny dashboard URL and repairs them.
	 *
	 * Extracts the video GUID from the stored URL, then sets the platform to
	 * `bunny` and the platform video id to that GUID - the same shape the
	 * records that DID detect correctly already carry.
	 *
	 * Collection URLs are reported but never rewritten. A collection is a folder
	 * of videos and its GUID is not a video GUID, so "repairing" one would swap
	 * a visibly broken record for a confidently wrong one.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : List what would change without writing anything. Default behaviour;
	 *   pass --execute to actually write.
	 *
	 * [--execute]
	 * : Apply the changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mediashield repair bunny-urls
	 *     wp mediashield repair bunny-urls --execute
	 *
	 * @subcommand bunny-urls
	 *
	 * @param array $args       Positional args (unused).
	 * @param array $assoc_args Associative args.
	 */
	public function bunny_urls( $args, $assoc_args ): void {
		$execute = isset( $assoc_args['execute'] );

		$query = new WP_Query(
			array(
				'post_type'      => 'mediashield_video',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// No meta_query on platform: a record saved before the platform
				// meta existed has no row at all, and would be skipped by one.
				'no_found_rows'  => true,
			)
		);

		$repairable  = array();
		$collections = array();

		foreach ( $query->posts as $video_id ) {
			$video_id = (int) $video_id;
			$platform = (string) get_post_meta( $video_id, '_ms_platform', true );
			$source   = (string) get_post_meta( $video_id, '_ms_source_url', true );

			// Only touch records detection got wrong. A video already saved as
			// bunny is either correct or was fixed by hand; either way it is not
			// ours to rewrite.
			if ( '' === $source || ( '' !== $platform && 'self' !== $platform ) ) {
				continue;
			}

			if ( ! preg_match( '#dash\.bunny\.net/#i', $source ) ) {
				continue;
			}

			if ( preg_match( '#dash\.bunny\.net/stream/\d+/library/collections/#i', $source ) ) {
				$collections[] = $video_id;
				continue;
			}

			if ( preg_match( '#dash\.bunny\.net/stream/\d+/library/([a-f0-9-]{36})#i', $source, $m ) ) {
				$repairable[ $video_id ] = strtolower( $m[1] );
			}
		}

		if ( empty( $repairable ) && empty( $collections ) ) {
			WP_CLI::success( 'No videos are affected. Nothing to repair.' );
			return;
		}

		foreach ( $repairable as $video_id => $guid ) {
			WP_CLI::log(
				sprintf(
					'%s #%d "%s" -> platform=bunny, video id=%s',
					$execute ? 'REPAIRED' : 'would repair',
					$video_id,
					get_the_title( $video_id ),
					$guid
				)
			);

			if ( $execute ) {
				update_post_meta( $video_id, '_ms_platform', 'bunny' );
				update_post_meta( $video_id, '_ms_platform_video_id', $guid );
			}
		}

		foreach ( $collections as $video_id ) {
			WP_CLI::warning(
				sprintf(
					'#%d "%s" points at a Bunny COLLECTION, not a video. Not repaired - open the video in Bunny Stream and paste its own URL.',
					$video_id,
					get_the_title( $video_id )
				)
			);
		}

		if ( ! $execute ) {
			WP_CLI::success(
				sprintf(
					'Dry run: %d video(s) would be repaired, %d need manual attention. Re-run with --execute to apply.',
					count( $repairable ),
					count( $collections )
				)
			);
			return;
		}

		WP_CLI::success(
			sprintf(
				'Repaired %d video(s). %d still need manual attention.',
				count( $repairable ),
				count( $collections )
			)
		);
	}
}
