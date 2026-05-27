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
		add_action( 'add_meta_boxes_mediashield_playlist', array( __CLASS__, 'register_items_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_items_assets' ) );
	}

	/**
	 * Register the "Playlist Items" meta box on the playlist CPT edit screen.
	 *
	 * Until this shipped, the only way to manage playlist items was via the
	 * MediaShield admin SPA (Playlists page → "Manage items"). Users who
	 * arrived at the playlist via the WP-native Edit link saw a blank
	 * Gutenberg page with no items UI — confirmed gap during UX sweep
	 * 2026-05-27.
	 *
	 * The meta box renders a React mount point; the existing admin SPA
	 * bundle picks it up and mounts the same PlaylistItemsModal content
	 * inline (no modal chrome).
	 */
	public static function register_items_meta_box(): void {
		add_meta_box(
			'mediashield-playlist-items',
			__( 'Playlist Items', 'mediashield' ),
			array( __CLASS__, 'render_items_meta_box' ),
			'mediashield_playlist',
			'normal',
			'high'
		);
	}

	/**
	 * Output the React mount point + small label for the items meta box.
	 *
	 * @param \WP_Post $post Current playlist post.
	 */
	public static function render_items_meta_box( \WP_Post $post ): void {
		printf(
			'<div id="mediashield-playlist-items-root" data-playlist-id="%d" data-playlist-title="%s"></div>',
			(int) $post->ID,
			esc_attr( $post->post_title )
		);
		printf(
			'<noscript><p>%s</p></noscript>',
			esc_html__( 'JavaScript is required to manage playlist items.', 'mediashield' )
		);
	}

	/**
	 * Enqueue the admin SPA bundle on the playlist edit screen so the React
	 * mount point in the items meta box has something to attach to.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_items_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'mediashield_playlist' !== $screen->post_type ) {
			return;
		}

		$build_dir = MEDIASHIELD_PATH . 'build/admin/';
		$build_url = MEDIASHIELD_URL . 'build/admin/';
		$asset_file = $build_dir . 'index.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-data', 'wp-notices' ),
			'version'      => MEDIASHIELD_VERSION,
		);

		wp_enqueue_script(
			'mediashield-playlist-items',
			$build_url . 'index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style(
			'mediashield-playlist-items',
			$build_url . 'index.css',
			array( 'wp-components' ),
			$asset['version']
		);
		wp_localize_script(
			'mediashield-playlist-items',
			'mediashieldAdmin',
			array(
				'restUrl'   => rest_url( 'mediashield/v1/' ),
				'wpRestUrl' => rest_url( 'wp/v2/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'adminUrl'  => admin_url(),
			)
		);
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
