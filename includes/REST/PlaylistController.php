<?php
/**
 * REST API controller for playlist items (ms_playlist_items).
 *
 * Routes:
 *   GET    /mediashield/v1/playlists/<id>/items          — List videos in order
 *   POST   /mediashield/v1/playlists/<id>/items          — Add video to playlist
 *   DELETE /mediashield/v1/playlists/<id>/items/<item_id> — Remove video
 *   PUT    /mediashield/v1/playlists/<id>/items/reorder   — Batch reorder
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class PlaylistController extends WP_REST_Controller {

	/** @var string */
	protected $namespace = 'mediashield/v1';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		// GET + POST /playlists/<id>/items
		register_rest_route( $this->namespace, '/playlists/(?P<playlist_id>\d+)/items', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'read_permissions_check' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_item' ),
				'permission_callback' => array( $this, 'edit_permissions_check' ),
				'args'                => array(
					'video_id'   => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'sort_order' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			),
		) );

		// DELETE /playlists/<id>/items/<item_id>
		register_rest_route( $this->namespace, '/playlists/(?P<playlist_id>\d+)/items/(?P<item_id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'remove_item' ),
				'permission_callback' => array( $this, 'edit_permissions_check' ),
			),
		) );

		// PUT /playlists/<id>/items/reorder
		register_rest_route( $this->namespace, '/playlists/(?P<playlist_id>\d+)/items/reorder', array(
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'reorder_items' ),
				'permission_callback' => array( $this, 'edit_permissions_check' ),
				'args'                => array(
					'order' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'item_id'    => array( 'type' => 'integer' ),
								'sort_order' => array( 'type' => 'integer' ),
							),
						),
					),
				),
			),
		) );
	}

	/**
	 * Read permissions: any logged-in user.
	 */
	public function read_permissions_check( WP_REST_Request $request ): bool {
		return is_user_logged_in();
	}

	/**
	 * Edit permissions: editors+.
	 */
	public function edit_permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Validate that the playlist exists.
	 *
	 * @param int $playlist_id Playlist CPT post ID.
	 * @return WP_Error|true
	 */
	private function validate_playlist( int $playlist_id ) {
		$post = get_post( $playlist_id );
		if ( ! $post || 'mediashield_playlist' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Playlist not found.', 'mediashield' ), array( 'status' => 404 ) );
		}
		return true;
	}

	/**
	 * GET /playlists/<id>/items — list videos in playlist order.
	 */
	public function get_items( $request ): WP_REST_Response|WP_Error {
		$playlist_id = (int) $request['playlist_id'];
		$valid       = $this->validate_playlist( $playlist_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		global $wpdb;

		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT pi.id AS item_id, pi.video_id, pi.sort_order, pi.added_at,
					p.post_title AS video_title, p.post_status AS video_status,
					pm_platform.meta_value AS platform,
					pm_url.meta_value AS source_url,
					pm_duration.meta_value AS duration
			 FROM {$wpdb->prefix}ms_playlist_items pi
			 INNER JOIN {$wpdb->posts} p ON pi.video_id = p.ID
			 LEFT JOIN {$wpdb->postmeta} pm_platform ON pi.video_id = pm_platform.post_id AND pm_platform.meta_key = '_ms_platform'
			 LEFT JOIN {$wpdb->postmeta} pm_url ON pi.video_id = pm_url.post_id AND pm_url.meta_key = '_ms_source_url'
			 LEFT JOIN {$wpdb->postmeta} pm_duration ON pi.video_id = pm_duration.post_id AND pm_duration.meta_key = '_ms_duration'
			 WHERE pi.playlist_id = %d
			 ORDER BY pi.sort_order ASC, pi.id ASC",
			$playlist_id
		) );

		// Sanitize output.
		$result = array();
		foreach ( ( $items ?: array() ) as $item ) {
			$result[] = array(
				'item_id'    => (int) $item->item_id,
				'video_id'   => (int) $item->video_id,
				'sort_order' => (int) $item->sort_order,
				'added_at'   => $item->added_at,
				'title'      => sanitize_text_field( $item->video_title ),
				'status'     => $item->video_status,
				'platform'   => sanitize_text_field( $item->platform ?: 'self' ),
				'source_url' => esc_url( $item->source_url ?: '' ),
				'duration'   => (int) ( $item->duration ?: 0 ),
				'thumbnail'  => get_the_post_thumbnail_url( (int) $item->video_id, 'thumbnail' ) ?: '',
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * POST /playlists/<id>/items — add a video to the playlist.
	 */
	public function add_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$playlist_id = (int) $request['playlist_id'];
		$valid       = $this->validate_playlist( $playlist_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$video_id   = (int) $request->get_param( 'video_id' );
		$sort_order = (int) $request->get_param( 'sort_order' );

		// Validate video exists.
		$video = get_post( $video_id );
		if ( ! $video || 'mediashield_video' !== $video->post_type ) {
			return new WP_Error( 'not_found', __( 'Video not found.', 'mediashield' ), array( 'status' => 404 ) );
		}

		// Auto-assign sort_order if not provided.
		if ( $sort_order <= 0 ) {
			global $wpdb;
			$max = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT MAX(sort_order) FROM {$wpdb->prefix}ms_playlist_items WHERE playlist_id = %d",
				$playlist_id
			) );
			$sort_order = $max + 1;
		}

		global $wpdb;
		$inserted = $wpdb->insert(
			"{$wpdb->prefix}ms_playlist_items",
			array(
				'playlist_id' => $playlist_id,
				'video_id'    => $video_id,
				'sort_order'  => $sort_order,
				'added_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'insert_failed', __( 'Could not add video to playlist.', 'mediashield' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array(
			'item_id'    => (int) $wpdb->insert_id,
			'video_id'   => $video_id,
			'sort_order' => $sort_order,
		), 201 );
	}

	/**
	 * DELETE /playlists/<id>/items/<item_id> — remove video from playlist.
	 */
	public function remove_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$playlist_id = (int) $request['playlist_id'];
		$item_id     = (int) $request['item_id'];

		$valid = $this->validate_playlist( $playlist_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		global $wpdb;
		$deleted = $wpdb->delete(
			"{$wpdb->prefix}ms_playlist_items",
			array(
				'id'          => $item_id,
				'playlist_id' => $playlist_id,
			),
			array( '%d', '%d' )
		);

		if ( ! $deleted ) {
			return new WP_Error( 'not_found', __( 'Playlist item not found.', 'mediashield' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * PUT /playlists/<id>/items/reorder — batch update sort orders.
	 */
	public function reorder_items( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$playlist_id = (int) $request['playlist_id'];

		$valid = $this->validate_playlist( $playlist_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$order = $request->get_param( 'order' );
		if ( ! is_array( $order ) ) {
			return new WP_Error( 'invalid_order', __( 'Order must be an array.', 'mediashield' ), array( 'status' => 400 ) );
		}

		global $wpdb;

		foreach ( $order as $entry ) {
			$item_id    = (int) ( $entry['item_id'] ?? 0 );
			$sort_order = (int) ( $entry['sort_order'] ?? 0 );

			if ( $item_id > 0 ) {
				$wpdb->update(
					"{$wpdb->prefix}ms_playlist_items",
					array( 'sort_order' => $sort_order ),
					array(
						'id'          => $item_id,
						'playlist_id' => $playlist_id,
					),
					array( '%d' ),
					array( '%d', '%d' )
				);
			}
		}

		return rest_ensure_response( array( 'success' => true ) );
	}
}
