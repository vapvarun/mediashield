<?php
/**
 * Register mediashield_video custom post type and meta.
 *
 * @package MediaShield\CPT
 */

namespace MediaShield\CPT;

class VideoPostType {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * Register the mediashield_video CPT.
	 */
	public static function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Videos', 'Post type general name', 'mediashield' ),
			'singular_name'         => _x( 'Video', 'Post type singular name', 'mediashield' ),
			'menu_name'             => _x( 'Videos', 'Admin Menu text', 'mediashield' ),
			'add_new'               => __( 'Add New Video', 'mediashield' ),
			'add_new_item'          => __( 'Add New Video', 'mediashield' ),
			'edit_item'             => __( 'Edit Video', 'mediashield' ),
			'new_item'              => __( 'New Video', 'mediashield' ),
			'view_item'             => __( 'View Video', 'mediashield' ),
			'search_items'          => __( 'Search Videos', 'mediashield' ),
			'not_found'             => __( 'No videos found.', 'mediashield' ),
			'not_found_in_trash'    => __( 'No videos found in Trash.', 'mediashield' ),
			'all_items'             => __( 'All Videos', 'mediashield' ),
			'archives'              => __( 'Video Archives', 'mediashield' ),
			'filter_items_list'     => __( 'Filter videos list', 'mediashield' ),
			'items_list_navigation' => __( 'Videos list navigation', 'mediashield' ),
			'items_list'            => __( 'Videos list', 'mediashield' ),
		);

		register_post_type( 'mediashield_video', array(
			'labels'             => $labels,
			'public'             => true,
			'show_in_rest'       => true,
			'rest_base'          => 'mediashield-videos',
			'has_archive'        => false,
			'rewrite'            => array( 'slug' => 'video', 'with_front' => false ),
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'menu_icon'          => 'dashicons-video-alt3',
			'show_in_menu'       => false, // Managed by our custom admin SPA menu.
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
		) );
	}

	/**
	 * Register video post meta fields.
	 */
	public static function register_meta(): void {
		$meta_fields = array(
			'_ms_platform'          => array(
				'type'    => 'string',
				'default' => 'self',
			),
			'_ms_platform_video_id' => array(
				'type'    => 'string',
				'default' => '',
			),
			'_ms_source_url'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'_ms_protection_level'  => array(
				'type'    => 'string',
				'default' => 'standard',
			),
			'_ms_access_role'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'_ms_duration'          => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'_ms_stream_url'        => array(
				'type'    => 'string',
				'default' => '',
			),
		);

		foreach ( $meta_fields as $key => $args ) {
			register_post_meta( 'mediashield_video', $key, array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => $args['type'],
				'default'           => $args['default'],
				'sanitize_callback' => 'string' === $args['type'] ? 'sanitize_text_field' : 'absint',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
		}
	}
}
