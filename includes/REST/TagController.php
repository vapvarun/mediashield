<?php
/**
 * REST API controller for tags and video-tag assignments.
 *
 * Routes:
 *   GET    /mediashield/v1/tags            — List tags (paginated, searchable)
 *   POST   /mediashield/v1/tags            — Create tag
 *   GET    /mediashield/v1/tags/<id>       — Get single tag
 *   PATCH  /mediashield/v1/tags/<id>       — Update tag
 *   DELETE /mediashield/v1/tags/<id>       — Delete tag
 *   GET    /mediashield/v1/videos/<id>/tags       — Get tags for video
 *   POST   /mediashield/v1/videos/<id>/tags       — Assign tag to video
 *   DELETE /mediashield/v1/videos/<id>/tags/<tid> — Remove tag from video
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

use MediaShield\Tags\TagManager;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class TagController extends WP_REST_Controller {

	/** @var string */
	protected $namespace = 'mediashield/v1';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {

		// /tags collection.
		register_rest_route( $this->namespace, '/tags', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 50,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => array(
					'name'        => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			),
		) );

		// /tags/<id> single.
		register_rest_route( $this->namespace, '/tags/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			),
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => array(
					'name'        => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			),
		) );

		// /videos/<id>/tags — tags for a specific video.
		register_rest_route( $this->namespace, '/videos/(?P<video_id>\d+)/tags', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_video_tags' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'assign_video_tag' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => array(
					'tag_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			),
		) );

		// /videos/<id>/tags/<tid> — remove tag from video.
		register_rest_route( $this->namespace, '/videos/(?P<video_id>\d+)/tags/(?P<tag_id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'unassign_video_tag' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			),
		) );
	}

	/**
	 * Permissions: any logged-in user can read tags.
	 */
	public function get_items_permissions_check( $request ): bool {
		return is_user_logged_in();
	}

	/**
	 * Permissions: only editors+ can create/modify/delete tags.
	 */
	public function create_item_permissions_check( $request ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * GET /tags — list tags.
	 */
	public function get_items( $request ): WP_REST_Response {
		$result = TagManager::list(
			$request->get_param( 'per_page' ),
			$request->get_param( 'page' ),
			$request->get_param( 'search' )
		);

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', (int) ceil( $result['total'] / $request->get_param( 'per_page' ) ) );

		return $response;
	}

	/**
	 * POST /tags — create tag.
	 */
	public function create_item( $request ): WP_REST_Response|WP_Error {
		$tag_id = TagManager::create(
			$request->get_param( 'name' ),
			$request->get_param( 'description' ),
			get_current_user_id()
		);

		if ( false === $tag_id ) {
			return new WP_Error(
				'tag_exists',
				__( 'A tag with this name already exists.', 'mediashield' ),
				array( 'status' => 409 )
			);
		}

		$tag = TagManager::get( $tag_id );

		return new WP_REST_Response( $tag, 201 );
	}

	/**
	 * GET /tags/<id> — get single tag.
	 */
	public function get_item( $request ): WP_REST_Response|WP_Error {
		$tag = TagManager::get( (int) $request['id'] );

		if ( ! $tag ) {
			return new WP_Error( 'not_found', __( 'Tag not found.', 'mediashield' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $tag );
	}

	/**
	 * PATCH /tags/<id> — update tag.
	 */
	public function update_item( $request ): WP_REST_Response|WP_Error {
		$tag_id = (int) $request['id'];
		$tag    = TagManager::get( $tag_id );

		if ( ! $tag ) {
			return new WP_Error( 'not_found', __( 'Tag not found.', 'mediashield' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( $request->has_param( 'name' ) ) {
			$data['name'] = $request->get_param( 'name' );
		}
		if ( $request->has_param( 'description' ) ) {
			$data['description'] = $request->get_param( 'description' );
		}

		$updated = TagManager::update( $tag_id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'update_failed',
				__( 'Could not update tag. The slug may already be in use.', 'mediashield' ),
				array( 'status' => 409 )
			);
		}

		return rest_ensure_response( TagManager::get( $tag_id ) );
	}

	/**
	 * DELETE /tags/<id> — delete tag.
	 */
	public function delete_item( $request ): WP_REST_Response|WP_Error {
		$tag_id = (int) $request['id'];
		$tag    = TagManager::get( $tag_id );

		if ( ! $tag ) {
			return new WP_Error( 'not_found', __( 'Tag not found.', 'mediashield' ), array( 'status' => 404 ) );
		}

		TagManager::delete( $tag_id );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * GET /videos/<id>/tags — get tags for a video.
	 */
	public function get_video_tags( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$video_id = (int) $request['video_id'];

		if ( ! get_post( $video_id ) ) {
			return new WP_Error( 'not_found', __( 'Video not found.', 'mediashield' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( TagManager::get_for_video( $video_id ) );
	}

	/**
	 * POST /videos/<id>/tags — assign tag to video.
	 */
	public function assign_video_tag( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$video_id = (int) $request['video_id'];
		$tag_id   = (int) $request->get_param( 'tag_id' );

		if ( ! get_post( $video_id ) ) {
			return new WP_Error( 'not_found', __( 'Video not found.', 'mediashield' ), array( 'status' => 404 ) );
		}

		if ( ! TagManager::get( $tag_id ) ) {
			return new WP_Error( 'not_found', __( 'Tag not found.', 'mediashield' ), array( 'status' => 404 ) );
		}

		$assigned = TagManager::assign_to_video( $video_id, $tag_id, get_current_user_id() );

		if ( ! $assigned ) {
			return new WP_Error(
				'already_assigned',
				__( 'This tag is already assigned to this video.', 'mediashield' ),
				array( 'status' => 409 )
			);
		}

		return new WP_REST_Response( TagManager::get_for_video( $video_id ), 201 );
	}

	/**
	 * DELETE /videos/<id>/tags/<tid> — remove tag from video.
	 */
	public function unassign_video_tag( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$video_id = (int) $request['video_id'];
		$tag_id   = (int) $request['tag_id'];

		TagManager::unassign_from_video( $video_id, $tag_id );

		return new WP_REST_Response( null, 204 );
	}
}
