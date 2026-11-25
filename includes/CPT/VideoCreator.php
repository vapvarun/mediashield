<?php
/**
 * Create a MediaShield video from another plugin: the `mediashield_create_video` door.
 *
 * @package MediaShield\CPT
 */

namespace MediaShield\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Support\Platforms;
use MediaShield\Upload\Drivers\SelfHosted;
use MediaShield\Upload\LibraryFiles;
use WP_Error;
use WP_Query;

/**
 * The one way another plugin adds a video to the library.
 *
 * Learnomy's lesson editor ("Add a video" in the protected-video chooser,
 * converting a video lesson to protected) asks here instead of writing
 * MediaShield's data itself (Integration Standard):
 *
 *     $id = apply_filters( 'mediashield_create_video', null, array(
 *         'source_url'    => 'https://youtu.be/…', // or
 *         'attachment_id' => 123,                   // an uploaded video file
 *         'title'         => 'Lesson 1',            // optional
 *         'replacing'     => array( 'type' => 'learnomy_lesson', 'id' => 42 ), // optional
 *     ) );
 *     // int video id, or WP_Error (status 400 / 403 / 404 / 500).
 *
 * It takes the path an owner's own "Add video" takes: the link check
 * (Platforms::recognise(), the same words as the screens), the same insert
 * and save hooks (platform and id normalised, the platform's title when none
 * is given, the Videos list status, a Media Library file moved into the
 * protected folder), no protection level stamped (the video follows the site
 * default), and a link already in the library returns that video, as the
 * Video block does (find()).
 */
class VideoCreator {

	/**
	 * Hook the door.
	 */
	public static function register(): void {
		add_filter( 'mediashield_create_video', array( __CLASS__, 'filter' ), 10, 2 );
	}

	/**
	 * The `mediashield_create_video` filter.
	 *
	 * @param mixed $result A value from an earlier callback; an int or WP_Error is kept.
	 * @param mixed $args   { source_url?: string, attachment_id?: int, title?: string }.
	 * @return int|WP_Error Video ID, or why not.
	 */
	public static function filter( $result, $args ) {
		if ( is_int( $result ) || is_wp_error( $result ) ) {
			return $result;
		}
		return self::create( is_array( $args ) ? $args : array() );
	}

	/**
	 * Create (or find) the video.
	 *
	 * `replacing` names the one reference about to switch from the file to
	 * this video ({ type: 'post'|'learnomy_lesson'|…, id: int }): the in-use
	 * check that decides whether a Media Library file may move does not count
	 * it (LibraryFiles::while_replacing()); every other use keeps the file in
	 * place. The caller switches its reference only after an int comes back.
	 *
	 * @param array $args { source_url?: string, attachment_id?: int, title?: string, replacing?: array{type: string, id: int} }.
	 * @return int|WP_Error Video ID, or why not.
	 */
	public static function create( array $args ) {
		$replacing = self::replacing( $args['replacing'] ?? null );
		return LibraryFiles::while_replacing(
			$replacing,
			static function () use ( $args, $replacing ) {
				$id = self::create_video( $args );
				// A video already made for this file may have been left in
				// place because of the very reference now switching.
				if ( is_int( $id ) && $replacing ) {
					LibraryFiles::recheck_in_use( $id );
				}
				return $id;
			}
		);
	}

	/**
	 * The `replacing` arg, cleaned: { type, id }, or empty.
	 *
	 * @param mixed $replacing Raw arg.
	 * @return array{type?: string, id?: int}
	 */
	private static function replacing( $replacing ): array {
		if ( ! is_array( $replacing ) ) {
			return array();
		}
		$type = sanitize_key( (string) ( $replacing['type'] ?? '' ) );
		$id   = absint( $replacing['id'] ?? 0 );
		return ( '' !== $type && $id > 0 ) ? array(
			'type' => $type,
			'id'   => $id,
		) : array();
	}

