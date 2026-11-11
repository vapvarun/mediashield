<?php
/**
 * Auto-fetch video thumbnails from platform APIs.
 *
 * @package MediaShield\CPT
 */

namespace MediaShield\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Thumbnail
 *
 * Auto-fetches video thumbnails from platform APIs on save.
 *
 * @since 1.0.0
 */
class Thumbnail {

	/**
	 * Option: set while the 1.4.2 poster rename still has videos to check.
	 * Holds the last video ID checked, so a pass resumes after it.
	 */
	public const RENAME_PENDING = 'ms_poster_names_pending';

	/**
	 * Background job hook for the poster rename.
	 */
	private const RENAME_JOB = 'ms_rename_id_posters';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( self::RENAME_JOB, array( __CLASS__, 'rename_batch' ) );
		add_action( 'init', array( __CLASS__, 'ensure_rename_job' ), 20 );
		// Classic editor / programmatic save: meta is written by save_meta_box at
		// priority 10, so reading platform meta at priority 20 sees the freshly
		// written values.
		add_action( 'save_post_mediashield_video', array( __CLASS__, 'maybe_fetch_thumbnail' ), 20, 2 );
		// REST create path (card #9924901405): WP_REST_Posts_Controller calls
		// wp_insert_post() WITHOUT the meta_input arg, so save_post fires while
		// _ms_platform / _ms_platform_video_id are still empty and the thumbnail
		// fetch above no-ops. The REST controller then writes meta and fires
		// rest_after_insert_{$post_type}, at which point we can safely re-run the
		// thumbnail logic (idempotent — guarded by has_post_thumbnail).
		add_action( 'rest_after_insert_mediashield_video', array( __CLASS__, 'maybe_fetch_thumbnail_rest' ), 10, 1 );
		// Runs first, so the thumbnail's attachment gets the real title too.
		add_action( 'rest_after_insert_mediashield_video', array( __CLASS__, 'maybe_use_platform_title' ), 5, 3 );
	}

	/**
	 * Replace the placeholder title the setup wizard and the Video block give
	 * a pasted URL ("youtube - VIDEO_ID") with the platform's own title.
	 *
	 * Only touches a title that is still exactly that placeholder, so a title
	 * the owner typed is never overwritten. Platforms with no oEmbed (Bunny)
	 * keep the placeholder.
	 *
	 * At most one oEmbed request, on REST create only (bulk and import paths
	 * use wp_insert_post and never get here). Discovery is off, so a URL with
	 * no known provider sends nothing; the request uses the WordPress HTTP
	 * timeout. A failed fetch keeps the placeholder.
	 *
	 * @param \WP_Post         $post     Post inserted or updated through REST.
	 * @param \WP_REST_Request $request  Request (unused).
	 * @param bool             $creating True on create, false on update.
	 */
	public static function maybe_use_platform_title( \WP_Post $post, $request = null, $creating = true ): void {
		if ( ! $creating ) {
			return;
		}

		$platform = (string) get_post_meta( $post->ID, '_ms_platform', true );
		$video_id = (string) get_post_meta( $post->ID, '_ms_platform_video_id', true );
		$source   = (string) get_post_meta( $post->ID, '_ms_source_url', true );

		if ( '' === $video_id || '' === $source || 'self' === $platform ) {
			return;
		}
		if ( $post->post_title !== $platform . ' - ' . $video_id ) {
			return;
		}

		$data  = _wp_oembed_get_object()->get_data( $source, array( 'discover' => false ) );
		$title = ( $data && ! empty( $data->title ) ) ? sanitize_text_field( (string) $data->title ) : '';
		if ( '' === $title ) {
			return;
		}

		wp_update_post(
			array(
				'ID'         => $post->ID,
				'post_title' => $title,
			)
		);
		// The thumbnail handler (priority 10) reads the same object.
		$post->post_title = $title;
	}

	/**
	 * REST-side bridge into maybe_fetch_thumbnail().
	 *
	 * The rest_after_insert_* action passes the WP_Post directly (no separate
	 * $post_id arg), so we wrap the signature here rather than overloading the
	 * save_post handler.
	 *
	 * @param \WP_Post $post Post object inserted/updated via REST.
	 */
	public static function maybe_fetch_thumbnail_rest( \WP_Post $post ): void {
		self::maybe_fetch_thumbnail( (int) $post->ID, $post );
	}

	/**
	 * Fetch and set thumbnail on video save if none is set.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function maybe_fetch_thumbnail( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip if featured image is already set.
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		$platform = get_post_meta( $post_id, '_ms_platform', true );
		$video_id = get_post_meta( $post_id, '_ms_platform_video_id', true );

		if ( empty( $video_id ) || 'self' === $platform ) {
			return;
		}

		$thumbnail_url = self::get_thumbnail_url( $platform, $video_id );

		if ( empty( $thumbnail_url ) ) {
			return;
		}

		self::sideload_poster( $thumbnail_url, $post_id, (string) $platform, (string) $video_id, $post->post_title );
	}

	/**
	 * Download a remote image and make it the video's poster (featured image).
	 *
	 * The one sideload for every platform poster, Free and Pro: the file is
	 * named by poster_filename() and the title never carries the platform ID.
	 *
	 * Wraps download_url() + wp_handle_sideload() instead of
	 * media_sideload_image() because the WP core helper infers the filename
	 * from the URL via a regex that requires `.jpg`/`.png`/etc. BEFORE any
	 * query string. Vimeo and Wistia serve thumbnails from CDN URLs that have
	 * no extension at all (e.g. `i.vimeocdn.com/video/…_d_640?region=us`), so
	 * media_sideload_image() rejects them with "Invalid image URL.". We build
	 * an explicit filename from the platform + video ID + content-type-derived
	 * extension instead, so every supported platform's thumbnail can be
	 * sideloaded regardless of URL shape.
	 *
	 * @param string $url       Remote image URL.
	 * @param int    $post_id   Parent post.
	 * @param string $platform  Platform slug (youtube/vimeo/wistia/bunny).
	 * @param string $video_id  Platform video ID, hashed into the file name.
	 * @param string $title     Post title, used for the attachment title.
	 * @return int|\WP_Error    Attachment ID on success, WP_Error on failure.
	 */
	public static function sideload_poster( string $url, int $post_id, string $platform, string $video_id, string $title ) {
		// Require the sideload helpers (these aren't loaded on frontend / REST).
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( ! function_exists( 'wp_read_image_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Read the downloaded file's MIME via WP's image checker; this also
		// confirms the payload is a real image, not (say) an HTML error page.
		$probe = wp_check_filetype_and_ext( $tmp, basename( $tmp ) );
		$mime  = $probe['type'] ?? '';
		if ( '' === $mime ) {
			// Fall back to finfo for URLs that don't pass WP's name-based hints.
			if ( function_exists( 'mime_content_type' ) ) {
				$mime = (string) @mime_content_type( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		if ( '' === $mime || 0 !== strpos( $mime, 'image/' ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			return new \WP_Error( 'mediashield_thumbnail_not_image', sprintf( 'Downloaded payload is not an image (mime=%s).', $mime ) );
		}

		$ext_map = array(
			'image/jpeg' => 'jpg',
			'image/jpg'  => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);
		$ext     = $ext_map[ $mime ] ?? 'jpg';

		$filename = self::poster_filename( $platform, $video_id, $post_id, $ext );

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		// wp_handle_sideload moves the file into uploads/ and returns its
		// final URL + path; test_form=false skips the form-data check that
		// would otherwise reject a programmatic upload.
		$sideloaded = wp_handle_sideload(
			$file_array,
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'gif'      => 'image/gif',
					'webp'     => 'image/webp',
				),
			)
		);

		if ( isset( $sideloaded['error'] ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			return new \WP_Error( 'mediashield_thumbnail_sideload_failed', (string) $sideloaded['error'] );
		}

		$attachment    = array(
			'post_mime_type' => $sideloaded['type'],
			'post_title'     => self::poster_title( $title, $video_id ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attachment_id = wp_insert_attachment( $attachment, $sideloaded['file'], $post_id, true );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate metadata + intermediate sizes so the thumbnail renders at
		// the size the theme requests (e.g. post grids ask for medium).
		$metadata = wp_generate_attachment_metadata( $attachment_id, $sideloaded['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		set_post_thumbnail( $post_id, $attachment_id );

		return (int) $attachment_id;
	}

	/**
	 * The file name of a platform poster: never the platform video ID.
	 *
	 * The poster is shown to viewers who may not watch and listed by
	 * /wp/v2/media, and the ID of an unlisted video gets past Require login
	 * (Basecamp 10499285942). A keyed hash stays stable per video without
	 * revealing it. Used by sideload_poster() and the 1.4.2 rename.
	 *
	 * @param string $platform Platform slug.
	 * @param string $video_id Platform video ID.
	 * @param int    $post_id  Video post ID.
	 * @param string $ext      File extension, without the dot.
	 * @return string File name, e.g. mediashield-poster-1a2b3c4d5e6f.jpg.
	 */
	public static function poster_filename( string $platform, string $video_id, int $post_id, string $ext = 'jpg' ): string {
		return sprintf( 'mediashield-poster-%s.%s', substr( wp_hash( $platform . '|' . $video_id . '|' . $post_id ), 0, 12 ), $ext );
	}

	/**
	 * Does this text reveal the platform video ID, in any form WordPress stores it?
	 *
	 * Matches the raw ID, its file-name form and its slug form (lower case),
	 * case-insensitively.
	 *
	 * @param string $text     File name, title, slug, URL or caption.
	 * @param string $video_id Platform video ID.
	 * @return bool
	 */
	public static function carries_id( string $text, string $video_id ): bool {
		if ( '' === $text || '' === $video_id ) {
			return false;
		}
		foreach ( array_unique( array( $video_id, sanitize_file_name( $video_id ), sanitize_title( $video_id ) ) ) as $form ) {
			if ( '' !== $form && false !== stripos( $text, $form ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The poster URL a viewer may be shown. Read every poster through this.
	 *
	 * A poster whose file name still carries the platform ID (saved before
	 * 1.4.2 and not renamed yet) is left out for a viewer who may not watch.
	 *
	 * @param int       $video_id  Video post ID.
	 * @param string    $size      Image size.
	 * @param bool|null $may_watch The render's own access decision; null asks AccessControl.
	 * @return string URL, or '' when there is none to show.
	 */
	public static function poster_url( int $video_id, string $size = 'large', ?bool $may_watch = null ): string {
		$url = (string) get_the_post_thumbnail_url( $video_id, $size );
		if ( '' === $url || ! self::carries_id( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ), (string) get_post_meta( $video_id, '_ms_platform_video_id', true ) ) ) {
			return $url;
		}
		if ( null === $may_watch ) {
			$may_watch = \MediaShield\Access\AccessControl::can_watch( $video_id, get_current_user_id() )['allowed'];
		}
		return $may_watch ? $url : '';
	}

	/**
	 * An attachment title that never carries the platform ID.
	 *
	 * The placeholder title "youtube - <id>" (and Bunny's, which keeps it)
	 * would put the ID in the title and slug.
	 *
	 * @param string $title    Proposed title.
	 * @param string $video_id Platform video ID.
	 * @return string
	 */
	private static function poster_title( string $title, string $video_id ): string {
		return ( '' === $title || self::carries_id( $title, $video_id ) ) ? __( 'Video poster', 'mediashield' ) : $title;
	}

	/**
	 * Queue the 1.4.2 poster rename while it is pending. Repairs itself if the action is lost.
	 */
	public static function ensure_rename_job(): void {
		if ( false !== get_option( self::RENAME_PENDING, false ) ) {
			\MediaShield\Cron\Cleanup::schedule_single( self::RENAME_JOB, time() + MINUTE_IN_SECONDS );
		}
	}

	/**
	 * One pass of the 1.4.2 upgrade: rename the posters of the next videos.
	 *
	 * Walks platform videos in ID order after the stored cursor, so a pass
	 * interrupted half way resumes where it stopped, and a 2000-video site is
	 * done in small background pages. The option is removed when none are left.
	 */
	public static function rename_batch(): void {
		global $wpdb;

		$batch  = max( 10, min( 200, (int) apply_filters( 'mediashield_poster_rename_batch_size', 50 ) ) );
		$cursor = (int) get_option( self::RENAME_PENDING, 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off background walk; ID > cursor paging is not expressible in WP_Query.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_ms_platform_video_id' AND m.meta_value <> ''
				 WHERE p.post_type = 'mediashield_video' AND p.ID > %d
				 ORDER BY p.ID ASC LIMIT %d",
				$cursor,
				$batch
			)
		);

		if ( empty( $ids ) ) {
			delete_option( self::RENAME_PENDING );
			return;
		}

		foreach ( $ids as $id ) {
			self::rename_posters( (int) $id );
		}

		update_option( self::RENAME_PENDING, (int) end( $ids ), false );
		\MediaShield\Cron\Cleanup::schedule_single( self::RENAME_JOB, time() + MINUTE_IN_SECONDS );
	}

	/**
	 * Rename every image of this video whose name, title or text carries its platform ID.
	 *
	 * Covers the featured image and any image attached to the video (an older
	 * poster replaced by a newer one is still listed by /wp/v2/media). Posters
	 * with no ID in them, such as one the owner uploaded, are left alone.
	 *
	 * @param int $video_id Video post ID.
	 * @return int How many attachments changed.
	 */
	public static function rename_posters( int $video_id ): int {
		$platform_id = (string) get_post_meta( $video_id, '_ms_platform_video_id', true );
		if ( '' === $platform_id ) {
			return 0;
		}

		$children = get_children(
			array(
				'post_parent'    => $video_id,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'fields'         => 'ids',
			)
		);
		$ids      = array_unique( array_filter( array_map( 'intval', array_merge( array( (int) get_post_thumbnail_id( $video_id ) ), $children ) ) ) );

		$changed = 0;
		foreach ( $ids as $id ) {
			if ( self::rename_attachment( $id, $video_id, $platform_id ) ) {
				++$changed;
			}
		}
		return $changed;
	}

	/**
	 * Rename one poster: its file and every size, title, slug, GUID, caption and alt text.
	 *
	 * @param int    $id          Attachment ID.
	 * @param int    $video_id    Video post ID.
	 * @param string $platform_id Platform video ID.
	 * @return bool True when anything changed.
	 */
	private static function rename_attachment( int $id, int $video_id, string $platform_id ): bool {
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}

		$file = (string) get_attached_file( $id, true );
		$meta = wp_get_attachment_metadata( $id, true );
		$meta = is_array( $meta ) ? $meta : array();
		$alt  = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );

		$file_leaks = '' !== $file && self::carries_id( wp_basename( $file ) . ' ' . ( $meta['original_image'] ?? '' ), $platform_id );
		$text_leaks = self::carries_id( implode( ' ', array( $post->post_title, $post->post_name, $post->post_excerpt, $post->post_content, $alt ) ), $platform_id );
		if ( ! $file_leaks && ! $text_leaks && ! self::carries_id( $post->guid, $platform_id ) ) {
			return false;
		}

		if ( $file_leaks && file_exists( $file ) ) {
			$dir      = dirname( $file );
			$old_stem = pathinfo( (string) ( $meta['original_image'] ?? $file ), PATHINFO_FILENAME );
			$new_stem = pathinfo( wp_unique_filename( $dir, self::poster_filename( (string) get_post_meta( $video_id, '_ms_platform', true ), $platform_id, $video_id, strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) ), PATHINFO_FILENAME );

			// Sizes, the main file and the pre-scaling original all start with
			// the same stem; each moves to the same name under the new stem.
			$move = static function ( string $name ) use ( $dir, $old_stem, $new_stem ): string {
				if ( 0 !== strpos( $name, $old_stem ) ) {
					return $name;
				}
				$to = $new_stem . substr( $name, strlen( $old_stem ) );
				if ( file_exists( $dir . '/' . $name ) && ! file_exists( $dir . '/' . $to ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename, WordPress.PHP.NoSilencedErrors.Discouraged -- Renaming inside uploads; a failure keeps the old name.
					if ( ! @rename( $dir . '/' . $name, $dir . '/' . $to ) ) {
						return $name;
					}
				}
				return $to;
			};

			foreach ( $meta['sizes'] ?? array() as $size => $data ) {
				if ( ! empty( $data['file'] ) ) {
					$meta['sizes'][ $size ]['file'] = $move( (string) $data['file'] );
				}
			}
			$original = (string) ( $meta['original_image'] ?? '' );
			if ( '' !== $original ) {
				$meta['original_image'] = $move( $original );
			}
			$new_file = $dir . '/' . $move( wp_basename( $file ) );
			if ( $new_file !== $file ) {
				update_attached_file( $id, $new_file );
				$meta['file'] = _wp_relative_upload_path( $new_file );
			}
			wp_update_attachment_metadata( $id, $meta );
		}

		$title = self::carries_id( $post->post_title, $platform_id ) ? self::poster_title( (string) get_post_field( 'post_title', $video_id ), $platform_id ) : $post->post_title;
		wp_update_post(
			array(
				'ID'           => $id,
				'post_title'   => $title,
				'post_name'    => self::carries_id( $post->post_name, $platform_id ) ? sanitize_title( $title ) : $post->post_name,
				'post_excerpt' => self::carries_id( $post->post_excerpt, $platform_id ) ? '' : $post->post_excerpt,
				'post_content' => self::carries_id( $post->post_content, $platform_id ) ? '' : $post->post_content,
			)
		);
		// wp_update_post() never rewrites a GUID; core sets it the same way on insert.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No core API updates a GUID; the cache is cleared below.
		$wpdb->update( $wpdb->posts, array( 'guid' => (string) wp_get_attachment_url( $id ) ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		if ( self::carries_id( $alt, $platform_id ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $title );
		}

		return true;
	}

	/**
	 * Get thumbnail URL for a platform video.
	 *
	 * @param string $platform Platform identifier.
	 * @param string $video_id Platform video ID.
	 * @return string Thumbnail URL or empty string.
	 */
	private static function get_thumbnail_url( string $platform, string $video_id ): string {
		switch ( $platform ) {
			case 'youtube':
				return "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";

			case 'vimeo':
				$response = wp_remote_get(
					"https://vimeo.com/api/oembed.json?url=https://vimeo.com/{$video_id}",
					array( 'timeout' => 15 )
				);
				if ( ! is_wp_error( $response ) ) {
					$data = json_decode( wp_remote_retrieve_body( $response ), true );
					return $data['thumbnail_url'] ?? '';
				}
				return '';

			case 'bunny':
				// Delegate to pro BunnyStream driver if available.
				if ( class_exists( '\MediaShieldPro\Upload\Drivers\BunnyStream' ) ) {
					$driver = new \MediaShieldPro\Upload\Drivers\BunnyStream();
					return $driver->get_thumbnail_url( $video_id );
				}
				return '';

			case 'wistia':
				$response = wp_remote_get(
					"https://fast.wistia.com/oembed?url=https://home.wistia.com/medias/{$video_id}",
					array( 'timeout' => 15 )
				);
				if ( ! is_wp_error( $response ) ) {
					$data = json_decode( wp_remote_retrieve_body( $response ), true );
					return $data['thumbnail_url'] ?? '';
				}
				return '';

			default:
				return '';
		}
	}
}
