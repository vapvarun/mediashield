<?php
/**
 * CRUD operations for ms_tags and ms_video_tags tables.
 *
 * @package MediaShield\Tags
 */

namespace MediaShield\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TagManager
 *
 * CRUD operations for ms_tags and ms_video_tags tables.
 *
 * @since 1.0.0
 */
class TagManager {

	/**
	 * Create a new tag.
	 *
	 * @param string $name        Tag name.
	 * @param string $description Optional description.
	 * @param int    $created_by  User ID.
	 * @return int|false Tag ID on success, false on failure.
	 */
	public static function create( string $name, string $description = '', int $created_by = 0 ) {
		global $wpdb;

		$slug = sanitize_title( $name );

		// Check for duplicate slug.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ms_tags WHERE slug = %s",
				$slug
			)
		);

		if ( $existing ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$inserted = $wpdb->insert(
			"{$wpdb->prefix}ms_tags",
			array(
				'name'        => sanitize_text_field( $name ),
				'slug'        => $slug,
				'description' => sanitize_textarea_field( $description ),
				'created_by'  => $created_by,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Idempotent "get or create": return the existing tag ID for the given name
	 * (matched by slug) or create the tag and return the new ID.
	 *
	 * Called by the milestone tracker and any future automation that needs to
	 * promote a free-form tag string into a real `ms_tags` row.
	 *
	 * @param string $name        Tag display name. Must be non-empty.
	 * @param int    $created_by  User ID to record on creation.
	 * @return int Tag ID, or 0 if the input was invalid.
	 */
	public static function ensure( string $name, int $created_by = 0 ): int {
		$name = trim( $name );
		if ( '' === $name ) {
			return 0;
		}
		$existing = self::get_by_slug( sanitize_title( $name ) );
		if ( $existing ) {
			return (int) $existing->id;
		}
		$id = self::create( $name, '', $created_by );
		return false === $id ? 0 : (int) $id;
	}

	/**
	 * Get a single tag by ID.
	 *
	 * @param int $tag_id Tag ID.
	 * @return object|null Tag row or null.
	 */
	public static function get( int $tag_id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ms_tags WHERE id = %d",
				$tag_id
			)
		);
	}

	/**
	 * Get a tag by slug.
	 *
	 * @param string $slug Tag slug.
	 * @return object|null Tag row or null.
	 */
	public static function get_by_slug( string $slug ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ms_tags WHERE slug = %s",
				$slug
			)
		);
	}

	/**
	 * List all tags with optional pagination.
	 *
	 * @param int    $per_page Items per page.
	 * @param int    $page     Page number (1-based).
	 * @param string $search   Optional search term.
	 * @return array{items: array, total: int}
	 */
	public static function list( int $per_page = 50, int $page = 1, string $search = '' ): array {
		global $wpdb;

		$where = '';
		$args  = array();

		if ( '' !== $search ) {
			$where  = 'WHERE name LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Custom table with dynamic WHERE.
		if ( ! empty( $args ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}ms_tags {$where}",
					...$args
				)
			);
		} else {
			$total = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ms_tags"
			);
		}

		$offset = ( $page - 1 ) * $per_page;

		if ( ! empty( $args ) ) {
			$limit_args = array_merge( $args, array( $per_page, $offset ) );
			$items      = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ms_tags {$where} ORDER BY name ASC LIMIT %d OFFSET %d",
					...$limit_args
				)
			);
		} else {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ms_tags ORDER BY name ASC LIMIT %d OFFSET %d",
					$per_page,
					$offset
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		// How many videos carry each tag on this page: one grouped count, on idx_tag_id.
		if ( ! empty( $items ) ) {
			$ids = array_map( static fn( $tag ) => (int) $tag->id, $items );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom table; one %d per id.
			$counts = $wpdb->get_results( $wpdb->prepare( "SELECT tag_id, COUNT(*) AS n FROM {$wpdb->prefix}ms_video_tags WHERE tag_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') GROUP BY tag_id', ...$ids ), OBJECT_K );
			foreach ( $items as $tag ) {
				$tag->video_count = isset( $counts[ $tag->id ] ) ? (int) $counts[ $tag->id ]->n : 0;
			}
		}

		return array(
			'items' => ! empty( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Update a tag.
	 *
	 * @param int   $tag_id Tag ID.
	 * @param array $data   Fields to update (name, description).
	 * @return bool Success.
	 */
	public static function update( int $tag_id, array $data ): bool {
		global $wpdb;

		$update = array();
		$format = array();

		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
			$update['slug'] = sanitize_title( $data['name'] );
			$format[]       = '%s';
			$format[]       = '%s';

			// Check slug uniqueness (exclude self).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}ms_tags WHERE slug = %s AND id != %d",
					$update['slug'],
					$tag_id
				)
			);

			if ( $existing ) {
				return false;
			}
		}

		if ( isset( $data['description'] ) ) {
			$update['description'] = sanitize_textarea_field( $data['description'] );
			$format[]              = '%s';
		}

		if ( empty( $update ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		return (bool) $wpdb->update(
			"{$wpdb->prefix}ms_tags",
			$update,
			array( 'id' => $tag_id ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Delete a tag and all its video associations.
	 *
	 * @param int $tag_id Tag ID.
	 * @return bool Success.
	 */
	public static function delete( int $tag_id ): bool {
		global $wpdb;

		// Remove all video associations first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete(
			"{$wpdb->prefix}ms_video_tags",
			array( 'tag_id' => $tag_id ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		return (bool) $wpdb->delete(
			"{$wpdb->prefix}ms_tags",
			array( 'id' => $tag_id ),
			array( '%d' )
		);
	}

	/**
	 * Assign a tag to a video (CPT post ID).
	 *
	 * @param int $video_id Video CPT post ID.
	 * @param int $tag_id   Tag ID.
	 * @param int $user_id  User performing the action.
	 * @return bool Success (false if already assigned).
	 */
	public static function assign_to_video( int $video_id, int $tag_id, int $user_id = 0 ): bool {
		global $wpdb;

		// INSERT IGNORE to handle duplicates gracefully.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert with IGNORE.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}ms_video_tags (video_id, tag_id, tagged_by, tagged_at)
			 VALUES (%d, %d, %d, %s)",
				$video_id,
				$tag_id,
				$user_id,
				current_time( 'mysql', true )
			)
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Remove a tag from a video.
	 *
	 * @param int $video_id Video CPT post ID.
	 * @param int $tag_id   Tag ID.
	 * @return bool Success.
	 */
	public static function unassign_from_video( int $video_id, int $tag_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		return (bool) $wpdb->delete(
			"{$wpdb->prefix}ms_video_tags",
			array(
				'video_id' => $video_id,
				'tag_id'   => $tag_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Get all tags for a video.
	 *
	 * @param int $video_id Video CPT post ID.
	 * @return array Array of tag objects.
	 */
	public static function get_for_video( int $video_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*, vt.tagged_by, vt.tagged_at
			 FROM {$wpdb->prefix}ms_tags t
			 INNER JOIN {$wpdb->prefix}ms_video_tags vt ON t.id = vt.tag_id
			 WHERE vt.video_id = %d
			 ORDER BY t.name ASC",
				$video_id
			)
		);

		return ! empty( $results ) ? $results : array();
	}

	/**
	 * Get all video IDs that have a specific tag.
	 *
	 * @param int $tag_id Tag ID.
	 * @return array Array of video CPT post IDs.
	 */
	public static function get_videos_for_tag( int $tag_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT video_id FROM {$wpdb->prefix}ms_video_tags WHERE tag_id = %d",
				$tag_id
			)
		);

		return array_map( 'intval', ! empty( $results ) ? $results : array() );
	}

	/**
	 * Option that holds the job's cursor (last usermeta ID read) while the
	 * v19 tag separation is pending; deleted when it is done.
	 */
	const MEMBER_TAGS_PENDING = 'ms_member_tags_pending';

	/**
	 * Earn records read per run of the job.
	 */
	const MEMBER_TAGS_BATCH = 1000;

	/**
	 * Queue the separation job while it is pending (init).
	 */
	public static function ensure_member_tags_job(): void {
		if ( false !== get_option( self::MEMBER_TAGS_PENDING ) ) {
			\MediaShield\Cron\Cleanup::schedule_single( 'ms_separate_member_tags', time() );
		}
	}

	/**
	 * Take member milestone tags off the videos they were linked to.
	 *
	 * Before 1.4.4 reaching a milestone also linked its tag to the video, as
	 * if the owner had tagged it, and created the tag in the owner's list.
	 * Removed: a link the member's own milestone made (tagged by that member,
	 * the same video and tag as their earn record), then, once every link is
	 * gone, a tag a member created which no video carries. A tag the owner
	 * created or put on a video stays. Earn records in user meta are kept.
	 *
	 * Background job (Migrator v19 queues it; `ms_separate_member_tags`): one
	 * batch of earn records above the cursor per run, rescheduled until none
	 * are left. Timed inline at 200k records it took 3 s on MySQL 8 and 27 s
	 * on MariaDB 10.6, too long for an update.
	 *
	 * @since 1.4.4
	 */
	public static function separate_member_tags(): void {
		$after = get_option( self::MEMBER_TAGS_PENDING );
		if ( false === $after ) {
			return;
		}

		global $wpdb;
		$links = "{$wpdb->prefix}ms_video_tags";
		$tags  = "{$wpdb->prefix}ms_tags";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off upgrade walk on the primary key.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT umeta_id, user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_ms_video_tags' AND umeta_id > %d ORDER BY umeta_id LIMIT %d", (int) $after, self::MEMBER_TAGS_BATCH ) );
		// A milestone wrote its link and its earn record in one request (1.4.3
		// MilestoneTracker), so only a link stamped within seconds of the earn
		// time is the milestone's. An owner's own tag on the same video, made
		// before or after, keeps its own time and stays.
		$earned = array(); // "video,tag,member" => earned_at.
		foreach ( $rows as $row ) {
			foreach ( (array) maybe_unserialize( $row->meta_value ) as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['tag_id'] ) && ! empty( $entry['video_id'] ) && ! empty( $entry['earned_at'] ) ) {
					$earned[ (int) $entry['video_id'] . ',' . (int) $entry['tag_id'] . ',' . (int) $row->user_id ] = (string) $entry['earned_at'];
				}
			}
		}
		if ( $earned ) {
			$tuples = array_map( static fn( $key ) => array_map( 'intval', explode( ',', $key ) ), array_keys( $earned ) );
			$videos = array_values( array_unique( array_column( $tuples, 0 ) ) );
			// The video_id list lets MariaDB use the unique key; the tuples alone scan the table.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off background job.
			$found = (array) $wpdb->get_results(
				$wpdb->prepare(
					'SELECT video_id, tag_id, tagged_by, tagged_at FROM %i WHERE video_id IN (' . implode( ',', array_fill( 0, count( $videos ), '%d' ) ) . ') AND (video_id, tag_id, tagged_by) IN (' . implode( ',', array_fill( 0, count( $tuples ), '(%d,%d,%d)' ) ) . ')',
					$links,
					...$videos,
					...array_merge( ...$tuples )
				)
			);
			$gone = array();
			foreach ( $found as $link ) {
				if ( self::same_request( (string) $link->tagged_at, $earned[ "{$link->video_id},{$link->tag_id},{$link->tagged_by}" ] ?? '' ) ) {
					$gone[] = array( (int) $link->video_id, (int) $link->tag_id );
				}
			}
			if ( $gone ) {
				$wpdb->query(
					$wpdb->prepare(
						'DELETE FROM %i WHERE video_id IN (' . implode( ',', array_fill( 0, count( $videos ), '%d' ) ) . ') AND (video_id, tag_id) IN (' . implode( ',', array_fill( 0, count( $gone ), '(%d,%d)' ) ) . ')',
						$links,
						...$videos,
						...array_merge( ...$gone )
					)
				);
			}
			// phpcs:enable
		}

		if ( count( $rows ) === self::MEMBER_TAGS_BATCH ) {
			update_option( self::MEMBER_TAGS_PENDING, (string) (int) end( $rows )->umeta_id, false );
			\MediaShield\Cron\Cleanup::schedule_single( 'ms_separate_member_tags', time() );
			return;
		}

		// Every link is gone now: a tag on no video is a milestone's when its
		// creator earned it in the request that created it. A tag the owner
		// made on the Tags screen, then earned while testing, stays. Checked
		// in PHP, 1000 tags a query: a correlated LIKE over usermeta ran for
		// minutes on MySQL 8.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off background job.
		$orphans = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT t.id, t.created_by, t.created_at FROM %i t LEFT JOIN %i l ON l.tag_id = t.id WHERE l.tag_id IS NULL AND t.created_by > 0', $tags, $links ) );
		foreach ( array_chunk( $orphans, 1000 ) as $chunk ) {
			$times = array();
			$users = array_values( array_unique( array_map( 'intval', wp_list_pluck( $chunk, 'created_by' ) ) ) );
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT user_id, meta_value FROM %i WHERE meta_key = %s AND user_id IN (' . implode( ',', array_fill( 0, count( $users ), '%d' ) ) . ')', $wpdb->usermeta, '_ms_video_tags', ...$users ) ) as $row ) {
				foreach ( (array) maybe_unserialize( $row->meta_value ) as $entry ) {
					if ( is_array( $entry ) && ! empty( $entry['tag_id'] ) && ! empty( $entry['earned_at'] ) ) {
						$times[ (int) $row->user_id . ':' . (int) $entry['tag_id'] ][] = (string) $entry['earned_at'];
					}
				}
			}
			$gone = array();
			foreach ( $chunk as $tag ) {
				foreach ( $times[ (int) $tag->created_by . ':' . (int) $tag->id ] ?? array() as $at ) {
					if ( self::same_request( (string) $tag->created_at, $at ) ) {
						$gone[] = (int) $tag->id;
						break;
					}
				}
			}
			if ( $gone ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $gone ), '%d' ) ) . ')', $tags, ...$gone ) );
			}
		}
		// phpcs:enable
		delete_option( self::MEMBER_TAGS_PENDING );
	}

	/**
	 * Were two UTC times written by the same request (within 5 seconds)?
	 *
	 * @param string $a MySQL datetime.
	 * @param string $b MySQL datetime.
	 * @return bool
	 */
	private static function same_request( string $a, string $b ): bool {
		$a = strtotime( $a . ' UTC' );
		$b = strtotime( $b . ' UTC' );
		return $a && $b && abs( $a - $b ) <= 5;
	}
}
