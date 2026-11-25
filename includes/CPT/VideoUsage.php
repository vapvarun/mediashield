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
	 * The places come from the usage index (USED_IN rows written when a
	 * post is saved) in one read for the whole batch (found()), never per
	 * row and never a content scan once the index is built. Pages that show
	 * one of the playlists become the video's pages via a playlist.
	 *
	 * @param int[] $video_ids Video IDs.
	 */
	public static function prime( array $video_ids ): void {
		$video_ids = array_values( array_diff( array_unique( array_map( 'intval', $video_ids ) ), array_keys( self::$cache ), array( 0 ) ) );

		if ( empty( $video_ids ) ) {
			return;
		}

		$wanted = array_flip( $video_ids );
		foreach ( self::found( $video_ids ) as $id => $found ) {
			self::$cache[ $id ]        = array();
			self::$via_playlist[ $id ] = array();
			foreach ( $found['places'] as $place ) {
				self::$cache[ $id ][] = self::entry( $place[0], $place[1], $place[2] );
			}
			foreach ( $found['via'] as $page ) {
				self::$via_playlist[ $id ][] = self::entry( $page[0], $page[1], $page[2] );
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
	 * Video and playlist meta, one row per post that shows it (the index).
	 */
	const USED_IN = '_ms_used_in';

	/**
	 * Meta on a post that shows MediaShield: what it shows, as
	 * { v: video IDs, p: playlist IDs }. What the index was last written from.
	 */
	const EMBEDS = '_ms_embeds';

	/**
	 * Option: the index of existing content is still being built; its value
	 * is the last post ID done. Reads scan post content until it is gone.
	 */
	const INDEX_PENDING = 'ms_usage_index_pending';

	/**
	 * Posts indexed per background run.
	 */
	const INDEX_BATCH = 100;

	/**
	 * Most index rows read per video or playlist. More than the five a list
	 * shows: My Videos looks for the first one the member may read, which
	 * can be past drafts and private posts.
	 * shortcut: a fixed ceiling, not paging; a video placed on more than 50
	 * newer posts the member cannot read links nowhere in My Videos.
	 */
	const PER_TARGET = 50;

	/**
	 * Statuses whose embeds count as places.
	 */
	const PLACE_STATUSES = array( 'publish', 'future', 'draft', 'private' );

	/**
	 * Post types that are never a place.
	 */
	const NOT_PLACES = array( 'revision', 'mediashield_video', 'mediashield_playlist', 'nav_menu_item', 'attachment', 'wp_global_styles' );

	/**
	 * Where each video is placed, as [ post ID, title, post type ] lists:
	 * `places` (posts and playlists that show it) and `via` (pages that show
	 * a playlist holding it). One read of the playlist items table, then one
	 * read of the index (or, while it is being built, one content scan).
	 *
	 * Raw rows, not entry(): an entry's link depends on who is looking.
	 *
	 * @param int[] $video_ids Video IDs.
	 * @return array<int, array{places: array<int, array{0:int,1:string,2:string}>, via: array<int, array{0:int,1:string,2:string}>}>
	 */
	private static function found( array $video_ids ): array {
		$out = array();
		foreach ( $video_ids as $id ) {
			$out[ $id ] = array(
				'places' => array(),
				'via'    => array(),
			);
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

		$hits = self::places( $video_ids, $playlist_ids, self::PLACE_STATUSES );

		$pages_by_playlist = array();
		foreach ( $hits as $hit ) {
			foreach ( $hit['playlists'] as $playlist_id ) {
				$pages_by_playlist[ $playlist_id ][] = $hit;
			}
			foreach ( $hit['videos'] as $id ) {
				if ( isset( $out[ $id ] ) && count( $out[ $id ]['places'] ) < self::MAX_PER_VIDEO ) {
					$out[ $id ]['places'][] = array( $hit['id'], $hit['title'], $hit['type'] );
				}
			}
		}

		foreach ( (array) $playlists as $row ) {
			$id = (int) $row->video_id;
			if ( ! isset( $out[ $id ] ) ) {
				continue;
			}
			if ( count( $out[ $id ]['places'] ) < self::MAX_PER_VIDEO ) {
				$out[ $id ]['places'][] = array( (int) $row->ID, (string) $row->post_title, (string) $row->post_type );
			}
			foreach ( $pages_by_playlist[ (int) $row->ID ] ?? array() as $page ) {
				if ( count( $out[ $id ]['via'] ) < self::MAX_PER_VIDEO ) {
					$out[ $id ]['via'][] = array( $page['id'], $page['title'], $page['type'] );
				}
			}
		}

		return $out;
	}

	/**
	 * The posts that show these videos or playlists, newest edit first: from
	 * the index, or from a content scan while the index is being built.
	 *
	 * @param int[]    $video_ids    Video IDs.
	 * @param int[]    $playlist_ids Playlist IDs.
	 * @param string[] $statuses     Post statuses that count.
	 * @return array<int, array{id:int, title:string, type:string, videos:int[], playlists:int[]}>
	 */
	private static function places( array $video_ids, array $playlist_ids, array $statuses ): array {
		if ( false !== get_option( self::INDEX_PENDING ) ) {
			return self::scan( $video_ids, $playlist_ids, $statuses );
		}

		$video_ids    = array_values( array_filter( array_map( 'intval', $video_ids ) ) );
		$playlist_ids = array_values( array_filter( array_map( 'intval', $playlist_ids ) ) );
		$targets      = array_merge( $video_ids, $playlist_ids );
		if ( ! $targets ) {
			return array();
		}

		global $wpdb;
		$in_statuses = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$not_places  = implode( ', ', array_fill( 0, count( self::NOT_PLACES ), '%s' ) );
		// One part per video or playlist, each with its own limit, so a video
		// shown on thousands of lessons reads PER_TARGET rows, not all of
		// them, and cannot crowd out another video's places.
		$part  = "( SELECT m.post_id AS target, p.ID, p.post_title, p.post_type, p.post_modified_gmt
			FROM {$wpdb->postmeta} m
			INNER JOIN {$wpdb->posts} p ON p.ID = CAST( m.meta_value AS UNSIGNED )
			WHERE m.meta_key = %s AND m.post_id = %d
			AND p.post_status IN ( {$in_statuses} )
			AND p.post_type NOT IN ( {$not_places} )
			ORDER BY p.post_modified_gmt DESC LIMIT %d )";
		$parts = array();
		$args  = array();
		foreach ( $targets as $target ) {
			$parts[] = $part;
			$args    = array_merge( $args, array( self::USED_IN, $target ), $statuses, self::NOT_PLACES, array( self::PER_TARGET ) );
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Index read on postmeta.post_id and the posts primary key; every value is a placeholder.
		$rows = $wpdb->get_results( $wpdb->prepare( implode( ' UNION ALL ', $parts ) . ' ORDER BY post_modified_gmt DESC', $args ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// A row counts only while the post that shows the video still says
		// so (EMBEDS): a copied video (Duplicate Post), an import or a
		// search-replace can bring USED_IN rows nothing wrote for it.
		update_meta_cache( 'post', array_unique( array_map( static fn( $row ) => (int) $row->ID, (array) $rows ) ) );

		$is_video = array_flip( $video_ids );
		$hits     = array();
		foreach ( (array) $rows as $row ) {
			$id     = (int) $row->ID;
			$target = (int) $row->target;
			$kind   = isset( $is_video[ $target ] ) ? 'videos' : 'playlists';
			$shows  = get_post_meta( $id, self::EMBEDS, true );
			if ( ! is_array( $shows ) || ! in_array( $target, array_map( 'intval', (array) ( $shows[ 'videos' === $kind ? 'v' : 'p' ] ?? array() ) ), true ) ) {
				continue;
			}
			if ( ! isset( $hits[ $id ] ) ) {
				$hits[ $id ] = array(
					'id'        => $id,
					'title'     => (string) $row->post_title,
					'type'      => (string) $row->post_type,
					'videos'    => array(),
					'playlists' => array(),
				);
			}
			$hits[ $id ][ $kind ][] = $target;
		}
		return array_values( $hits );
	}

	/**
	 * Keep the index current, and build it for existing content.
	 *
	 * @since 1.4.4
	 */
	public static function register(): void {
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 20, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_trash' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
		add_action( 'ms_index_usage', array( __CLASS__, 'build_index' ) );
		add_action( 'init', array( __CLASS__, 'ensure_index_job' ), 20 );
		// A copy made by Yoast Duplicate Post starts with no index rows: its
		// own save writes them.
		add_filter( 'duplicate_post_excludelist_filter', array( __CLASS__, 'skip_on_copy' ) );
	}

	/**
	 * Duplicate Post: do not copy the index meta.
	 *
	 * @param array $keys Meta keys Duplicate Post skips.
	 * @return array
	 */
	public static function skip_on_copy( $keys ): array {
		return array_merge( (array) $keys, array( self::USED_IN, self::EMBEDS ) );
	}

	/**
	 * A post saved (any status change included): index what it shows now.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function on_save( $post_id, $post ): void {
		if ( $post instanceof \WP_Post ) {
			self::index( $post );
		}
	}

	/**
	 * A post trashed: it is no longer a place.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_trash( $post_id ): void {
		self::write( (int) $post_id, array() );
	}

	/**
	 * A post about to be deleted: take it out of the index.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete( $post_id ): void {
		self::write( (int) $post_id, array() );
	}

	/**
	 * Index one post from its content.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function index( \WP_Post $post ): void {
		if ( in_array( $post->post_type, self::NOT_PLACES, true ) ) {
			return;
		}
		$content = (string) $post->post_content;
		$shows   = array();
		if ( in_array( $post->post_status, self::PLACE_STATUSES, true ) && false !== stripos( $content, 'mediashield' ) ) {
			$shows = array(
				'v' => self::ids_in( $content ),
				'p' => self::playlist_ids_in( $content ),
			);
		}
		self::write( (int) $post->ID, $shows );
	}

	/**
	 * Store what a post shows and move the difference onto the videos and
	 * playlists (USED_IN rows).
	 *
	 * @param int                     $post_id Post ID.
	 * @param array{v?:int[],p?:int[]} $shows   What it shows now ([] for nothing).
	 */
	private static function write( int $post_id, array $shows ): void {
		if ( $post_id <= 0 ) {
			return;
		}
		$old   = get_post_meta( $post_id, self::EMBEDS, true );
		$old   = is_array( $old ) ? $old : array();
		$types = array(
			'v' => 'mediashield_video',
			'p' => 'mediashield_playlist',
		);
		$now   = array();
		foreach ( $types as $kind => $type ) {
			$was = array_map( 'intval', (array) ( $old[ $kind ] ?? array() ) );
			$is  = array_values( array_filter( array_map( 'intval', (array) ( $shows[ $kind ] ?? array() ) ), static fn( $id ) => $id > 0 && get_post_type( $id ) === $type ) );
			foreach ( array_diff( $was, $is ) as $target ) {
				delete_post_meta( $target, self::USED_IN, (string) $post_id );
			}
			// Every target it shows has its row, not only new ones: an import
			// can bring EMBEDS without the rows.
			foreach ( $is as $target ) {
				if ( ! in_array( (string) $post_id, (array) get_post_meta( $target, self::USED_IN ), true ) ) {
					add_post_meta( $target, self::USED_IN, (string) $post_id );
				}
			}
			$now[ $kind ] = $is;
		}

		if ( $now['v'] || $now['p'] ) {
			update_post_meta( $post_id, self::EMBEDS, $now );
		} elseif ( $old ) {
			delete_post_meta( $post_id, self::EMBEDS );
		}
		self::flush();
	}

	/**
	 * Queue the next index run while the index of existing content is
	 * being built (init).
	 *
	 * @since 1.4.4
	 */
	public static function ensure_index_job(): void {
		if ( false !== get_option( self::INDEX_PENDING ) ) {
			\MediaShield\Cron\Cleanup::schedule_single( 'ms_index_usage', time() + MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Index existing content, 100 posts that name MediaShield a run, by post
	 * ID above the cursor (`ms_index_usage`, queued by Migrator v18). The
	 * last run deletes INDEX_PENDING, and reads switch to the index.
	 *
	 * @since 1.4.4
	 */
	public static function build_index(): void {
		$cursor = get_option( self::INDEX_PENDING );
		if ( false === $cursor ) {
			return;
		}

		global $wpdb;
		$not_places = implode( ', ', array_fill( 0, count( self::NOT_PLACES ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One-off walk on the primary key, one batch.
		$ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE ID > %d AND post_type NOT IN ( {$not_places} ) AND post_content LIKE %s
					ORDER BY ID ASC LIMIT %d",
					array_merge( array( (int) $cursor ), self::NOT_PLACES, array( '%' . $wpdb->esc_like( 'mediashield' ) . '%', self::INDEX_BATCH ) )
				)
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		_prime_post_caches( $ids, false, false );
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post ) {
				self::index( $post );
			}
		}

		if ( count( $ids ) < self::INDEX_BATCH ) {
			delete_option( self::INDEX_PENDING );
			return;
		}
		update_option( self::INDEX_PENDING, (string) end( $ids ), false );
		\MediaShield\Cron\Cleanup::schedule_single( 'ms_index_usage', time() + MINUTE_IN_SECONDS );
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
		// From the index (a content scan while it is being built).
		$ids = wp_list_pluck( self::places( array(), array( $playlist_id ), array( 'publish' ) ), 'id' );

		return self::first_readable( array_map( static fn( $id ) => array( 'id' => (int) $id ), $ids ), 0 );
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
		$excluded = "'" . implode( "', '", self::NOT_PLACES ) . "'";

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
