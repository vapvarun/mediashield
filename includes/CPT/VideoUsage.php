<?php
/**
 * Where is each video used? (Videos list "Used in" column.)
 *
 * @package MediaShield\CPT
 */

namespace MediaShield\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds the pages, posts, lessons and playlists that show a video.
 *
 * Answers "which published or draft content or playlist shows video X?" for the Videos
 * list (Basecamp 10530930295), one batch per list page. Not the same question
 * as Upload\LibraryFiles' "is this FILE referenced anywhere", which scans meta
 * and options for a path.
 */
class VideoUsage {

	/**
	 * Answers for the current request, by video ID.
	 *
	 * @var array<int, array<int, array{id:int,title:string,url:string,type:string}>>
	 */
	private static $cache = array();

	/**
	 * Most places listed per video.
	 */
	const MAX_PER_VIDEO = 5;

	/**
	 * Look up several videos with one query and remember the answers.
	 *
	 * One scan of post_content for the MediaShield shortcode and block, for the
	 * whole list page. post_content is not indexed, so this is a table scan;
	 * it runs once per Videos list page (20 videos), never per row, and is
	 * capped at 500 matching posts. Then one indexed read of the playlist
	 * items table for the playlists that hold these videos.
	 *
	 * @param int[] $video_ids Video IDs.
	 */
	public static function prime( array $video_ids ): void {
		$video_ids = array_values( array_diff( array_unique( array_map( 'intval', $video_ids ) ), array_keys( self::$cache ), array( 0 ) ) );

		if ( empty( $video_ids ) ) {
			return;
		}

		foreach ( $video_ids as $id ) {
			self::$cache[ $id ] = array();
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One batched read per list page; see the docblock.
		$rows = $wpdb->get_results(
			"SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts}
			WHERE post_status IN ( 'publish', 'future', 'draft', 'private' )
			AND post_type NOT IN ( 'revision', 'mediashield_video', 'mediashield_playlist', 'nav_menu_item', 'attachment', 'wp_global_styles' )
			AND ( post_content LIKE '%[mediashield %' OR post_content LIKE '%wp:mediashield/video%' )
			ORDER BY post_modified_gmt DESC
			LIMIT 500"
		);

		$wanted = array_flip( $video_ids );

		foreach ( (array) $rows as $row ) {
			foreach ( self::ids_in( (string) $row->post_content ) as $id ) {
				if ( isset( $wanted[ $id ] ) && count( self::$cache[ $id ] ) < self::MAX_PER_VIDEO ) {
					self::$cache[ $id ][] = self::entry( (int) $row->ID, (string) $row->post_title, (string) $row->post_type );
				}
			}
		}

		// Playlists: a video added to a playlist is shown wherever the playlist
		// is, and the playlist's own page shows it too, so the playlist is a
		// place. Its items live in the playlist table, not in post_content.
		// One read for the list page, on the indexed video_id.
		$placeholders = implode( ', ', array_fill( 0, count( $video_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One batched read per list page; placeholders built above.
		$playlists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT pi.video_id, p.ID, p.post_title, p.post_type, p.post_modified_gmt
				FROM {$wpdb->prefix}ms_playlist_items pi
				INNER JOIN {$wpdb->posts} p ON p.ID = pi.playlist_id
				WHERE pi.video_id IN ( {$placeholders} )
				AND p.post_type = 'mediashield_playlist'
				AND p.post_status IN ( 'publish', 'future', 'draft', 'private' )
				ORDER BY p.post_modified_gmt DESC
				LIMIT 500",
				$video_ids
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		foreach ( (array) $playlists as $row ) {
			$id = (int) $row->video_id;
			if ( isset( $wanted[ $id ] ) && count( self::$cache[ $id ] ) < self::MAX_PER_VIDEO ) {
				self::$cache[ $id ][] = self::entry( (int) $row->ID, (string) $row->post_title, (string) $row->post_type );
			}
		}

		/**
		 * Filter where the listed videos are used.
		 *
		 * Pro adds the LMS lesson a video is linked to. Each entry is
		 * { id, title, url, type }; keep each list short.
		 *
		 * @since 1.4.3
		 *
		 * @param array<int, array> $usage     Places by video ID.
		 * @param int[]             $video_ids Videos being listed.
		 */
		$filtered = apply_filters( 'mediashield_video_used_in', array_intersect_key( self::$cache, $wanted ), $video_ids );

		foreach ( (array) $filtered as $id => $places ) {
			if ( isset( $wanted[ (int) $id ] ) ) {
				self::$cache[ (int) $id ] = array_slice( (array) $places, 0, self::MAX_PER_VIDEO );
			}
		}
	}

	/**
	 * Where one video is used.
	 *
	 * @param int $video_id Video ID.
	 * @return array<int, array{id:int,title:string,url:string,type:string}>
	 */
	public static function for_video( int $video_id ): array {
		if ( ! isset( self::$cache[ $video_id ] ) ) {
			self::prime( array( $video_id ) );
		}

		return self::$cache[ $video_id ] ?? array();
	}

	/**
	 * The page where this member can watch the video again, for My Videos on
	 * a site where no LMS supplies one: the first place that shows it which
	 * is published, has no password and the member may read. '' when none.
	 *
	 * Not entry()'s url, which is the edit screen for anyone who can edit.
	 *
	 * @param int $video_id Video ID.
	 * @param int $user_id  Member the list is for.
	 * @return string
	 */
	public static function page_for_viewer( int $video_id, int $user_id ): string {
		foreach ( self::for_video( $video_id ) as $place ) {
			$post = get_post( (int) ( $place['id'] ?? 0 ) );
			if ( ! $post || 'publish' !== $post->post_status || post_password_required( $post ) ) {
				continue;
			}
			if ( ! is_post_type_viewable( $post->post_type ) || ! user_can( $user_id, 'read_post', $post->ID ) ) {
				continue;
			}
			$url = (string) get_permalink( $post );
			if ( '' !== $url ) {
				return $url;
			}
		}
		return '';
	}

	/**
	 * One place, as the list shows it: a link the owner may follow.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $title   Post title.
	 * @param string $type    Post type.
	 * @return array{id:int,title:string,url:string,type:string}
	 */
	public static function entry( int $post_id, string $title, string $type ): array {
		$object = get_post_type_object( $type );

		return array(
			'id'    => $post_id,
			'title' => '' !== $title ? wp_strip_all_tags( $title ) : __( '(no title)', 'mediashield' ),
			'url'   => current_user_can( 'edit_post', $post_id ) ? (string) get_edit_post_link( $post_id, 'raw' ) : (string) get_permalink( $post_id ),
			'type'  => $object ? (string) $object->labels->singular_name : $type,
		);
	}

	/**
	 * Video IDs a post's content shows, by shortcode or block.
	 *
	 * @param string $content Post content.
	 * @return int[]
	 */
	private static function ids_in( string $content ): array {
		$ids = array();

		if ( preg_match_all( '/\[mediashield\s+[^\]]*\bid=["\']?(\d+)/', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}

		if ( preg_match_all( '/<!--\s*wp:mediashield\/video\s+\{[^}]*"videoId"\s*:\s*"?(\d+)/', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Forget answers. Test seam.
	 */
	public static function flush(): void {
		self::$cache = array();
	}
}
