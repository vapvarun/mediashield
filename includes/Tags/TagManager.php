<?php
/**
 * CRUD operations for ms_tags and ms_video_tags tables.
 *
 * @package MediaShield\Tags
 */

namespace MediaShield\Tags;

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
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}ms_tags WHERE slug = %s",
			$slug
		) );

		if ( $existing ) {
			return false;
		}

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
	 * Get a single tag by ID.
	 *
	 * @param int $tag_id Tag ID.
	 * @return object|null Tag row or null.
	 */
	public static function get( int $tag_id ): ?object {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ms_tags WHERE id = %d",
			$tag_id
		) );
	}

	/**
	 * Get a tag by slug.
	 *
	 * @param string $slug Tag slug.
	 * @return object|null Tag row or null.
	 */
	public static function get_by_slug( string $slug ): ?object {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ms_tags WHERE slug = %s",
			$slug
		) );
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
			$where = 'WHERE name LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		if ( ! empty( $args ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ms_tags {$where}",
				...$args
			) );
		} else {
			$total = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ms_tags"
			);
		}

		$offset = ( $page - 1 ) * $per_page;

		if ( ! empty( $args ) ) {
			$limit_args = array_merge( $args, array( $per_page, $offset ) );
			$items = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ms_tags {$where} ORDER BY name ASC LIMIT %d OFFSET %d",
				...$limit_args
			) );
		} else {
			$items = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ms_tags ORDER BY name ASC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			) );
		}

		return array( 'items' => $items ?: array(), 'total' => $total );
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
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ms_tags WHERE slug = %s AND id != %d",
				$update['slug'],
				$tag_id
			) );

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
		$wpdb->delete(
			"{$wpdb->prefix}ms_video_tags",
			array( 'tag_id' => $tag_id ),
			array( '%d' )
		);

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
		$result = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->prefix}ms_video_tags (video_id, tag_id, tagged_by, tagged_at)
			 VALUES (%d, %d, %d, %s)",
			$video_id,
			$tag_id,
			$user_id,
			current_time( 'mysql', true )
		) );

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

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.*, vt.tagged_by, vt.tagged_at
			 FROM {$wpdb->prefix}ms_tags t
			 INNER JOIN {$wpdb->prefix}ms_video_tags vt ON t.id = vt.tag_id
			 WHERE vt.video_id = %d
			 ORDER BY t.name ASC",
			$video_id
		) );

		return $results ?: array();
	}

	/**
	 * Get all video IDs that have a specific tag.
	 *
	 * @param int $tag_id Tag ID.
	 * @return array Array of video CPT post IDs.
	 */
	public static function get_videos_for_tag( int $tag_id ): array {
		global $wpdb;

		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT video_id FROM {$wpdb->prefix}ms_video_tags WHERE tag_id = %d",
			$tag_id
		) );

		return array_map( 'intval', $results ?: array() );
	}
}
