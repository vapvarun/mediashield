<?php
/**
 * What the Video block shows about a video, and whether a pasted link is one already.
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\CPT\Thumbnail;
use MediaShield\CPT\VideoUsage;
use MediaShield\Player\Protection;
use MediaShield\Support\Platforms;
use WP_Error;
use WP_Query;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Two editor reads for the Video block (Basecamp 10545260523):
 *
 * - GET /videos/match?url= : the library video a pasted link already is, so
 *   the block offers "Use this video" instead of adding a second copy.
 * - GET /videos/{id}/summary : title, platform, length, protection and where
 *   else the video is shown, so the block and its sidebar name the video
 *   instead of showing an id.
 */
class VideoSummaryController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'mediashield/v1';

	/**
	 * Post statuses a library video can be found in (not the trash).
	 */
	const FINDABLE = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/videos/match',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'match' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'url' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/videos/(?P<id>\d+)/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'summary_response' ),
				'permission_callback' => static function ( WP_REST_Request $request ) {
					return current_user_can( 'edit_posts' ) && current_user_can( 'read_post', (int) $request['id'] );
				},
				'args'                => array(
					// The post being edited, left out of "used on N other pages".
					'post_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * GET /videos/match - is this link already in the library?
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error The match (or null) with the link's platform, or the recogniser's refusal.
	 */
	public function match( WP_REST_Request $request ) {
		$url  = trim( (string) $request->get_param( 'url' ) );
		$link = Platforms::recognise( $url );
		if ( is_wp_error( $link ) ) {
			return $link;
		}

		$id = self::find( $link['platform'], $link['video_id'], $url );

		return new WP_REST_Response(
			array(
				'platform'       => $link['platform'],
				'platform_label' => Platforms::label( $link['platform'] ),
				'match'          => $id ? self::summary( $id, 0 ) : null,
			),
			200
		);
	}

	/**
	 * GET /videos/{id}/summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function summary_response( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( 'mediashield_video' !== get_post_type( $id ) ) {
			return new WP_Error( 'not_found', __( 'Video not found.', 'mediashield' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( self::summary( $id, (int) $request->get_param( 'post_id' ) ), 200 );
	}

	/**
	 * The library video a link already is: same platform and platform id,
	 * or for a video file the same address. Oldest first, so the video people
	 * have been using wins over a later copy.
	 *
	 * @param string $platform Platform from Platforms::recognise().
	 * @param string $video_id Platform video id ('' for a video file).
	 * @param string $url      The link as pasted.
	 * @return int Video ID, 0 when the link is new.
	 */
	public static function find( string $platform, string $video_id, string $url ): int {
		$meta_query = '' !== $video_id
			? array(
				'relation' => 'AND',
				array(
					'key'   => '_ms_platform',
					'value' => $platform,
				),
				array(
					'key'   => '_ms_platform_video_id',
					'value' => $video_id,
				),
			)
			: array(
				array(
					'key'   => '_ms_source_url',
					'value' => esc_url_raw( $url ),
				),
			);

		$query = new WP_Query(
			array(
				'post_type'              => 'mediashield_video',
				'post_status'            => self::FINDABLE,
				'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Keyed on the indexed meta_key; one lookup per paste.
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $query->posts as $id ) {
			if ( current_user_can( 'read_post', (int) $id ) ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/**
	 * What the block and its sidebar show about a video.
	 *
	 * @param int $video_id Video ID.
	 * @param int $post_id  Post being edited, left out of used_in.
	 * @return array<string, mixed>
	 */
	public static function summary( int $video_id, int $post_id ): array {
		$platform = (string) get_post_meta( $video_id, '_ms_platform', true );
		$platform = '' !== $platform ? $platform : Platforms::SELF_HOSTED;
		$level    = Protection::resolve_level( $video_id );
		$levels   = Protection::levels();
		$own      = (string) get_post_meta( $video_id, '_ms_protection_level', true );

		$places = array_values(
			array_filter(
				VideoUsage::for_video( $video_id ),
				static fn( $place ) => (int) $place['id'] !== $post_id
			)
		);

		$title = get_the_title( $video_id );

		// No poster of the owner's: a YouTube video still has YouTube's
		// public thumbnail, as the block showed before 1.4.4. Never derived
		// for other platforms: a self-hosted id is the private file name.
		$thumbnail = Thumbnail::poster_url( $video_id, 'large', true );
		$youtube   = (string) get_post_meta( $video_id, '_ms_platform_video_id', true );
		if ( '' === $thumbnail && 'youtube' === $platform && preg_match( '/^[A-Za-z0-9_-]{11}$/', $youtube ) ) {
			$thumbnail = 'https://i.ytimg.com/vi/' . $youtube . '/hqdefault.jpg';
		}

		return array(
			'id'               => $video_id,
			'title'            => '' !== $title ? wp_strip_all_tags( $title ) : __( '(no title)', 'mediashield' ),
			'status'           => (string) get_post_status( $video_id ),
			'platform'         => $platform,
			'platform_label'   => Platforms::label( $platform ),
			'duration'         => (int) get_post_meta( $video_id, '_ms_duration', true ),
			'thumbnail'        => $thumbnail,
			'protection'       => $level,
			'protection_label' => $levels[ $level ]['label'] ?? $level,
			'follows_default'  => '' === $own,
			'edit_url'         => current_user_can( 'edit_post', $video_id ) ? (string) get_edit_post_link( $video_id, 'raw' ) : '',
			'used_in'          => $places,
			'used_in_more'     => count( VideoUsage::for_video( $video_id ) ) >= VideoUsage::MAX_PER_VIDEO,
		);
	}
}
