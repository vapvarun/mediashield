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

		// The picker's row: what the video is, in owners' words.
		register_rest_field(
			'mediashield_video',
			'ms_card',
			array(
				'get_callback' => static function ( $post ) {
					$id = (int) ( $post['id'] ?? 0 );
					return $id && current_user_can( 'read_post', $id ) ? self::card( $id ) : null;
				},
				'schema'       => array(
					'description' => 'Platform name, length, poster and protection in owners\' words (VideoSummaryController::card()).',
					'type'        => array( 'object', 'null' ),
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
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

		$id = \MediaShield\CPT\VideoCreator::find( $link['platform'], $link['video_id'], $url );

		return new WP_REST_Response(
			array(
				'platform'       => $link['platform'],
				'platform_label' => Platforms::label( $link['platform'], false ),
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
	 * What the block and its sidebar show about a video.
	 *
	 * @param int $video_id Video ID.
	 * @param int $post_id  Post being edited, left out of used_in.
	 * @return array<string, mixed>
	 */
	public static function summary( int $video_id, int $post_id ): array {
		$places = array_values(
			array_filter(
				VideoUsage::for_video( $video_id ),
				static fn( $place ) => (int) $place['id'] !== $post_id
			)
		);

		$title = get_the_title( $video_id );

		return array(
			'id'           => $video_id,
			// Plain text: the block shows it as text, so get_the_title()'s
			// texturised entities (&#8211;) would show literally.
			'title'        => '' !== $title ? html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : __( '(no title)', 'mediashield' ),
			'status'       => (string) get_post_status( $video_id ),
			'edit_url'     => current_user_can( 'edit_post', $video_id ) ? (string) get_edit_post_link( $video_id, 'raw' ) : '',
			'used_in'      => $places,
			'used_in_more' => VideoUsage::has_more( $video_id ),
		) + self::card( $video_id );
	}

	/**
	 * A video as owners recognise it: "YouTube · 12:04 · Site default (Standard)",
	 * plus its poster. Never a platform slug or an id.
	 *
	 * The block, the "Choose a Video" picker (as the `ms_card` field on
	 * /wp/v2/mediashield-videos) and summary() all read this, with the same
	 * platform names and protection words as the Videos list.
	 *
	 * @since 1.4.4
	 *
	 * @param int $video_id Video ID.
	 * @return array{platform: string, platform_label: string, duration: int, thumbnail: string, protection: string, protection_label: string, follows_default: bool}
	 */
	public static function card( int $video_id ): array {
		$platform = (string) get_post_meta( $video_id, '_ms_platform', true );
		$platform = '' !== $platform ? $platform : Platforms::SELF_HOSTED;
		$level    = Protection::resolve_level( $video_id );
		$levels   = Protection::levels();
		$own      = (string) get_post_meta( $video_id, '_ms_protection_level', true );

		// poster_url() falls back to YouTube's public image itself, and not
		// for a video YouTube no longer plays.
		$thumbnail = Thumbnail::poster_url( $video_id, 'large', true );

		return array(
			'platform'         => $platform,
			'platform_label'   => Platforms::label( $platform, Platforms::SELF_HOSTED === $platform ? '' !== VideoListController::source_label( $video_id, $platform ) : null ),
			'duration'         => (int) get_post_meta( $video_id, '_ms_duration', true ),
			'thumbnail'        => $thumbnail,
			'protection'       => $level,
			// The one wording for "follows the site": "Site default (Standard)".
			'protection_label' => '' === $own ? Protection::site_default_label() : ( $levels[ $level ]['label'] ?? $level ),
			'follows_default'  => '' === $own,
		);
	}
}