	/**
	 * Create (or find) the video (create(), inside the replacing context).
	 *
	 * @param array $args See create().
	 * @return int|WP_Error Video ID, or why not.
	 */
	private static function create_video( array $args ) {
		$type = get_post_type_object( 'mediashield_video' );
		if ( ! $type || ! current_user_can( $type->cap->create_posts ) ) {
			return new WP_Error( 'mediashield_cannot_create_video', __( 'You are not allowed to add videos.', 'mediashield' ), array( 'status' => 403 ) );
		}

		$title      = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		$attachment = absint( $args['attachment_id'] ?? 0 );
		$url        = trim( (string) ( $args['source_url'] ?? '' ) );

		if ( $attachment ) {
			if ( 'attachment' !== get_post_type( $attachment ) || ! wp_attachment_is( 'video', $attachment ) ) {
				return new WP_Error( 'mediashield_not_a_video_file', __( 'That file is not a video in the Media Library.', 'mediashield' ), array( 'status' => 404 ) );
			}
			if ( ! current_user_can( 'edit_post', $attachment ) ) {
				return new WP_Error( 'mediashield_cannot_create_video', __( 'You are not allowed to use that file.', 'mediashield' ), array( 'status' => 403 ) );
			}
			$existing = self::find_by_attachment( $attachment );
			if ( $existing ) {
				return $existing;
			}
			$path = (string) get_attached_file( $attachment );
			$url  = (string) wp_get_attachment_url( $attachment );
			if ( '' === $title ) {
				$title = sanitize_text_field( get_the_title( $attachment ) );
			}
		}

		$link = Platforms::recognise( $url );
		if ( is_wp_error( $link ) ) {
			return $link;
		}

		$existing = self::find( $link['platform'], $link['video_id'], $url );
		if ( $existing ) {
			return $existing;
		}

		// The same insert as the REST route and the edit screen: platform,
		// provider id and link in place before save_post, so the save hooks
		// (normalize_platform(), Thumbnail's title and poster, VideoStatus,
		// LibraryFiles) see them. No protection level: the site default.
		$id = wp_insert_post(
			array(
				'post_type'   => 'mediashield_video',
				// As core's REST route allows: someone who cannot publish adds
				// a video for review (Free review #10).
				'post_status' => current_user_can( $type->cap->publish_posts ) ? 'publish' : 'pending',
				'post_title'  => $title,
				'meta_input'  => array_filter(
					array(
						'_ms_platform'          => $link['platform'],
						'_ms_platform_video_id' => $link['video_id'],
						'_ms_source_url'        => esc_url_raw( $url ),
					),
					static fn( $value ) => '' !== $value
				),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			$id->add_data( array( 'status' => 500 ) );
			return $id;
		}

		// An uploaded file: the name it was uploaded with and its length, as
		// an upload through "Add video" records them (SelfHosted).
		// The save hooks may just have moved the file into the protected
		// folder (LibraryFiles), so the length is read from where it is now.
		if ( $attachment ) {
			update_post_meta( $id, SelfHosted::ORIGINAL_NAME, sanitize_file_name( wp_basename( $path ) ) );
			SelfHosted::record_length( $id, (string) get_attached_file( $attachment ) );
		}

		return (int) $id;
	}

	/**
	 * The library video a link already is: same platform and platform id, or
	 * for a video file the same address. Oldest first, so the video people have
	 * been using wins over a later copy. 0 when the link is new.
	 *
	 * Shared by this door and the Video block (VideoSummaryController::match()).
	 *
	 * @param string $platform Platform from Platforms::recognise().
	 * @param string $video_id Platform video id ('' for a video file).
	 * @param string $url      The link as given.
	 * @return int Video ID, 0 when the link is new.
	 */
	public static function find( string $platform, string $video_id, string $url ): int {
		$meta_query = '' !== $video_id
			? array(
				'relation' => 'AND',
				array(
					'key'   => '_ms_platform',
					'value' => $platform,
				),
				array(
					'key'   => '_ms_platform_video_id',
					'value' => $video_id,
				),
			)
			: array(
				array(
					'key'   => '_ms_source_url',
					'value' => esc_url_raw( $url ),
				),
			);

		return self::first_readable( $meta_query );
	}

	/**
	 * The video already made from this Media Library file, once its file was
	 * moved into the protected folder (LibraryFiles keeps the attachment id in
	 * `_ms_library_restore`). 0 when none.
	 *
	 * @param int $attachment Attachment ID.
	 * @return int Video ID.
	 */
	private static function find_by_attachment( int $attachment ): int {
		return self::first_readable(
			array(
				array(
					'key'     => '_ms_library_restore',
					'value'   => '"attachment";i:' . $attachment . ';',
					'compare' => 'LIKE',
				),
			)
		);
	}

	/**
	 * The oldest library video matching a meta query that the user may read.
	 *
	 * @param array $meta_query Meta query.
	 * @return int Video ID, 0 when none.
	 */
	private static function first_readable( array $meta_query ): int {
		$query = new WP_Query(
			array(
				'post_type'              => 'mediashield_video',
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Keyed on the indexed meta_key; one lookup per call.
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'posts_per_page'         => 5,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $query->posts as $id ) {
			if ( current_user_can( 'read_post', (int) $id ) ) {
				return (int) $id;
			}
		}
		return 0;
	}
}
