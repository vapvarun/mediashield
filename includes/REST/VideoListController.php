<?php
/**
 * What the admin Videos list needs beyond the core posts route.
 *
 * Basecamp 10530930295: the list showed title, platform, protection and
 * date only, with no way to narrow it or act on several videos. This adds:
 *
 * - `ms_list` on /wp/v2/mediashield-videos (editors only): thumbnail,
 *   duration, status (ready / encoding / failed) and where it is used.
 * - `ms_platform` and `ms_protection` filters on that collection.
 * - POST /mediashield/v1/videos/bulk: trash, restore or set protection on
 *   up to 100 videos, each checked against the user's own rights.
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\CPT\Thumbnail;
use MediaShield\CPT\VideoUsage;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Videos list fields, filters and bulk actions.
 */
class VideoListController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'mediashield/v1';

	/**
	 * Platforms a filter may name.
	 */
	const PLATFORMS = array( 'self', 'youtube', 'vimeo', 'wistia', 'bunny' );

	/**
	 * Protection levels a filter or bulk action may name ('default' = follows the site setting).
	 */
	const LEVELS = array( 'default', ...\MediaShield\Core\Settings::PROTECTION_LEVELS );

	/**
	 * Register everything.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/videos/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'ids'    => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'integer' ),
						'maxItems' => 100,
					),
					'action' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'trash', 'restore', 'protection' ),
					),
					'level'  => array(
						'type' => 'string',
						'enum' => self::LEVELS,
					),
				),
			)
		);

		// The filter chips' counts, one COUNT(*) for the library (Basecamp 10545260458).
		register_rest_route(
			$this->namespace,
			'/videos/counts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function () {
					return rest_ensure_response( \MediaShield\CPT\VideoStatus::counts() );
				},
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Every place one video is used, for the list's Show all (card 10551900031).
		register_rest_route(
			$this->namespace,
			'/videos/(?P<id>\d+)/used-in',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $request ) {
					return rest_ensure_response( array( 'places' => VideoUsage::all_for_video( (int) $request['id'] ) ) );
				},
				'permission_callback' => static function ( WP_REST_Request $request ) {
					$id = (int) $request['id'];
					return 'mediashield_video' === get_post_type( $id ) && current_user_can( 'edit_post', $id );
				},
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_field(
			'mediashield_video',
			'ms_list',
			array(
				'get_callback' => array( $this, 'list_field' ),
				'schema'       => array(
					'description' => 'Videos list columns: thumbnail, duration, status (ready, encoding, failed, unavailable, no_source, draft), source (uploaded file name), used_in (first places) and used_in_more. Null for users who cannot edit the video.',
					'type'        => array( 'object', 'null' ),
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}

	/**
	 * Hook the collection filters and the batched "used in" lookup.
	 */
	public static function register_hooks(): void {
		add_filter( 'rest_mediashield_video_collection_params', array( __CLASS__, 'collection_params' ) );
		add_filter( 'rest_mediashield_video_query', array( __CLASS__, 'filter_query' ), 10, 2 );
		add_filter( 'the_posts', array( __CLASS__, 'prime_usage' ), 10, 2 );
	}

	/**
	 * Document the filters on the collection (platform, state, tag, protection).
	 *
	 * @param array $params Collection params.
	 * @return array
	 */
	public static function collection_params( $params ): array {
		$params['ms_platform']   = array(
			'description' => 'Only videos on this platform.',
			'type'        => 'string',
			'enum'        => self::PLATFORMS,
		);
		$params['ms_state']      = array(
			'description' => 'Only videos with this status: ready, attention (no source, or unavailable at the platform) or draft.',
			'type'        => 'string',
			'enum'        => array( 'ready', 'attention', 'draft' ),
		);
		$params['ms_tag']        = array(
			'description' => 'Only videos carrying this video tag (an id from /mediashield/v1/tags).',
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$params['ms_protection'] = array(
			'description' => 'Only videos at this protection level; "default" means the video follows the site setting.',
			'type'        => 'string',
			'enum'        => self::LEVELS,
		);

		return (array) $params;
	}

	/**
	 * Turn the filters into query args.
	 *
	 * @param array           $args    WP_Query args.
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function filter_query( $args, $request ): array {
		$args = (array) $args;

		// "Used in" scans post content, so it is primed only for a response
		// that carries ms_list: the Videos list, not a picker asking for a
		// few fields (prime_usage()).
		$fields                 = $request instanceof WP_REST_Request ? wp_parse_list( (string) $request->get_param( '_fields' ) ) : array();
		$args['ms_prime_usage'] = empty( $fields ) || rest_is_field_included( 'ms_list', $fields );

		// The filters read protection settings; only people who edit videos may use them.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $args;
		}

		$meta_query = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		$platform = (string) $request->get_param( 'ms_platform' );
		if ( in_array( $platform, self::PLATFORMS, true ) ) {
			// A video saved before the platform meta existed counts as self-hosted.
			$meta_query[] = 'self' === $platform
				? array(
					'relation' => 'OR',
					array(
						'key'   => '_ms_platform',
						'value' => 'self',
					),
					array(
						'key'     => '_ms_platform',
						'compare' => 'NOT EXISTS',
					),
				)
				: array(
					'key'   => '_ms_platform',
					'value' => $platform,
				);
		}

		$level = (string) $request->get_param( 'ms_protection' );
		if ( in_array( $level, self::LEVELS, true ) ) {
			$meta_query[] = 'default' === $level
				? array(
					'relation' => 'OR',
					array(
						'key'     => '_ms_protection_level',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => '_ms_protection_level',
						'value' => '',
					),
				)
				: array(
					'key'   => '_ms_protection_level',
					'value' => $level,
				);
		}

		$tag = (int) $request->get_param( 'ms_tag' );
		if ( $tag > 0 ) {
			// shortcut: one IN list of the tag's videos; fine into the tens of thousands per tag.
			$ids              = \MediaShield\Tags\TagManager::get_videos_for_tag( $tag );
			$args['post__in'] = isset( $args['post__in'] ) && $args['post__in'] ? array_values( array_intersect( (array) $args['post__in'], $ids ) ) : $ids;
			$args['post__in'] = $args['post__in'] ? $args['post__in'] : array( 0 );
		}

		$state = (string) $request->get_param( 'ms_state' );
		$chip  = in_array( $state, array( 'ready', 'attention', 'draft' ), true ) ? \MediaShield\CPT\VideoStatus::query_args( $state ) : array();
		if ( isset( $chip['post_status'] ) && 'trash' !== ( $args['post_status'] ?? '' ) ) {
			// Within the statuses asked for: the block picker asks for
			// status=publish&ms_state=ready and gets published, playable
			// videos only; the Videos list asks for every status.
			$asked               = array_filter( (array) ( $args['post_status'] ?? array() ) );
			$within              = $asked ? array_values( array_intersect( $chip['post_status'], $asked ) ) : $chip['post_status'];
			$args['post_status'] = $within ? $within : array( 'ms-none' );
		}
		foreach ( $chip['meta_query'] ?? array() as $clause ) {
			$meta_query[] = $clause;
		}

		if ( $meta_query ) {
			$meta_query['relation'] = 'AND';
			$args['meta_query']     = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		return $args;
	}

	/**
	 * Before the list's rows are prepared, look up "used in" for all of them at once.
	 *
	 * Only for a collection request whose response includes ms_list
	 * (filter_query() sets `ms_prime_usage`); a picker that asks for
	 * `_fields=id,title,...` gets no content scan.
	 *
	 * @param WP_Post[] $posts Posts found.
	 * @param WP_Query  $query The query.
	 * @return WP_Post[]
	 */
	public static function prime_usage( $posts, $query ) {
		// The flag is set only by the REST collection filter (filter_query()).
		if ( $query instanceof WP_Query && $query->get( 'ms_prime_usage' )
			&& 'mediashield_video' === $query->get( 'post_type' ) && is_array( $posts ) && current_user_can( 'edit_posts' ) ) {
			VideoUsage::prime( wp_list_pluck( $posts, 'ID' ) );
		}

		return $posts;
	}

	/**
	 * The `ms_list` field for one video.
	 *
	 * @param array $post Prepared post data.
	 * @return array|null
	 */
	public function list_field( $post ) {
		$id = (int) ( $post['id'] ?? 0 );

		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			return null;
		}

		$platform = (string) get_post_meta( $id, '_ms_platform', true );
		$platform = '' !== $platform ? $platform : 'self';

		/**
		 * Filter a video's state for the Videos list.
		 *
		 * Pro reports a Bunny video that is still encoding or that Bunny
		 * failed to encode.
		 *
		 * @since 1.4.3
		 *
		 * @param string $status   'ready', 'encoding', 'failed' or 'unavailable' (the platform refuses it, CPT\SourceStatus).
		 * @param int    $video_id Video ID.
		 * @param string $platform Platform slug.
		 */
		$status = (string) apply_filters( 'mediashield_video_status', 'ready', $id, $platform );

		// Nothing to play outranks what a platform says; a draft that is
		// otherwise fine is a Draft (CPT\VideoStatus, Basecamp 10545260458).
		if ( '' !== (string) get_post_meta( $id, \MediaShield\CPT\VideoStatus::NO_SOURCE, true ) ) {
			$status = 'no_source';
		} elseif ( 'ready' === $status && in_array( get_post_status( $id ), \MediaShield\CPT\VideoStatus::DRAFT_STATUSES, true ) ) {
			$status = 'draft';
		}

		return array(
			'thumbnail'    => Thumbnail::poster_url( $id, 'thumbnail', true ),
			// The stored length, else the length members' players reported
			// (WatchedSpans::measured(), the watch rule's own number), so a
			// watched YouTube video is not "-" (MSUX-12).
			'duration'     => (int) get_post_meta( $id, '_ms_duration', true ) ?: (int) round( \MediaShield\Access\WatchedSpans::measured( $id ) ),
			'status'       => in_array( $status, array( 'ready', 'encoding', 'failed', 'unavailable', 'no_source', 'draft' ), true ) ? $status : 'ready',
			'source'       => self::source_label( $id, $platform ),
			'used_in'      => VideoUsage::for_video( $id ),
			// More places than the row shows: the list offers Show all (/videos/<id>/used-in).
			'used_in_more' => VideoUsage::has_more( $id ),
			// No Duration while a watch rule uses it (1.4.4): the list asks for one.
			'needs_length' => \MediaShield\CPT\VideoStatus::needs_length( $id ),
		);
	}

	/**
	 * The uploaded file's name, for a video whose file MediaShield holds.
	 *
	 * The source URL of an upload is the signed /stream/ address, which means
	 * nothing to an owner; the file name does.
	 *
	 * @param int    $id       Video ID.
	 * @param string $platform Platform slug.
	 * @return string File name, or '' for a link.
	 */
	public static function source_label( int $id, string $platform ): string {
		if ( 'self' !== $platform ) {
			return '';
		}
		$file = (string) get_post_meta( $id, '_ms_owned_file', true );
		if ( '' === $file ) {
			$file = \MediaShield\REST\StreamController::resolve_file( $id );
		}
		return '' !== $file ? \MediaShield\Upload\Drivers\SelfHosted::display_name( $id, $file ) : '';
	}

	/**
	 * POST /videos/bulk - one action on several videos.
	 *
	 * Each video is checked on its own, and one that has already moved on
	 * (deleted, already in the trash, not yours) is reported, not fatal.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk( WP_REST_Request $request ) {
		$action = (string) $request->get_param( 'action' );
		$level  = (string) $request->get_param( 'level' );

		if ( 'protection' === $action && ! in_array( $level, self::LEVELS, true ) ) {
			return new WP_Error( 'invalid_level', __( 'Choose a protection level.', 'mediashield' ), array( 'status' => 400 ) );
		}

		$done    = array();
		$skipped = array();

		foreach ( array_unique( array_map( 'absint', (array) $request->get_param( 'ids' ) ) ) as $id ) {
			$post = get_post( $id );

			if ( ! $post || 'mediashield_video' !== $post->post_type ) {
				$skipped[] = array(
					'id'     => $id,
					'reason' => 'not_found',
				);
				continue;
			}

			$cap = 'protection' === $action ? 'edit_post' : 'delete_post';
			if ( ! current_user_can( $cap, $id ) ) {
				$skipped[] = array(
					'id'     => $id,
					'reason' => 'not_allowed',
				);
				continue;
			}

			$ok = false;

			if ( 'trash' === $action ) {
				$ok = 'trash' === $post->post_status || (bool) wp_trash_post( $id );
			} elseif ( 'restore' === $action ) {
				$ok = 'trash' !== $post->post_status || (bool) wp_untrash_post( $id );
			} elseif ( 'default' === $level ) {
				delete_post_meta( $id, '_ms_protection_level' );
				$ok = true;
			} else {
				update_post_meta( $id, '_ms_protection_level', $level );
				$ok = true;
			}

			if ( $ok ) {
				$done[] = $id;
			} else {
				$skipped[] = array(
					'id'     => $id,
					'reason' => 'failed',
				);
			}
		}

		return new WP_REST_Response(
			array(
				'done'    => $done,
				'skipped' => $skipped,
			),
			200
		);
	}
}
