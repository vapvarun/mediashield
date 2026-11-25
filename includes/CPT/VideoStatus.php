<?php
/**
 * A video's status in the library: Ready, No source, Unavailable at the platform, Draft.
 *
 * @package MediaShield\CPT
 */

namespace MediaShield\CPT;

use MediaShield\Support\Platforms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Videos list and Dashboard status, stored so it is never worked out per row.
 *
 * Basecamp 10545260458: every video looked Ready, even one with no source or
 * one its platform removed. The status is stored when it can change and read
 * back with COUNT(*), so a library of thousands is counted in one query and
 * no platform is asked anything when the list loads:
 *
 * - No source: `_ms_no_source` = 1, written here whenever the video's source
 *   address, stream address or platform id is written (any screen, import or
 *   API) and on save: none of the three, or only a stored link MediaShield
 *   cannot play (Platforms::recognise()).
 * - Unavailable at the platform: `_ms_source_unavailable`, written by
 *   SourceStatus when a player reports a refusal the platform confirms.
 * - Draft: the post status (draft, pending, scheduled).
 * - Ready: published or private with neither of the above.
 *
 * "Needs attention" is No source or Unavailable, whatever the post status.
 *
 * @since 1.4.4
 */
class VideoStatus {

	/**
	 * Video meta: '1' while the video has nothing MediaShield can play.
	 */
	const NO_SOURCE = '_ms_no_source';

	/**
	 * Post statuses shown as Draft.
	 */
	const DRAFT_STATUSES = array( 'draft', 'pending', 'future' );

	/**
	 * Post statuses that can be Ready.
	 */
	const LIVE_STATUSES = array( 'publish', 'private' );

	/**
	 * Meta keys whose change can change "No source".
	 */
	const SOURCE_KEYS = array( '_ms_source_url', '_ms_stream_url', '_ms_platform_video_id' );

