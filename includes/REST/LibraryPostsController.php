<?php
/**
 * The wp/v2 routes for the video and playlist library: editors only.
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;
use WP_REST_Posts_Controller;
use WP_REST_Request;

/**
 * The library post types are not public (no page of their own), but core's
 * posts controller lists published posts of any REST-enabled type to anyone,
 * so /wp/v2/mediashield-videos and /wp/v2/mediashield-playlists answered a
 * logged-out visitor with every title, slug and playback setting.
 *
 * Every reader of these routes is an editing screen (the blocks, the admin
 * screens, the wizard), so reading them needs edit_posts, like writing them
 * does. Viewers get a video or a playlist only where it is placed, through
 * the player routes, which check access per video (Basecamp 10545260546).
 */
class LibraryPostsController extends WP_REST_Posts_Controller {

	/**
	 * Listing needs edit_posts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return self::refusal();
		}
		return parent::get_items_permissions_check( $request );
	}

	/**
	 * Reading one needs edit_posts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return self::refusal();
		}
		return parent::get_item_permissions_check( $request );
	}

	/**
	 * 401 for a visitor, 403 for a member, as core answers.
	 *
	 * @return WP_Error
	 */
	private static function refusal(): WP_Error {
		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to do that.', 'mediashield' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
