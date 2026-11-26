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
		// The classic edit screen, like Videos: one way of editing for both
		// (Basecamp 10545260546). The block editor hid the items in a drawer.
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'classic_screen' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'render_main_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_items_assets' ) );
		// Before redirect_canonical (10), which would guess some other post for the 404.
		add_action( 'template_redirect', array( __CLASS__, 'redirect_old_address' ), 5 );
		// Playlists list: how many videos each holds (MSUX-31), one query per page.
		add_filter( 'the_posts', array( __CLASS__, 'prime_item_counts' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_item_count_field' ) );
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
			__( 'Videos in this playlist', 'mediashield' ),
			array( __CLASS__, 'render_items_meta_box' ),
			'mediashield_playlist',
			self::MAIN_CONTEXT,
			'high'
		);
	}

	/**
	 * Meta box context drawn straight under the title, so the videos come
	 * before the description, as the video comes first on a video.
	 */
	const MAIN_CONTEXT = 'ms_main';

	/**
	 * Playlists edit on the classic screen, as videos do.
	 *
	 * @param bool   $use_block_editor Whether to use the block editor.
	 * @param string $post_type        Post type.
	 * @return bool
	 */
	public static function classic_screen( $use_block_editor, $post_type ) {
		return 'mediashield_playlist' === $post_type ? false : $use_block_editor;
	}

	/**
	 * Draw the main-column boxes under the title.
	 *
	 * @param \WP_Post $post Post being edited.
	 */
	public static function render_main_boxes( $post ): void {
		if ( $post instanceof \WP_Post && 'mediashield_playlist' === $post->post_type ) {
			do_meta_boxes( get_current_screen(), self::MAIN_CONTEXT, $post );
		}
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
		\MediaShield\Core\Assets::enqueue_admin_bundle( 'mediashield-playlist-items' );
		wp_enqueue_style(
			'mediashield-playlist-items',
			$build_url . 'index.css',
			array( 'wp-components' ),
			(string) filemtime( $build_dir . 'index.css' )
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
				'labels'              => $labels,
				// Not a page of its own (Basecamp 10545260546). A playlist shows
				// where the owner places it, with the Playlist block or the
				// [mediashield_playlist] shortcode, like a video. The old
				// /playlist/<slug>/ address answers 404 once the rules are
				// rebuilt (Migrator v13).
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_nav_menus'   => false,
				'query_var'           => false,
				'show_in_rest'        => true,
				'rest_base'           => 'mediashield-playlists',
				// Reading needs edit_posts: a playlist is shown only where it
				// is placed (REST\LibraryPostsController).
				'rest_controller_class' => \MediaShield\REST\LibraryPostsController::class,
				'has_archive'         => false,
				'rewrite'             => false,
				// custom-fields is what puts registered meta in the REST response.
				// Without it the four playback options below could be read by
				// the player and written by nothing - the admin only displayed them.
				'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'menu_icon'           => 'dashicons-playlist-video',
				'show_in_menu'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Where does a 1.4.1-1.4.3 playlist address lead now?
	 *
	 * Those versions gave every playlist its own page, /playlist/<slug>/ (or
	 * ?mediashield_playlist=<slug> on plain permalinks). Since 1.4.4 a
	 * playlist shows only where it is placed, so an old link goes to the
	 * page that places it (VideoUsage::page_for_playlist()), and answers 404
	 * when no page does (Basecamp 10545260546).
	 *
	 * @since 1.4.4
	 *
	 * @return string|null The page to send the visitor to, '' for a 404, null when this is not an old playlist address.
	 */
	public static function old_address_target(): ?string {
		global $wp;

		$slug = '';
		if ( is_404() && $wp instanceof \WP && preg_match( '#^playlist/([^/]+)/?$#', (string) $wp->request, $m ) ) {
			$slug = sanitize_title( $m[1] );
		} elseif ( isset( $_GET['mediashield_playlist'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A public address, read only.
			$slug = sanitize_title( wp_unslash( $_GET['mediashield_playlist'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( '' === $slug ) {
			return null;
		}

		$playlist = get_page_by_path( $slug, OBJECT, 'mediashield_playlist' );
		if ( ! $playlist || 'publish' !== $playlist->post_status || post_password_required( $playlist ) ) {
			return '';
		}

		return \MediaShield\CPT\VideoUsage::page_for_playlist( (int) $playlist->ID );
	}

	/**
	 * Send an old playlist address on to the page that shows the playlist (301), or answer 404.
	 */
	public static function redirect_old_address(): void {
		$target = self::old_address_target();
		if ( null === $target ) {
			return;
		}

		if ( '' !== $target ) {
			wp_safe_redirect( $target, 301, 'MediaShield' );
			exit;
		}

		// No page shows it. On plain permalinks the query string would
		// otherwise fall through to the home page.
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		remove_action( 'template_redirect', 'redirect_canonical' );
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

	/**
	 * Item counts for the playlists a query returned, keyed by playlist ID.
	 *
	 * @var array<int,int>
	 */
	private static array $item_counts = array();

	/**
	 * Count the items of a page of playlists in one query.
	 *
	 * @param array     $posts Posts found.
	 * @param \WP_Query $query The query.
	 * @return array
	 */
	public static function prime_item_counts( $posts, $query ) {
		if ( ! is_array( $posts ) || ! $posts || ! $query instanceof \WP_Query || 'mediashield_playlist' !== $query->get( 'post_type' ) || ! defined( 'REST_REQUEST' ) ) {
			return $posts;
		}
		global $wpdb;
		$ids = array_map( 'intval', wp_list_pluck( $posts, 'ID' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, one query per page.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT playlist_id, COUNT(*) AS n FROM %i WHERE playlist_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') GROUP BY playlist_id', $wpdb->prefix . 'ms_playlist_items', ...$ids ), OBJECT_K );
		foreach ( $ids as $id ) {
			self::$item_counts[ $id ] = isset( $rows[ $id ] ) ? (int) $rows[ $id ]->n : 0;
		}
		return $posts;
	}

	/**
	 * The `ms_item_count` field on wp/v2/mediashield-playlists (editors only).
	 */
	public static function register_item_count_field(): void {
		register_rest_field(
			'mediashield_playlist',
			'ms_item_count',
			array(
				'get_callback' => static function ( $post ) {
					$id = (int) ( $post['id'] ?? 0 );
					if ( ! current_user_can( 'edit_post', $id ) ) {
						return null;
					}
					if ( ! isset( self::$item_counts[ $id ] ) ) {
						global $wpdb;
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, a single playlist read.
						self::$item_counts[ $id ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ms_playlist_items WHERE playlist_id = %d", $id ) );
					}
					return self::$item_counts[ $id ];
				},
				'schema'       => array(
					'description' => 'How many videos the playlist holds, published or not.',
					'type'        => array( 'integer', 'null' ),
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}
}