	/**
	 * Hook the writers.
	 */
	public static function register(): void {
		add_action( 'save_post_mediashield_video', array( __CLASS__, 'on_save' ), 30 );
		add_action( 'rest_after_insert_mediashield_video', array( __CLASS__, 'on_rest' ), 30 );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'on_meta' ), 10, 3 );
		}
		add_filter( 'mediashield_library_choices', array( __CLASS__, 'library_choices' ), 10, 2 );
	}

	/**
	 * Most videos one `mediashield_library_choices` page returns.
	 */
	const CHOICES_MAX = 100;

	/**
	 * The ready, published videos another plugin may offer in a chooser
	 * (Learnomy's lesson video select), as [ id => title ], one page at a
	 * time. Answers the public filter `mediashield_library_choices`.
	 *
	 * Ready means it has something to play: published, not "No source",
	 * not unavailable at its platform (query_args( 'ready' )). Not the
	 * Videos list (all states) or the block picker (REST, same rule).
	 *
	 * @since 1.4.4
	 *
	 * @param array $choices Choices from an earlier callback; kept first.
	 * @param array $args    { page: int (1), per_page: int (20, at most 100), search: string }.
	 * @return array<int, string> Video ID => title, newest first.
	 */
	public static function library_choices( $choices, $args = array() ): array {
		$args     = (array) $args;
		$per_page = max( 1, min( self::CHOICES_MAX, (int) ( $args['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$ready    = self::query_args( 'ready' );

		$query = new \WP_Query(
			array(
				'post_type'              => 'mediashield_video',
				'post_status'            => 'publish',
				'meta_query'             => $ready['meta_query'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- NOT EXISTS on two keys, one page.
				's'                      => sanitize_text_field( (string) ( $args['search'] ?? '' ) ),
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$own = array();
		foreach ( $query->posts as $post ) {
			$own[ (int) $post->ID ] = '' !== $post->post_title ? $post->post_title : sprintf( /* translators: %d: video ID. */ __( 'Video %d', 'mediashield' ), $post->ID );
		}
		return (array) $choices + $own;
	}

	/**
	 * After any save.
	 *
	 * @param int $post_id Video ID.
	 */
	public static function on_save( $post_id ): void {
		if ( ! wp_is_post_revision( (int) $post_id ) ) {
			self::refresh( (int) $post_id );
		}
	}

	/**
	 * After a REST create or update (meta is written after save_post there).
	 *
	 * @param \WP_Post $post Video.
	 */
	public static function on_rest( $post ): void {
		self::refresh( (int) $post->ID );
	}

	/**
	 * When a source or stream address is written, by any code.
	 *
	 * @param int|int[] $meta_id  Meta row ID(s).
	 * @param int       $post_id  Post ID.
	 * @param string    $meta_key Meta key.
	 */
	public static function on_meta( $meta_id, $post_id, $meta_key ): void {
		if ( in_array( $meta_key, self::SOURCE_KEYS, true ) && 'mediashield_video' === get_post_type( (int) $post_id ) ) {
			self::refresh( (int) $post_id );
		}
	}

	/**
	 * Does this video have nothing MediaShield can play?
	 *
	 * A stored link with a stored platform plays as it did: 1.4.3 saved an
	 * unrecognised link as a video file (`self`) and plays it in a <video>
	 * (for example https://media.example.com/watch?id=42). Platforms::recognise()
	 * only gates a new link, at save; here it judges a link saved without a
	 * platform.
	 *
	 * @param int $video_id Video ID.
	 * @return bool
	 */
	public static function lacks_source( int $video_id ): bool {
		$source = trim( (string) get_post_meta( $video_id, '_ms_source_url', true ) );
		$stream = trim( (string) get_post_meta( $video_id, '_ms_stream_url', true ) );
		// A platform id (or an uploaded file's name) is enough for the
		// player, as Renderer::render() reads it.
		$id       = trim( (string) get_post_meta( $video_id, '_ms_platform_video_id', true ) );
		$platform = trim( (string) get_post_meta( $video_id, '_ms_platform', true ) );

		if ( '' !== $stream || '' !== $id ) {
			return false;
		}
		if ( '' === $source ) {
			return true;
		}
		return '' === $platform && is_wp_error( Platforms::recognise( $source ) );
	}

	/**
	 * Take "No source" off videos the v14 backfill flagged that still play:
	 * a stored link with a stored platform (lacks_source()). One read and
	 * one delete, for sites that ran v14 before this rule (Migrator v17).
	 *
	 * @since 1.4.4
	 */
	public static function clear_played_links(): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off upgrade correction on indexed meta keys.
		$ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT n.post_id FROM {$wpdb->postmeta} n
					 INNER JOIN {$wpdb->postmeta} s ON s.post_id = n.post_id AND s.meta_key = '_ms_source_url' AND s.meta_value <> ''
					 INNER JOIN {$wpdb->postmeta} p ON p.post_id = n.post_id AND p.meta_key = '_ms_platform' AND p.meta_value <> ''
					 WHERE n.meta_key = %s",
					self::NO_SOURCE
				)
			)
		);
		if ( ! $ids ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ( " . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ' )', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders only.
				array_merge( array( self::NO_SOURCE ), $ids )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( $ids as $id ) {
			wp_cache_delete( $id, 'post_meta' );
		}
	}

	/**
	 * Store "No source" for one video. Local checks only, never the network.
	 *
	 * @param int $video_id Video ID.
	 */
	public static function refresh( int $video_id ): void {
		if ( 'mediashield_video' !== get_post_type( $video_id ) ) {
			return;
		}
		$lacks = self::lacks_source( $video_id );
		$has   = '' !== (string) get_post_meta( $video_id, self::NO_SOURCE, true );
		if ( $lacks && ! $has ) {
			update_post_meta( $video_id, self::NO_SOURCE, '1' );
		} elseif ( ! $lacks && $has ) {
			delete_post_meta( $video_id, self::NO_SOURCE );
		}
	}

	/**
	 * One video's status for the list: ready, no_source, unavailable or draft.
	 *
	 * @param int $video_id Video ID.
	 * @return string
	 */
	public static function of( int $video_id ): string {
		if ( '' !== (string) get_post_meta( $video_id, self::NO_SOURCE, true ) ) {
			return 'no_source';
		}
		if ( null !== SourceStatus::get( $video_id ) ) {
			return 'unavailable';
		}
		return in_array( get_post_status( $video_id ), self::DRAFT_STATUSES, true ) ? 'draft' : 'ready';
	}

	/**
	 * Library counts for the filter chips and the Dashboard, in one query.
	 *
	 * Served by the posts type_status_date index and the postmeta post_id
	 * index; nothing per row.
	 *
	 * @return array{all: int, ready: int, attention: int, draft: int, no_source: int, unavailable: int}
	 */
	public static function counts(): array {
		global $wpdb;

		$statuses = array_merge( self::LIVE_STATUSES, self::DRAFT_STATUSES );
		$in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$live     = implode( ',', array_fill( 0, count( self::LIVE_STATUSES ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One COUNT over core tables for the list chips; indexed. The IN lists are built from the arrays passed.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only placeholders are interpolated.
				"SELECT COUNT(*) AS all_videos,
				        SUM( ns.meta_id IS NOT NULL ) AS no_source,
				        SUM( ns.meta_id IS NULL AND un.meta_id IS NOT NULL ) AS unavailable,
				        SUM( ns.meta_id IS NULL AND un.meta_id IS NULL AND p.post_status IN ( {$live} ) ) AS ready
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} ns ON ns.post_id = p.ID AND ns.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} un ON un.post_id = p.ID AND un.meta_key = %s
				 WHERE p.post_type = 'mediashield_video' AND p.post_status IN ( {$in} )",
				array_merge( self::LIVE_STATUSES, array( self::NO_SOURCE, SourceStatus::META ), $statuses )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$all         = (int) ( $row->all_videos ?? 0 );
		$no_source   = (int) ( $row->no_source ?? 0 );
		$unavailable = (int) ( $row->unavailable ?? 0 );
		$ready       = (int) ( $row->ready ?? 0 );

		return array(
			'all'         => $all,
			'ready'       => $ready,
			'attention'   => $no_source + $unavailable,
			'draft'       => $all - $ready - $no_source - $unavailable,
			'no_source'   => $no_source,
			'unavailable' => $unavailable,
		);
	}

	/**
	 * The WP_Query args that show one chip's videos.
	 *
	 * @param string $state ready, attention or draft.
	 * @return array{post_status?: string[], meta_query?: array}
	 */
	public static function query_args( string $state ): array {
		$none = array(
			'relation' => 'AND',
			array(
				'key'     => self::NO_SOURCE,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => SourceStatus::META,
				'compare' => 'NOT EXISTS',
			),
		);

		if ( 'ready' === $state ) {
			return array(
				'post_status' => self::LIVE_STATUSES,
				'meta_query'  => array( $none ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			);
		}
		if ( 'draft' === $state ) {
			return array(
				'post_status' => self::DRAFT_STATUSES,
				'meta_query'  => array( $none ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			);
		}
		if ( 'attention' === $state ) {
			return array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'relation' => 'OR',
						array(
							'key'     => self::NO_SOURCE,
							'compare' => 'EXISTS',
						),
						array(
							'key'     => SourceStatus::META,
							'compare' => 'EXISTS',
						),
					),
				),
			);
		}
		return array();
	}

	/**
	 * Store "No source" for every existing video, once, on update to 1.4.4.
	 *
	 * Two queries for the whole library: the IDs with a source or stream
	 * address, then the links themselves, checked in PHP (regular
	 * expressions only, no network).
	 */
	public static function backfill(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off upgrade pass over core tables.
		$ids = array_map(
			'intval',
			(array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'mediashield_video' AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )" )
		);
		if ( ! $ids ) {
			return;
		}
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m
				 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'mediashield_video'
				 WHERE m.meta_key IN ( %s, %s, %s, '_ms_platform' ) AND m.meta_value <> ''",
				self::SOURCE_KEYS[0],
				self::SOURCE_KEYS[1],
				self::SOURCE_KEYS[2]
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// The same rule as lacks_source(): a stream or platform id plays; a
		// link plays when a platform is stored with it or recognise() knows it.
		$playable = array();
		$links    = array();
		$platform = array();
		foreach ( $rows as $row ) {
			$id = (int) $row->post_id;
			if ( '_ms_platform' === $row->meta_key ) {
				$platform[ $id ] = true;
			} elseif ( '_ms_source_url' === $row->meta_key ) {
				$links[ $id ] = (string) $row->meta_value;
			} else {
				$playable[ $id ] = true;
			}
		}
		foreach ( $links as $id => $link ) {
			if ( isset( $platform[ $id ] ) || ! is_wp_error( Platforms::recognise( $link ) ) ) {
				$playable[ $id ] = true;
			}
		}
		foreach ( $ids as $id ) {
			if ( ! isset( $playable[ $id ] ) ) {
				update_post_meta( $id, self::NO_SOURCE, '1' );
			}
		}
	}
}
