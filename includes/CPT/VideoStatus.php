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
	 * @param int $video_id Video ID.
	 * @return bool
	 */
	public static function lacks_source( int $video_id ): bool {
		$source = trim( (string) get_post_meta( $video_id, '_ms_source_url', true ) );
		$stream = trim( (string) get_post_meta( $video_id, '_ms_stream_url', true ) );
		// A platform id (or an uploaded file's name) is enough for the
		// player, as Renderer::render() reads it.
		$id = trim( (string) get_post_meta( $video_id, '_ms_platform_video_id', true ) );

		return '' === $stream && '' === $id && ( '' === $source || is_wp_error( Platforms::recognise( $source ) ) );
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
				 WHERE m.meta_key IN ( %s, %s, %s ) AND m.meta_value <> ''",
				self::SOURCE_KEYS[0],
				self::SOURCE_KEYS[1],
				self::SOURCE_KEYS[2]
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$playable = array();
		foreach ( $rows as $row ) {
			if ( '_ms_source_url' !== $row->meta_key || ! is_wp_error( Platforms::recognise( (string) $row->meta_value ) ) ) {
				$playable[ (int) $row->post_id ] = true;
			}
		}
		foreach ( $ids as $id ) {
			if ( ! isset( $playable[ $id ] ) ) {
				update_post_meta( $id, self::NO_SOURCE, '1' );
			}
		}
	}
}
