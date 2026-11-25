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
	 * Pages that show a video through a playlist, by video ID. Not shown as
	 * "Used in" (the playlist is); read by page_for_viewer(), since a
	 * playlist has no public page of its own.
	 *
	 * @var array<int, array<int, array{id:int,title:string,url:string,type:string}>>
	 */
	private static $via_playlist = array();

	/**
	 * Most places listed per video.
	 */
	const MAX_PER_VIDEO = 5;

	/**
	 * Look up several videos with one query and remember the answers.
	 *
	 * One indexed read of the playlist items table for the playlists that
	 * hold these videos, then one scan of post_content (not indexed) for
	 * exactly these videos and those playlists (scan()). It runs once per
	 * Videos list page (20 videos), never per row, and has no cap on the
	 * posts it reads, so an old page is never missed. Pages that show one
	 * of the playlists become the video's pages via a playlist.
	 *
	 * @param int[] $video_ids Video IDs.
	 */
	public static function prime( array $video_ids ): void {
		$video_ids = array_values( array_diff( array_unique( array_map( 'intval', $video_ids ) ), array_keys( self::$cache ), array( 0 ) ) );

		if ( empty( $video_ids ) ) {
			return;
		}

		foreach ( $video_ids as $id ) {
			self::$cache[ $id ]        = array();
			self::$via_playlist[ $id ] = array();
		}

		global $wpdb;

		// Playlists that hold these videos: one indexed read of the playlist
		// table. A video added to a playlist is shown wherever the playlist
		// is placed (a playlist has no page of its own since 1.4.4), so the
		// playlist is a place, and the pages that show it are the video's
		// pages via a playlist.
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
				ORDER BY p.post_modified_gmt DESC",
				$video_ids
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$playlist_ids = array_values( array_unique( array_map( static fn( $row ) => (int) $row->ID, (array) $playlists ) ) );

		// One scan of post_content for exactly these videos and playlists,
		// with no cap: a video shown only on an old page must still say
		// where.
		$hits = self::scan( $video_ids, $playlist_ids, array( 'publish', 'future', 'draft', 'private' ) );

		$wanted            = array_flip( $video_ids );
		$pages_by_playlist = array();
		foreach ( $hits as $hit ) {
			foreach ( $hit['playlists'] as $playlist_id ) {
				$pages_by_playlist[ $playlist_id ][] = $hit;
			}
			foreach ( $hit['videos'] as $id ) {
				if ( count( self::$cache[ $id ] ) < self::MAX_PER_VIDEO ) {
					self::$cache[ $id ][] = self::entry( $hit['id'], $hit['title'], $hit['type'] );
				}
			}
		}

		foreach ( (array) $playlists as $row ) {
			$id = (int) $row->video_id;
			if ( ! isset( $wanted[ $id ] ) ) {
				continue;
			}
			if ( count( self::$cache[ $id ] ) < self::MAX_PER_VIDEO ) {
				self::$cache[ $id ][] = self::entry( (int) $row->ID, (string) $row->post_title, (string) $row->post_type );
			}
			foreach ( $pages_by_playlist[ (int) $row->ID ] ?? array() as $page ) {
				if ( count( self::$via_playlist[ $id ] ) < self::MAX_PER_VIDEO ) {
					self::$via_playlist[ $id ][] = self::entry( $page['id'], $page['title'], $page['type'] );
				}
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
		self::for_video( $video_id ); // Primes both lists.
		// Pages that show the video itself first, then pages that show a
		// playlist holding it. A playlist is never offered: it has no public
		// page, and first_readable() skips types that are not viewable.
		return self::first_readable( array_merge( self::$cache[ $video_id ] ?? array(), self::$via_playlist[ $video_id ] ?? array() ), $user_id );
	}

	/**
	 * The page that shows a playlist now that it has no page of its own:
	 * the first published place with the Playlist block or shortcode that a
	 * visitor may read, most recently edited first. '' when none.
	 *
	 * Used to send the 1.4.1-1.4.3 /playlist/<slug>/ address on to where
	 * the playlist lives today (PlaylistPostType::old_address_target()).
	 * Asked as a logged-out visitor, so the answer is the same for everyone.
	 *
	 * @since 1.4.4
	 *
	 * @param int $playlist_id Playlist ID.
	 * @return string
	 */
	public static function page_for_playlist( int $playlist_id ): string {
		// One scan for exactly this playlist, like prime(); this runs only on a
		// request for an old address.
		$places = array();
		foreach ( self::scan( array(), array( $playlist_id ), array( 'publish' ) ) as $hit ) {
			$places[] = array( 'id' => $hit['id'] );
		}

		return self::first_readable( $places, 0 );
	}

	/**
	 * The posts that show these videos or playlists, newest edit first.
	 *
	 * One table scan (post_content is not indexed) whose WHERE asks for
	 * exactly these ids, in the forms the shortcode and the block are saved
	 * in (id="N", id='N', id=N, "videoId":N, "playlistId":N), with no cap:
	 * a video shown only on an old page must still say where. One REGEXP
	 * per kind, not a LIKE per id and form: on 1,600 embedding posts 140
	 * LIKEs took 770 ms, the pattern a few ms. Each match is then read with
	 * the same parsers as before, so a near miss (another plugin's id="N")
	 * is dropped.
	 *
	 * @since 1.4.4
	 *
	 * @param int[]    $video_ids    Video IDs (at most a list page).
	 * @param int[]    $playlist_ids Playlist IDs.
	 * @param string[] $statuses     Post statuses to search.
	 * @return array<int, array{id:int, title:string, type:string, videos:int[], playlists:int[]}>
	 */
	private static function scan( array $video_ids, array $playlist_ids, array $statuses ): array {
		global $wpdb;

		$video_ids    = array_values( array_filter( array_unique( array_map( 'intval', $video_ids ) ) ) );
		$playlist_ids = array_values( array_filter( array_unique( array_map( 'intval', $playlist_ids ) ) ) );

		// One pattern per kind: a shortcode id or the block's key, then one of
		// the ids, then a non-digit, so 12 never matches 120. Plain ERE, read
		// the same by MySQL 5.7, MySQL 8 and MariaDB.
		$patterns = array();
		$kinds    = array(
			'videoId'    => $video_ids,
			'playlistId' => $playlist_ids,
		);
		foreach ( $kinds as $block_key => $ids ) {
			if ( $ids ) {
				$patterns[] = '(id=["\']?|"' . $block_key . '":"?)(' . implode( '|', $ids ) . ')([^0-9]|$)';
			}
		}
		if ( ! $patterns ) {
			return array();
		}

		$any      = implode( ' OR ', array_fill( 0, count( $patterns ), 'post_content REGEXP %s' ) );
		$in       = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$excluded = "'revision', 'mediashield_video', 'mediashield_playlist', 'nav_menu_item', 'attachment', 'wp_global_styles'";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders built above; every value is prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts}
				WHERE post_status IN ( {$in} )
				AND post_type NOT IN ( {$excluded} )
				AND post_content LIKE %s
				AND ( {$any} )
				ORDER BY post_modified_gmt DESC",
				array_merge( $statuses, array( '%' . $wpdb->esc_like( 'mediashield' ) . '%' ), $patterns )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$hits = array();
		foreach ( (array) $rows as $row ) {
			$content = (string) $row->post_content;
			$videos  = array_values( array_intersect( self::ids_in( $content ), $video_ids ) );
			$lists   = array_values( array_intersect( self::playlist_ids_in( $content ), $playlist_ids ) );
			if ( ! $videos && ! $lists ) {
				continue;
			}
			$hits[] = array(
				'id'        => (int) $row->ID,
				'title'     => (string) $row->post_title,
				'type'      => (string) $row->post_type,
				'videos'    => $videos,
				'playlists' => $lists,
			);
		}
		return $hits;
	}

	/**
	 * The first of these places that is published, has no password, is a
	 * viewable post type and the user may read: its address, or ''.
	 *
	 * @param array<int, array{id:int}> $places  Places, in order of preference.
	 * @param int                       $user_id User (0 = a logged-out visitor).
	 * @return string
	 */
	private static function first_readable( array $places, int $user_id ): string {
		foreach ( $places as $place ) {
			$post = get_post( (int) ( $place['id'] ?? 0 ) );
			if ( ! $post || 'publish' !== $post->post_status || post_password_required( $post ) ) {
				continue;
			}
			// A logged-out visitor holds no capabilities at all (not even
			// `read`), so for them published, no password and viewable is
			// the whole test.
			if ( ! is_post_type_viewable( $post->post_type ) || ( $user_id && ! user_can( $user_id, 'read_post', $post->ID ) ) ) {
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
	 * Playlist IDs a post's content shows, by shortcode or block.
	 *
	 * @param string $content Post content.
	 * @return int[]
	 */
	private static function playlist_ids_in( string $content ): array {
		$ids = array();

		if ( preg_match_all( '/\[mediashield_playlist\s+[^\]]*\bid=["\']?(\d+)/', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}

		if ( preg_match_all( '/<!--\s*wp:mediashield\/playlist\s+\{[^}]*"playlistId"\s*:\s*"?(\d+)/', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Forget answers. Test seam.
	 */
	public static function flush(): void {
		self::$cache        = array();
		self::$via_playlist = array();
	}
}
