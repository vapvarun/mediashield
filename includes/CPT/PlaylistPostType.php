<?php
/**
 * Register mediashield_playlist custom post type and meta.
 *
 * @package MediaShield\CPT
 */

namespace MediaShield\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PlaylistPostType
 *
 * Registers the mediashield_playlist custom post type and meta fields.
 *
 * @since 1.0.0
 */
class PlaylistPostType {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * Register the mediashield_playlist CPT.
	 */
	public static function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Playlists', 'Post type general name', 'mediashield' ),
			'singular_name'         => _x( 'Playlist', 'Post type singular name', 'mediashield' ),
			'menu_name'             => _x( 'Playlists', 'Admin Menu text', 'mediashield' ),
			'add_new'               => __( 'Add New Playlist', 'mediashield' ),
			'add_new_item'          => __( 'Add New Playlist', 'mediashield' ),
			'edit_item'             => __( 'Edit Playlist', 'mediashield' ),
			'new_item'              => __( 'New Playlist', 'mediashield' ),
			'view_item'             => __( 'View Playlist', 'mediashield' ),
			'search_items'          => __( 'Search Playlists', 'mediashield' ),
			'not_found'             => __( 'No playlists found.', 'mediashield' ),
			'not_found_in_trash'    => __( 'No playlists found in Trash.', 'mediashield' ),
			'all_items'             => __( 'All Playlists', 'mediashield' ),
			'archives'              => __( 'Playlist Archives', 'mediashield' ),
			'filter_items_list'     => __( 'Filter playlists list', 'mediashield' ),
			'items_list_navigation' => __( 'Playlists list navigation', 'mediashield' ),
			'items_list'            => __( 'Playlists list', 'mediashield' ),
		);

		register_post_type(
			'mediashield_playlist',
			array(
				'labels'          => $labels,
				'public'          => true,
				'show_in_rest'    => true,
				'rest_base'       => 'mediashield-playlists',
				'has_archive'     => false,
				'rewrite'         => array(
					'slug'       => 'playlist',
					'with_front' => false,
				),
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'menu_icon'       => 'dashicons-playlist-video',
				'show_in_menu'    => false,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Register playlist post meta fields.
	 */
	public static function register_meta(): void {
		$meta_fields = array(
			'_ms_autoplay'  => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'_ms_countdown' => array(
				'type'    => 'integer',
				'default' => 5,
			),
			'_ms_loop'      => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'_ms_shuffle'   => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);

		foreach ( $meta_fields as $key => $args ) {
			register_post_meta(
				'mediashield_playlist',
				$key,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => $args['type'],
					'default'           => $args['default'],
					'sanitize_callback' => 'boolean' === $args['type'] ? 'rest_sanitize_boolean' : 'absint',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}
}
