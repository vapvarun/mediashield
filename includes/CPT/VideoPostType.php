<?php
/**
 * Register mediashield_video custom post type and meta.
 *
 * @package MediaShield\CPT
 */

namespace MediaShield\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VideoPostType
 *
 * Registers the mediashield_video custom post type and meta fields.
 *
 * @since 1.0.0
 */
class VideoPostType {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_filter( 'rest_prepare_mediashield_video', array( __CLASS__, 'hide_meta_from_viewers' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_mediashield_video', array( __CLASS__, 'save_meta_box' ), 10, 2 );
		// After the meta box (10) on the edit screen, and after REST has written
		// meta for the block and the wizard - both routes end in one normalizer.
		add_action( 'save_post_mediashield_video', array( __CLASS__, 'normalize_platform' ), 20 );
		add_action(
			'rest_after_insert_mediashield_video',
			static function ( \WP_Post $post ): void {
				self::normalize_platform( $post->ID );
			}
		);
		// A link that can never play is refused where it is added: REST (the
		// wizard, the block, any client) here, the edit screen in
		// save_meta_box() (Basecamp 10545260436).
		add_filter( 'rest_pre_insert_mediashield_video', array( __CLASS__, 'refuse_unplayable_source' ), 10, 2 );
		add_filter( 'redirect_post_location', array( __CLASS__, 'flag_refused_source' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'render_refused_source_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_edit_assets' ) );
		// Hide WP's default Custom Fields meta box on the video edit screen —
		// it surfaces raw post-meta key/value pairs and confuses site owners
		// who only need the structured fields we already render. Users who
		// want it back can flip it on via Screen Options at any time.
		add_action( 'add_meta_boxes_mediashield_video', array( __CLASS__, 'hide_default_meta_boxes' ), 100 );
	}

	/**
	 * Remove WP-default meta boxes that aren't useful on the video edit
	 * screen by default. Fires late (priority 100) so it removes WP's box
	 * after WP-core has registered it.
	 */
	public static function hide_default_meta_boxes(): void {
		remove_meta_box( 'postcustom', 'mediashield_video', 'normal' );
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

		register_post_type(
			'mediashield_video',
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_rest'        => true,
				'rest_base'           => 'mediashield-videos',
				// Editors only, as for playlists (REST\LibraryPostsController).
				'rest_controller_class' => \MediaShield\REST\LibraryPostsController::class,
				'has_archive'         => false,
				'rewrite'             => false,
				'supports'            => array( 'title', 'thumbnail', 'custom-fields' ),
				'menu_icon'           => 'dashicons-video-alt3',
				'show_in_menu'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register meta boxes for the video edit screen.
	 */
	public static function register_meta_boxes(): void {
		// The order an owner works in (Basecamp 10545260613): the video first,
		// then who can watch and how it is protected, then milestones.
		add_meta_box(
			'mediashield-video-source',
			__( '1. The video', 'mediashield' ),
			array( __CLASS__, 'render_source_meta_box' ),
			'mediashield_video',
			'normal',
			'high'
		);

		add_meta_box(
			'mediashield-video-settings',
			__( '2. Who can watch, and how it is protected', 'mediashield' ),
			array( __CLASS__, 'render_settings_meta_box' ),
			'mediashield_video',
			'normal',
			'high'
		);

		add_meta_box(
			'mediashield-video-embed',
			__( 'Embed This Video', 'mediashield' ),
			array( __CLASS__, 'render_embed_meta_box' ),
			'mediashield_video',
			'side',
			'high'
		);

		add_meta_box(
			'mediashield-video-milestones',
			__( '3. Milestones', 'mediashield' ),
			array( __CLASS__, 'render_milestones_meta_box' ),
			'mediashield_video',
			'normal',
			'default'
		);

		add_meta_box(
			'mediashield-video-player',
			__( 'Player Options', 'mediashield' ),
			array( __CLASS__, 'render_player_meta_box' ),
			'mediashield_video',
			'side',
			'default'
		);

		// Pro teaser meta boxes (only when Pro is NOT active). The LMS one
		// only when an LMS Pro links to is active (the detection Pro's
		// LMSManager uses): with none it sold a feature the site cannot use.
		if ( ! defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
			if ( defined( 'LEARNDASH_VERSION' ) || function_exists( 'tutor' ) || function_exists( 'llms' ) ) {
				add_meta_box(
					'mediashield-pro-lms-teaser',
					__( 'LMS Integration', 'mediashield' ) . ' <span class="ms-pro-badge-small">PRO</span>',
					array( __CLASS__, 'render_lms_teaser' ),
					'mediashield_video',
					'side',
					'low'
				);
			}
			add_meta_box(
				'mediashield-pro-features-teaser',
				__( 'Pro Features', 'mediashield' ) . ' <span class="ms-pro-badge-small">PRO</span>',
				array( __CLASS__, 'render_pro_teaser' ),
				'mediashield_video',
				'side',
				'low'
			);
		}
	}

	/**
	 * Enqueue the video edit screen meta-box CSS/JS (only on that screen).
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_edit_assets( string $hook_suffix ): void {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'mediashield_video' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'mediashield-admin-video', MEDIASHIELD_URL . 'assets/css/admin-video.css', array(), MEDIASHIELD_VERSION );
		wp_enqueue_script( 'mediashield-admin-video', MEDIASHIELD_URL . 'assets/js/admin-video.js', array( 'mediashield-request' ), MEDIASHIELD_VERSION, true );
		wp_localize_script(
			'mediashield-admin-video',
			'mediashieldVideoAdmin',
			array(
				'labels' => array(
					'youtube'         => __( 'YouTube', 'mediashield' ),
					'vimeo'           => __( 'Vimeo', 'mediashield' ),
					'wistia'          => __( 'Wistia', 'mediashield' ),
					'bunny'           => __( 'Bunny Stream', 'mediashield' ),
					'self'            => __( 'Video file', 'mediashield' ),
					'bunnyCollection' => __( 'That is a Bunny collection (a folder of videos), not a single video. Open the video itself in Bunny Stream and paste that URL, or paste its embed URL.', 'mediashield' ),
					'bunnyDashboard'  => __( 'That looks like a Bunny dashboard link we cannot read a video ID from. Open the video in Bunny Stream and copy the URL from the address bar, or use its embed URL.', 'mediashield' ),
					'unrecognised'    => \MediaShield\Support\Platforms::unplayable_message(),
					'bunnyStreamFile' => __( 'This is a Bunny stream file from a library this site is not connected to, so it will be saved as a direct stream. That only plays if token authentication is off for the library. To use Bunny\'s player instead, paste the video\'s embed URL.', 'mediashield' ),
					'uploading'       => __( 'Uploading…', 'mediashield' ),
					/* translators: %s: percentage sent so far, e.g. "42%". */
					'uploadSending'   => __( 'Sending to your site: %s', 'mediashield' ),
					/* translators: %s: upload target, e.g. "Bunny Stream - Course videos". */
					'uploadHandoff'   => __( 'Sending to %s. A large file can take a few minutes; keep this page open.', 'mediashield' ),
					/* translators: 1: size of the chosen file, e.g. "27 MB"; 2: largest upload this server accepts, e.g. "2 MB". */
					'tooLarge'        => __( 'This file is %1$s; your server allows %2$s.', 'mediashield' ),
					'tooLargeBunny'   => __( 'Upload it in Bunny, then use Browse & Import.', 'mediashield' ),
					'tooLargeOther'   => __( 'Ask your host to raise the upload limit, or upload the file to your video platform and paste its URL above.', 'mediashield' ),
					'uploadDone'      => __( 'Uploaded. Save the video to keep this file.', 'mediashield' ),
					'uploadFailed'    => __( 'Upload failed:', 'mediashield' ),
					'uploadNetwork'   => __( 'Upload failed: the connection dropped or the file is larger than this server accepts.', 'mediashield' ),
				),
				'restUrl'        => esc_url_raw( rest_url( 'mediashield/v1/' ) ),
				// Checked in the browser before anything is sent, so an
				// over-limit file never starts a request (Basecamp 10530929713).
				'maxUploadBytes' => (int) wp_max_upload_size(),
				'maxUploadLabel' => size_format( wp_max_upload_size() ),
				'uploadTarget'   => \MediaShield\Upload\UploadManager::target_label(),
				'uploadDriver'   => \MediaShield\Upload\UploadManager::resolve_default_driver(),
				// Pro's Bunny library browser, for the "too large" hint.
				'browseUrl'      => esc_url_raw( admin_url( 'admin.php?page=mediashield#/bunny-browser' ) ),
				// Object, not array: an empty PHP array would reach JS as [].
				'bunnyPullZones' => (object) \MediaShield\Support\Platforms::bunny_pull_zones(),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				// The post being edited. get_the_ID() is not dependable this
				// early on post-new.php, so read the screen's own post object,
				// which WordPress has already set up by admin_enqueue_scripts.
				'postId'         => isset( $GLOBALS['post']->ID ) ? (int) $GLOBALS['post']->ID : 0,
			)
		);
	}

	/**
	 * Render the Video Source meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render_source_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'mediashield_video_meta', '_ms_meta_nonce' );

		$platform = get_post_meta( $post->ID, '_ms_platform', true ) ?: '';
		$video_id = get_post_meta( $post->ID, '_ms_platform_video_id', true );
		$source   = get_post_meta( $post->ID, '_ms_source_url', true );
		$duration = (int) get_post_meta( $post->ID, '_ms_duration', true );
		$is_new   = empty( $platform ) && empty( $source );
		$has_pro  = defined( 'MEDIASHIELD_PRO_VERSION' );

		// Check connected platforms.
		$connected_platforms = array();
		if ( $has_pro ) {
			global $wpdb;
			$table = "{$wpdb->prefix}ms_platform_connections";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$connected_platforms = $wpdb->get_col( "SELECT platform FROM {$table} WHERE is_active = 1" );
		}
		?>
		<div class="ms-source-meta-box">
			<?php
			// Suppress browser autofill for the entire metabox. Without this,
			// Chrome/Firefox happily refill the URL input with the previously-
			// submitted value when a new video is added — looking to QA like
			// the form is "leaking" state across posts.
			?>
			<input type="text" style="display:none !important" autocomplete="username" tabindex="-1" aria-hidden="true">
			<?php if ( $is_new ) : ?>
				<p class="ms-source-intro">
					<?php
					// Which links work is said once, under the field (Platforms::link_hint()).
					esc_html_e( 'Paste a link or upload a file. The platform is read from the link.', 'mediashield' );
					?>
				</p>

				<?php if ( $has_pro && ! empty( $connected_platforms ) ) : ?>
					<p class="ms-source-intro" style="color: #2271b1;">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg returns inline SVG built from a vendored Lucide path map; no user input.
						echo \MediaShield\Support\Icons::svg(
							'circle-check',
							array(
								'size'  => 16,
								'class' => 'ms-inline-icon',
							)
						);
						?>
						<?php
						printf(
							/* translators: %s: comma-separated platform names */
							esc_html__( 'Connected platforms: %s. Use Browse & Import for bulk actions.', 'mediashield' ),
							esc_html( implode( ', ', array_map( array( __CLASS__, 'get_platform_label' ), $connected_platforms ) ) )
						);
						?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=mediashield#/platforms' ) ); ?>"><?php esc_html_e( 'Manage Platforms →', 'mediashield' ); ?></a>
					</p>
				<?php elseif ( $has_pro ) : ?>
					<div class="notice notice-info inline" style="margin: 8px 0;">
						<p>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg returns inline SVG built from a vendored Lucide path map; no user input.
							echo \MediaShield\Support\Icons::svg(
								'cloud',
								array(
									'size'  => 16,
									'class' => 'ms-inline-icon',
								)
							);
							?>
							<?php
							printf(
								/* translators: %s: link to Platforms page */
								esc_html__( 'No cloud platforms connected. %s to enable browsing, bulk import, and protected streaming from Bunny, YouTube, Vimeo, or Wistia.', 'mediashield' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=mediashield#/platforms' ) ) . '"><strong>' . esc_html__( 'Connect a platform', 'mediashield' ) . '</strong></a>'
							);
							?>
						</p>
					</div>
				<?php else : ?>
					<p class="ms-source-intro" style="color: #757575;">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg returns inline SVG built from a vendored Lucide path map; no user input.
						echo \MediaShield\Support\Icons::svg(
							'lock',
							array(
								'size'  => 16,
								'class' => 'ms-inline-icon',
							)
						);
						?>
						<?php esc_html_e( 'YouTube, Vimeo and Wistia links play here as they are. MediaShield Pro adds Bunny Stream: browse your Bunny library and import videos in one step.', 'mediashield' ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<?php
			// A file this site holds is shown by its name, length and size,
			// not by the signed /stream/ address its source field carries
			// (Basecamp 10545260613). The link field is still there, empty, to
			// replace the file with a link; left empty, the file stays.
			$held = 'self' === $platform ? \MediaShield\REST\StreamController::resolve_file( $post->ID ) : '';
			if ( '' !== $held ) :
				?>
				<div class="ms-held-file">
					<?php $held_name = \MediaShield\Upload\Drivers\SelfHosted::display_name( $post->ID, $held ); ?>
					<strong class="ms-held-file__name" title="<?php echo esc_attr( $held_name ); ?>"><?php echo esc_html( $held_name ); ?></strong>
					<span class="ms-held-file__meta">
						<?php
						echo esc_html(
							implode(
								' · ',
								array_filter(
									array(
										__( 'Uploaded file', 'mediashield' ),
										$duration > 0 ? self::length_label( $duration ) : '',
										size_format( (int) filesize( $held ) ),
										__( 'kept private, played through a signed link', 'mediashield' ),
									)
								)
							)
						);
						?>
					</span>
					<input type="hidden" name="_ms_source_held" value="1" />
				</div>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="ms-video-url"><?php echo '' !== $held ? esc_html__( 'Replace with a link', 'mediashield' ) : esc_html__( 'Video link', 'mediashield' ); ?></label></th>
					<td>
						<input type="url" id="ms-video-url" name="_ms_source_url"
							value="<?php echo '' !== $held ? '' : esc_url( $source ); ?>" class="large-text"
							autocomplete="off"
							data-form-type="other"
							placeholder="<?php esc_attr_e( 'https://www.youtube.com/watch?v=... or https://vimeo.com/...', 'mediashield' ); ?>" />
						<p class="description"><?php echo '' !== $held ? esc_html__( 'Leave empty to keep the uploaded file.', 'mediashield' ) : esc_html( \MediaShield\Support\Platforms::link_hint() ); ?></p>
					</td>
				</tr>
				<?php
				// Uploading a file was the first thing buyers tried and the one
				// thing this screen could not do: it offered a URL field only, so
				// self-hosting meant uploading through the Media Library
				// separately and pasting the resulting URL back (BC#10143668243).
				// The /upload/init route and its driver already existed and were
				// already proven by Pro's frontend upload form - nothing was
				// reaching them from the admin.
				if ( current_user_can( 'upload_mediashield' ) ) :
					?>
				<tr>
					<th><label for="ms-video-file"><?php esc_html_e( 'Or upload a file', 'mediashield' ); ?></label></th>
					<td>
						<input type="file" id="ms-video-file" accept="video/*" />
						<button type="button" class="button" id="ms-upload-btn" disabled>
							<?php esc_html_e( 'Upload', 'mediashield' ); ?>
						</button>
						<div class="ms-upload-progress" id="ms-upload-progress-wrap" hidden>
							<progress id="ms-upload-progress" max="100" value="0" aria-labelledby="ms-upload-status"></progress>
							<span class="ms-upload-progress__pct" id="ms-upload-pct" aria-hidden="true"></span>
						</div>
						<p id="ms-upload-status" class="description" role="status" aria-live="polite"></p>
						<p class="description ms-upload-target">
							<?php
							printf(
								/* translators: 1: upload target, e.g. "Bunny Stream - Course videos"; 2: largest upload this server accepts, e.g. "64 MB". */
								esc_html__( 'Uploads to: %1$s. Largest file this server accepts: %2$s.', 'mediashield' ),
								'<strong>' . esc_html( \MediaShield\Upload\UploadManager::target_label() ) . '</strong>',
								esc_html( size_format( wp_max_upload_size() ) )
							);
							?>
						</p>
					</td>
				</tr>
				<?php endif; ?>
				<tr id="ms-detected-platform-row" <?php echo ( $platform && '' === $held ) ? '' : 'style="display:none;"'; ?>>
					<th><?php esc_html_e( 'Detected Platform', 'mediashield' ); ?></th>
					<td>
						<strong id="ms-detected-platform-label"><?php echo esc_html( self::get_platform_label( $platform ) ); ?></strong>
						<input type="hidden" id="ms-platform" name="_ms_platform" value="<?php echo esc_attr( $platform ); ?>" />
						<input type="hidden" id="ms-platform-video-id" name="_ms_platform_video_id" value="<?php echo esc_attr( $video_id ); ?>" />
						<?php
						// Detection feedback. Falling back to "Self-hosted" without
						// saying so is how a Bunny dashboard URL got saved as a
						// self-hosted video on 19 of 26 videos on one site: the row
						// above read "Self-hosted", which looks like a successful
						// detection, and the player then hung forever with no error
						// (BC#10225483994). aria-live so the message is announced
						// when it changes, since it appears without user focus.
						?>
						<p id="ms-detect-note" class="description" role="status" aria-live="polite" style="display:none;"></p>
					</td>
				</tr>
				<tr>
					<th><label for="ms-duration"><?php esc_html_e( 'Duration', 'mediashield' ); ?></label></th>
					<td>
						<input type="number" id="ms-duration" name="_ms_duration"
							value="<?php echo esc_attr( $duration ); ?>" class="small-text" min="0"
							autocomplete="off" data-form-type="other" />
						<span class="description"><?php esc_html_e( 'Length in seconds. Used to validate watch progress; leave 0 if unknown.', 'mediashield' ); ?></span>
					</td>
				</tr>
			</table>
		</div>

		<?php
		// Platform auto-detection behavior lives in assets/js/admin-video.js
		// (enqueued by enqueue_edit_assets()).
	}

	/**
	 * A length as m:ss, or h:mm:ss from an hour (the admin's formatDuration()).
	 *
	 * @param int $seconds Seconds.
	 * @return string
	 */
	private static function length_label( int $seconds ): string {
		$h = intdiv( $seconds, 3600 );
		$m = intdiv( $seconds % 3600, 60 );
		$r = $seconds % 60;
		return $h ? sprintf( '%d:%02d:%02d', $h, $m, $r ) : sprintf( '%d:%02d', $m, $r );
	}

	/**
	 * Get human-readable platform label.
	 *
	 * @param string $platform Platform slug.
	 * @return string Label.
	 */
	private static function get_platform_label( string $platform ): string {
		$labels = array(
			'youtube' => __( 'YouTube', 'mediashield' ),
			'vimeo'   => __( 'Vimeo', 'mediashield' ),
			'wistia'  => __( 'Wistia', 'mediashield' ),
			'bunny'   => __( 'Bunny Stream', 'mediashield' ),
			'self'    => __( 'Video file', 'mediashield' ),
		);
		return $labels[ $platform ] ?? $platform;
	}

	/**
	 * Render the Video Settings meta box (protection level, access role).
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render_settings_meta_box( \WP_Post $post ): void {
		// The RAW override, not the resolved value.
		//
		// This read Protection::resolve_level(), which returns the site default
		// when a video has no override of its own. With no "Default" option to
		// return to and a save handler that writes whatever is posted, merely
		// OPENING a video and pressing Update stamped the current default onto
		// it as a permanent per-video override - so the video stopped following
		// Settings > Default Protection Level from then on. That is the ninth
		// creation path of the same bug the other eight were fixed for
		// (BC#10235758016); showing the effective value here is what introduced
		// it.
		$protection = (string) get_post_meta( $post->ID, '_ms_protection_level', true );
		$access     = get_post_meta( $post->ID, '_ms_access_role', true );

		// The one set of level words (Protection::levels(), Basecamp 10545260510).
		$words  = \MediaShield\Player\Protection::levels();
		$levels = wp_list_pluck( $words, 'label' );

		/**
		 * Filter the protection levels offered on the video edit screen.
		 *
		 * Open so an add-on can offer its own level. Free ships none, basic,
		 * standard and strict.
		 *
		 * Adding a level here only makes it selectable and storable. Whatever
		 * the level is supposed to DO still has to be implemented by whoever
		 * adds it - typically via `mediashield_player_type`.
		 *
		 * @since 1.3.0
		 *
		 * @param array<string,string> $levels   Level slug => human label.
		 * @param \WP_Post             $post     Video being edited.
		 * @param string               $selected Currently stored level.
		 */
		$levels = (array) apply_filters( 'mediashield_protection_levels', $levels, $post, (string) $protection );

		// Prepended after the filter so it stays first, and so an add-on cannot
		// remove the only route back to "follow the site default". The label
		// names the current default, because "Default" on its own tells the
		// operator nothing about what they are choosing.
		$levels = array( '' => \MediaShield\Player\Protection::site_default_label() ) + $levels;

		// What the chosen level does, under the select; admin-video.js swaps
		// it when the choice changes.
		$default_level = \MediaShield\Player\Protection::default_level();
		$summaries     = array( '' => $words[ $default_level ]['summary'] ?? '' ) + wp_list_pluck( $words, 'summary' );

		$roles = wp_roles()->get_names();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ms-protection-level"><?php esc_html_e( 'Protection Level', 'mediashield' ); ?></label></th>
				<td>
					<select id="ms-protection-level" name="_ms_protection_level" aria-describedby="ms-protection-summary">
						<?php foreach ( $levels as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" data-summary="<?php echo esc_attr( $summaries[ $value ] ?? '' ); ?>" <?php selected( $protection, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description" id="ms-protection-summary"><?php echo esc_html( $summaries[ $protection ] ?? '' ); ?></p>
					<?php
					// A file on another site cannot be served through MediaShield,
					// so no level can hide its address. Said here, where the owner
					// picks the level, instead of Strict silently not hiding it.
					$ms_platform = (string) get_post_meta( $post->ID, '_ms_platform', true );
					$ms_source   = (string) get_post_meta( $post->ID, '_ms_source_url', true );
					$ms_stream   = (string) get_post_meta( $post->ID, '_ms_stream_url', true );
					if ( 'self' === $ms_platform && '' !== $ms_source && '' === $ms_stream && '' === \MediaShield\REST\StreamController::resolve_file( $post->ID ) ) :
						?>
						<p class="description"><?php esc_html_e( 'This file is on another site, so MediaShield cannot hide its address, even on Strict. The watermark, session tracking and developer-tools detection still work. To hide the address, upload the file to this site.', 'mediashield' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="ms-access-role"><?php esc_html_e( 'Who can watch', 'mediashield' ); ?></label></th>
				<td>
					<select id="ms-access-role" name="_ms_access_role">
						<option value="">
							<?php
							// Login is the site's Who can watch setting, not part of a level.
							echo esc_html(
								\MediaShield\Core\Settings::get( 'ms_require_login' )
									? __( 'Everyone the site allows (Require login is on: logged-in members)', 'mediashield' )
									: __( 'Everyone the site allows (Require login is off: anyone)', 'mediashield' )
							);
							?>
						</option>
						<?php foreach ( $roles as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $access, $slug ); ?>>
								<?php
								/* translators: %s: a user role, e.g. Subscriber. */
								echo esc_html( sprintf( __( 'Only %s', 'mediashield' ), translate_user_role( $name ) ) );
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to the Who can watch settings. */
							esc_html__( 'Require login is set once for the whole site, under %s.', 'mediashield' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=mediashield#/settings/access' ) ) . '">' . esc_html__( 'Settings > Who can watch', 'mediashield' ) . '</a>'
						);
						?>
					</p>
				</td>
			</tr>
			<?php self::render_tags_row( $post->ID ); ?>
		</table>
		<?php
	}

	/**
	 * The Tags row of the Settings box: the video's tags, and a field to add more.
	 *
	 * Tags were managed on MediaShield > Tags but could only be put on a video
	 * through the REST API (Basecamp 10526725213). Saved with the video by
	 * save_tags(), through the same TagManager calls the REST routes use.
	 * Typed names that are new become tags, as in the core Tags box.
	 *
	 * @param int $video_id Video post ID.
	 */
	private static function render_tags_row( int $video_id ): void {
		$assigned = \MediaShield\Tags\TagManager::get_for_video( $video_id );
		// Suggestions only; any name can be typed. Bounded for large tag lists.
		$known = \MediaShield\Tags\TagManager::list( 200, 1 )['items'];
		?>
		<tr>
			<th><label for="ms-add-tags"><?php esc_html_e( 'Tags', 'mediashield' ); ?></label></th>
			<td>
				<input type="hidden" name="_ms_tags_present" value="1" />
				<?php if ( $assigned ) : ?>
					<fieldset class="ms-video-tags">
						<legend class="screen-reader-text"><?php esc_html_e( 'Tags on this video. Untick to remove.', 'mediashield' ); ?></legend>
						<?php foreach ( $assigned as $tag ) : ?>
							<label class="ms-video-tags__tag">
								<input type="checkbox" name="_ms_tag_ids[]" value="<?php echo esc_attr( (string) $tag->id ); ?>" checked />
								<?php echo esc_html( $tag->name ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>
				<input type="text" id="ms-add-tags" name="_ms_add_tags" class="regular-text" list="ms-tag-names" autocomplete="off" />
				<datalist id="ms-tag-names">
					<?php foreach ( $known as $tag ) : ?>
						<option value="<?php echo esc_attr( $tag->name ); ?>"></option>
					<?php endforeach; ?>
				</datalist>
				<p class="description"><?php esc_html_e( 'Add tags, separated by commas. Untick a tag to remove it. Saved with the video.', 'mediashield' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the Tags row: remove unticked tags, add typed ones.
	 *
	 * Only runs when the row was on the form, so a save from anywhere else
	 * (REST, quick edit) never clears a video's tags. Nonce and edit_post are
	 * checked by save_meta_box().
	 *
	 * @param int $video_id Video post ID.
	 */
	private static function save_tags( int $video_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in save_meta_box().
		if ( empty( $_POST['_ms_tags_present'] ) ) {
			return;
		}
		$keep  = array_map( 'absint', (array) wp_unslash( $_POST['_ms_tag_ids'] ?? array() ) );
		$typed = sanitize_text_field( wp_unslash( $_POST['_ms_add_tags'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		foreach ( \MediaShield\Tags\TagManager::get_for_video( $video_id ) as $tag ) {
			if ( ! in_array( (int) $tag->id, $keep, true ) ) {
				\MediaShield\Tags\TagManager::unassign_from_video( $video_id, (int) $tag->id );
			}
		}
		foreach ( explode( ',', $typed ) as $name ) {
			$tag_id = \MediaShield\Tags\TagManager::ensure( $name, get_current_user_id() );
			if ( $tag_id ) {
				\MediaShield\Tags\TagManager::assign_to_video( $video_id, $tag_id, get_current_user_id() );
			}
		}
	}

	/**
	 * Render the Embed This Video meta box (sidebar, top).
	 *
	 * Shows copy-to-clipboard shortcode and Gutenberg block usage.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render_embed_meta_box( \WP_Post $post ): void {
		$post_id   = $post->ID;
		$shortcode = '[mediashield id=' . $post_id . ']';
		$is_new    = 'auto-draft' === $post->post_status;
		?>
		<div class="ms-embed-meta-box">
			<?php if ( $is_new ) : ?>
				<p class="description">
					<?php esc_html_e( 'Save the video first to get the embed codes.', 'mediashield' ); ?>
				</p>
			<?php else : ?>
				<label class="ms-embed-label"><?php esc_html_e( 'Shortcode', 'mediashield' ); ?></label>
				<div class="ms-embed-copy-row">
					<input type="text" value="<?php echo esc_attr( $shortcode ); ?>" readonly class="ms-embed-input" id="ms-shortcode-input" />
					<button type="button" class="button ms-embed-copy-btn" data-copy="ms-shortcode-input" title="<?php esc_attr_e( 'Copy', 'mediashield' ); ?>">
						<?php
						// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Two Icons::svg calls concatenated; each returns inline SVG from a vendored Lucide path map; no user input.
						echo \MediaShield\Support\Icons::svg(
							'clipboard',
							array(
								'size'  => 16,
								'class' => 'ms-copy-idle',
							)
						) . \MediaShield\Support\Icons::svg(
							'check',
							array(
								'size'  => 16,
								'class' => 'ms-copy-done',
							)
						);
						// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</button>
				</div>
				<p class="description"><?php esc_html_e( 'Paste in any page, post, or lesson using the Classic Editor.', 'mediashield' ); ?></p>

				<label class="ms-embed-label" style="margin-top: 14px; display: block;"><?php esc_html_e( 'Gutenberg Block', 'mediashield' ); ?></label>
				<p class="description">
					<?php esc_html_e( 'In the Block Editor, search for "MediaShield" to find the Video block. Select this video from the block settings.', 'mediashield' ); ?>
				</p>

				<label class="ms-embed-label ms-embed-label--spaced"><?php esc_html_e( 'PHP Template', 'mediashield' ); ?></label>
				<div class="ms-embed-copy-row">
					<input type="text" value="<?php echo esc_attr( '<?php echo do_shortcode(\'[mediashield id=' . $post_id . ']\'); ?>' ); ?>" readonly class="ms-embed-input" id="ms-php-input" />
					<button type="button" class="button ms-embed-copy-btn" data-copy="ms-php-input" title="<?php esc_attr_e( 'Copy', 'mediashield' ); ?>">
						<?php
						// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Two Icons::svg calls concatenated; each returns inline SVG from a vendored Lucide path map; no user input.
						echo \MediaShield\Support\Icons::svg(
							'clipboard',
							array(
								'size'  => 16,
								'class' => 'ms-copy-idle',
							)
						) . \MediaShield\Support\Icons::svg(
							'check',
							array(
								'size'  => 16,
								'class' => 'ms-copy-done',
							)
						);
						// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<?php
		// Embed-box styles live in assets/css/admin-video.css; the copy-to-clipboard
		// behavior lives in assets/js/admin-video.js (enqueued by enqueue_edit_assets()).
	}

	/**
	 * Render the Milestones meta box.
	 *
	 * Per-video milestone tag assignments. When a user reaches a milestone,
	 * the tag is stored as serialized user meta linking video ID + tag.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render_milestones_meta_box( \WP_Post $post ): void {
		$milestones = get_post_meta( $post->ID, '_ms_milestone_tags', true );
		if ( ! is_array( $milestones ) ) {
			$milestones = array();
		}

		// The milestones MediaShield records (MilestoneTracker); a 10% row a
		// site filled in before 1.4.4 is still shown so nothing it set is lost.
		$default_pcts = array( 25, 50, 75, 100 );
		if ( ! empty( $milestones[10]['tag'] ) ) {
			array_unshift( $default_pcts, 10 );
		}
		?>
		<p class="description ms-milestones-intro">
			<?php esc_html_e( 'When a member reaches 25%, 50%, 75% or 100% of this video, MediaShield records it. To add a tag to the member at a milestone, type the tag and tick the box.', 'mediashield' ); ?>
		</p>
		<table class="widefat striped ms-milestones-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Milestone', 'mediashield' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Tag to add to the member', 'mediashield' ); ?></th>
					<th scope="col"><?php esc_html_e( 'On', 'mediashield' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $default_pcts as $pct ) :
					$tag     = $milestones[ $pct ]['tag'] ?? '';
					$enabled = ! empty( $milestones[ $pct ]['enabled'] );
					?>
				<tr>
					<td>
						<strong><?php echo esc_html( $pct . '%' ); ?></strong>
					</td>
					<td>
						<input type="text" name="_ms_milestone_tags[<?php echo esc_attr( $pct ); ?>][tag]"
							value="<?php echo esc_attr( $tag ); ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %d: milestone percentage */ __( 'Tag at %d%%', 'mediashield' ), $pct ) ); ?>"
							placeholder="<?php echo esc_attr( sprintf( /* translators: %d: milestone percentage */ __( 'e.g. watched-%d', 'mediashield' ), $pct ) ); ?>"
							class="regular-text" />
					</td>
					<td>
						<input type="checkbox" name="_ms_milestone_tags[<?php echo esc_attr( $pct ); ?>][enabled]"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %d: milestone percentage */ __( 'Add the tag at %d%%', 'mediashield' ), $pct ) ); ?>"
							value="1" <?php checked( $enabled ); ?> />
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">
			<?php esc_html_e( 'The tag goes on the member\'s profile, for your CRM, LMS or automation rules. Tags are listed under MediaShield > Tags.', 'mediashield' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Player Options meta box (sidebar).
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render_player_meta_box( \WP_Post $post ): void {
		$autoplay      = get_post_meta( $post->ID, '_ms_autoplay', true );
		$loop          = get_post_meta( $post->ID, '_ms_loop', true );
		$muted         = get_post_meta( $post->ID, '_ms_muted', true );
		$show_controls = get_post_meta( $post->ID, '_ms_show_controls', true );
		if ( '' === $show_controls ) {
			$show_controls = '1'; // Default to showing controls.
		}
		?>
		<div class="ms-player-options">
			<p class="description" style="margin-bottom: 12px;">
				<?php esc_html_e( 'These options apply to every video source. Two exceptions for Show player controls: a Bunny embed always shows Bunny\'s own controls, and Vimeo hides its controls only on a paid Vimeo plan. Most browsers start a video automatically only when it is muted.', 'mediashield' ); ?>
			</p>
			<p>
				<label>
					<input type="checkbox" name="_ms_autoplay" value="1" <?php checked( $autoplay, '1' ); ?> />
					<?php esc_html_e( 'Autoplay', 'mediashield' ); ?>
				</label>
				<br /><span class="description"><?php esc_html_e( 'Start playing automatically (muted in most browsers).', 'mediashield' ); ?></span>
			</p>
			<p>
				<label>
					<input type="checkbox" name="_ms_loop" value="1" <?php checked( $loop, '1' ); ?> />
					<?php esc_html_e( 'Loop', 'mediashield' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="_ms_muted" value="1" <?php checked( $muted, '1' ); ?> />
					<?php esc_html_e( 'Start muted', 'mediashield' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="_ms_show_controls" value="1" <?php checked( $show_controls, '1' ); ?> />
					<?php esc_html_e( 'Show player controls', 'mediashield' ); ?>
				</label>
			</p>

			<hr style="margin: 16px 0;" />
			<p style="margin-bottom: 8px;"><strong><?php esc_html_e( 'Feature Overrides', 'mediashield' ); ?></strong></p>
			<p class="description" style="margin-bottom: 10px;">
				<?php esc_html_e( 'Override global settings for this video. "Default" uses the value from Settings → Player Controls.', 'mediashield' ); ?>
			</p>
			<?php
			// Labels come from the same class the player reads its overrides
			// from, so a feature cannot appear in one and not the other - which
			// is how Prevent Skipping Ahead ended up as the only player feature
			// with no per-video control.
			$overrides = \MediaShield\Player\FeatureOverrides::labels();
			?>
			<div class="ms-feature-overrides">
			<?php
			foreach ( $overrides as $key => $label ) :
				$val = get_post_meta( $post->ID, $key, true );
				?>
				<label class="ms-feature-overrides__row" for="<?php echo esc_attr( 'ms-override-' . $key ); ?>">
					<span class="ms-feature-overrides__label"><?php echo esc_html( $label ); ?></span>
					<select id="<?php echo esc_attr( 'ms-override-' . $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="ms-feature-overrides__select">
						<option value="" <?php selected( $val, '' ); ?>><?php esc_html_e( 'Default (global)', 'mediashield' ); ?></option>
						<option value="on" <?php selected( $val, 'on' ); ?>><?php esc_html_e( 'On', 'mediashield' ); ?></option>
						<option value="off" <?php selected( $val, 'off' ); ?>><?php esc_html_e( 'Off', 'mediashield' ); ?></option>
					</select>
				</label>
			<?php endforeach; ?>
			</div>

			<?php
			$endscreen_text = get_post_meta( $post->ID, '_ms_player_endscreen_text', true );
			$endscreen_url  = get_post_meta( $post->ID, '_ms_player_endscreen_url', true );
			?>
			<div id="ms-endscreen-fields" style="margin-top: 8px; <?php echo 'on' !== get_post_meta( $post->ID, '_ms_player_endscreen', true ) ? 'display:none;' : ''; ?>">
				<p style="margin: 4px 0;">
					<label><?php esc_html_e( 'End Screen Text:', 'mediashield' ); ?>
						<input type="text" name="_ms_player_endscreen_text" value="<?php echo esc_attr( $endscreen_text ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Leave blank to use global default', 'mediashield' ); ?>" />
					</label>
				</p>
				<p style="margin: 4px 0;">
					<label><?php esc_html_e( 'End Screen URL:', 'mediashield' ); ?>
						<input type="url" name="_ms_player_endscreen_url" value="<?php echo esc_url( $endscreen_url ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Leave blank to use global default', 'mediashield' ); ?>" />
					</label>
				</p>
			</div>

			<?php
			// End-screen field toggle lives in assets/js/admin-video.js.
			?>
		</div>
		<?php
	}

	/**
	 * Render the LMS Integration teaser meta box (Pro upsell).
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render_lms_teaser( \WP_Post $post ): void {
		?>
		<div class="ms-teaser-box">
			<p><strong><?php esc_html_e( 'Link this video to a LearnDash, Tutor LMS, or LifterLMS lesson.', 'mediashield' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Auto-mark lesson complete on video finish', 'mediashield' ); ?></li>
				<li><?php esc_html_e( 'Require course enrollment to watch', 'mediashield' ); ?></li>
				<li><?php esc_html_e( 'Configurable completion threshold', 'mediashield' ); ?></li>
			</ul>
			<a href="https://wbcomdesigns.com/downloads/mediashield-pro/" target="_blank" rel="noopener noreferrer" class="button"><?php esc_html_e( 'Get Pro', 'mediashield' ); ?> &rarr;</a>
		</div>
		<?php
	}

	/**
	 * Render the Pro Features teaser meta box (Pro upsell).
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render_pro_teaser( \WP_Post $post ): void {
		?>
		<div class="ms-teaser-box">
			<ul>
				<li><strong><?php esc_html_e( 'Advanced Watermark', 'mediashield' ); ?></strong> — <?php esc_html_e( '7 fields (email, IP, timestamp...)', 'mediashield' ); ?></li>
				<li><strong><?php esc_html_e( 'Heatmaps', 'mediashield' ); ?></strong> — <?php esc_html_e( 'See where viewers watch', 'mediashield' ); ?></li>
			</ul>
			<a href="https://wbcomdesigns.com/downloads/mediashield-pro/" target="_blank" rel="noopener noreferrer" class="button"><?php esc_html_e( 'Upgrade', 'mediashield' ); ?> &rarr;</a>
		</div>
		<?php
	}

	/**
	 * Set when the edit screen refused a link during this save.
	 *
	 * @var bool
	 */
	private static bool $refused_source = false;

	/**
	 * Refuse a REST save whose new source link MediaShield cannot play.
	 *
	 * Covers every REST writer of `_ms_source_url`: the setup wizard, the
	 * Video block's paste, Pro screens and any API client. A link already
	 * stored is not re-checked when it comes back unchanged, so an old video
	 * can still be edited.
	 *
	 * @since 1.4.4
	 *
	 * @param \stdClass|\WP_Error $prepared Post about to be saved.
	 * @param \WP_REST_Request    $request  Request.
	 * @return \stdClass|\WP_Error
	 */
	public static function refuse_unplayable_source( $prepared, $request ) {
		if ( is_wp_error( $prepared ) || ! $request instanceof \WP_REST_Request ) {
			return $prepared;
		}

		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $meta ) || ! isset( $meta['_ms_source_url'] ) ) {
			return $prepared;
		}

		$url = trim( (string) $meta['_ms_source_url'] );
		$id  = (int) $request->get_param( 'id' );
		if ( '' === $url || ( $id > 0 && get_post_meta( $id, '_ms_source_url', true ) === $url ) ) {
			return $prepared;
		}

		$found = \MediaShield\Support\Platforms::recognise( $url );

		return is_wp_error( $found ) ? $found : $prepared;
	}

	/**
	 * After an edit-screen save that refused a link, say so on the next screen.
	 *
	 * @since 1.4.4
	 *
	 * @param string $location Redirect URL.
	 * @param int    $post_id  Post ID.
	 * @return string
	 */
	public static function flag_refused_source( $location, $post_id ): string {
		if ( self::$refused_source && 'mediashield_video' === get_post_type( (int) $post_id ) ) {
			return add_query_arg( 'ms_source_refused', 1, (string) $location );
		}
		return (string) $location;
	}

	/**
	 * The edit-screen notice for a refused link: what to use instead.
	 *
	 * @since 1.4.4
	 */
	public static function render_refused_source_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only; set by our own redirect.
		if ( empty( $_GET['ms_source_refused'] ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'mediashield_video' !== $screen->post_type ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'The video link was not saved.', 'mediashield' ),
			esc_html( \MediaShield\Support\Platforms::unplayable_message() )
		);
	}

	/**
	 * Re-derive the platform from the stored source URL, server side.
	 *
	 * Whatever the browser guessed, a URL the server recognises is stored in one
	 * canonical shape, so two creation screens can no longer save the same URL
	 * as two different records (BC#10341091646). Only Bunny needs this today:
	 * it is the platform whose URL shapes the screens disagreed on.
	 *
	 * @param int $post_id Video post ID.
	 */
	public static function normalize_platform( int $post_id ): void {
		$source = (string) get_post_meta( $post_id, '_ms_source_url', true );
		$bunny  = \MediaShield\Support\Platforms::bunny_from_url( $source );

		// A YouTube, Vimeo or Wistia link names its platform and id whatever
		// the browser guessed ("iframe", or nothing from an older client).
		$found = '' !== $source ? \MediaShield\Support\Platforms::recognise( $source ) : null;
		if ( is_array( $found ) && in_array( $found['platform'], array( 'youtube', 'vimeo', 'wistia' ), true ) ) {
			update_post_meta( $post_id, '_ms_platform', $found['platform'] );
			update_post_meta( $post_id, '_ms_platform_video_id', $found['video_id'] );
			return;
		}

		if ( ! $bunny ) {
			// Saved as Bunny, but we cannot tell which library it belongs to -
			// a pull-zone file from a library this site is not connected to.
			// Kept as Bunny it would load an .m3u8 inside an iframe and never
			// play; as a direct stream our own player plays it.
			if ( 'bunny' === get_post_meta( $post_id, '_ms_platform', true )
				&& preg_match( '/\.(m3u8|mp4|webm|mov|m4v)(\?|#|$)/i', $source ) ) {
				update_post_meta( $post_id, '_ms_platform', \MediaShield\Support\Platforms::SELF_HOSTED );
				delete_post_meta( $post_id, '_ms_platform_video_id' );
				delete_post_meta( $post_id, '_ms_library_id' );
			}
			return;
		}

		update_post_meta( $post_id, '_ms_platform', 'bunny' );
		update_post_meta( $post_id, '_ms_platform_video_id', $bunny['guid'] );
		update_post_meta( $post_id, '_ms_library_id', $bunny['library'] );
		update_post_meta( $post_id, '_ms_source_url', \MediaShield\Support\Platforms::bunny_embed_url( $bunny['library'], $bunny['guid'] ) );
	}

	/**
	 * Save meta box data on post save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save_meta_box( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['_ms_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ms_meta_nonce'] ) ), 'mediashield_video_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Standard fields.
		$fields = array(
			'_ms_platform'          => 'sanitize_text_field',
			'_ms_platform_video_id' => 'sanitize_text_field',
			'_ms_source_url'        => 'esc_url_raw',
			'_ms_protection_level'  => 'sanitize_text_field',
			'_ms_access_role'       => 'sanitize_text_field',
			'_ms_duration'          => 'absint',
		);

		// Keys where an empty submission means "not set", not "set to empty".
		// Writing '' created a meta row on a video that had none - a save that
		// changed nothing still stamped every field, and for
		// _ms_protection_level that empty row is the difference between
		// following the site default and overriding it forever.
		$unset_when_empty = array(
			'_ms_platform',
			'_ms_platform_video_id',
			'_ms_source_url',
			'_ms_protection_level',
			'_ms_access_role',
		);

		// A length read from an uploaded file is never replaced by an empty or
		// 0 Duration field (a form opened before the upload finished).
		if ( isset( $_POST['_ms_duration'] ) && 0 === absint( wp_unslash( $_POST['_ms_duration'] ) )
			&& (int) get_post_meta( $post_id, '_ms_duration', true ) > 0
			&& '' !== (string) get_post_meta( $post_id, '_ms_owned_file', true ) ) {
			unset( $fields['_ms_duration'] );
		}

		// The edit screen shows an uploaded file by name with an empty link
		// field (render_source_meta_box()); empty there keeps the file.
		if ( ! empty( $_POST['_ms_source_held'] ) && isset( $_POST['_ms_source_url'] ) && '' === trim( sanitize_text_field( wp_unslash( $_POST['_ms_source_url'] ) ) ) ) {
			unset( $fields['_ms_source_url'], $fields['_ms_platform'], $fields['_ms_platform_video_id'] );
		}

		// A link MediaShield cannot play is not saved: the video keeps the
		// source it had, and the screen says what to use instead
		// (flag_refused_source(), Basecamp 10545260436).
		$posted_source = isset( $_POST['_ms_source_url'] ) ? trim( esc_url_raw( wp_unslash( $_POST['_ms_source_url'] ) ) ) : '';
		if ( '' !== $posted_source && is_wp_error( \MediaShield\Support\Platforms::recognise( $posted_source ) )
			&& $posted_source !== (string) get_post_meta( $post_id, '_ms_source_url', true ) ) {
			unset( $fields['_ms_source_url'], $fields['_ms_platform'], $fields['_ms_platform_video_id'] );
			self::$refused_source = true;
		}

		foreach ( $fields as $key => $sanitize ) {
			if ( isset( $_POST[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via $sanitize callback (sanitize_text_field, esc_url_raw, absint).
				$value = $sanitize( wp_unslash( $_POST[ $key ] ) );

				if ( '' === $value && in_array( $key, $unset_when_empty, true ) ) {
					delete_post_meta( $post_id, $key );
					continue;
				}

				update_post_meta( $post_id, $key, $value );
			}
		}

		self::save_tags( $post_id );

		// Player options (checkboxes — absent means unchecked).
		$checkboxes = array( '_ms_autoplay', '_ms_loop', '_ms_muted', '_ms_show_controls' );
		foreach ( $checkboxes as $cb ) {
			update_post_meta( $post_id, $cb, isset( $_POST[ $cb ] ) ? '1' : '0' );
		}

		// Player feature overrides (tri-state: '', 'on', 'off').
		$override_keys = \MediaShield\Player\FeatureOverrides::keys();
		foreach ( $override_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				if ( in_array( $val, array( 'on', 'off' ), true ) ) {
					update_post_meta( $post_id, $key, $val );
				} else {
					delete_post_meta( $post_id, $key ); // '' = use global default.
				}
			}
		}

		// Per-video end screen text/URL.
		if ( isset( $_POST['_ms_player_endscreen_text'] ) ) {
			$text = sanitize_text_field( wp_unslash( $_POST['_ms_player_endscreen_text'] ) );
			if ( ! empty( $text ) ) {
				update_post_meta( $post_id, '_ms_player_endscreen_text', $text );
			} else {
				delete_post_meta( $post_id, '_ms_player_endscreen_text' );
			}
		}
		if ( isset( $_POST['_ms_player_endscreen_url'] ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['_ms_player_endscreen_url'] ) );
			if ( ! empty( $url ) ) {
				update_post_meta( $post_id, '_ms_player_endscreen_url', $url );
			} else {
				delete_post_meta( $post_id, '_ms_player_endscreen_url' );
			}
		}

		// Milestone tags (per-video).
		if ( isset( $_POST['_ms_milestone_tags'] ) && is_array( $_POST['_ms_milestone_tags'] ) ) {
			$milestones = array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per-field below.
			$raw = wp_unslash( $_POST['_ms_milestone_tags'] );
			foreach ( $raw as $pct => $data ) {
				$pct = absint( $pct );
				if ( $pct < 1 || $pct > 100 ) {
					continue;
				}
				$tag = sanitize_text_field( $data['tag'] ?? '' );
				if ( ! empty( $tag ) ) {
					$milestones[ $pct ] = array(
						'tag'     => $tag,
						'enabled' => ! empty( $data['enabled'] ),
					);
				}
			}
			update_post_meta( $post_id, '_ms_milestone_tags', $milestones );
		}
	}

	/**
	 * Meta that leads to the media itself: the file or stream address and the provider id.
	 *
	 * @since 1.4.2
	 */
	public const PROTECTED_META = array( '_ms_source_url', '_ms_platform_video_id', '_ms_stream_url' );

	/**
	 * Keep the video's addresses out of wp/v2 responses for anyone who cannot edit that video.
	 *
	 * The meta carries the source URL and provider id, the very addresses the
	 * player withholds from a viewer who may not watch. Anyone could read them
	 * from /wp/v2/mediashield-videos (1.4.0), and in 1.4.1 anyone who could
	 * write posts still could, for every video: a contributor or a course
	 * instructor read the file address of a paid lesson video
	 * (Basecamp 10482094152). The bar is now the video itself, the same
	 * `edit_post` check WordPress applies to `context=edit`.
	 *
	 * The filter runs for every response shape (`_fields`, `context=edit`,
	 * `_embed`, lists), because WordPress applies `_fields` after it. People
	 * who can write posts keep the rest of the meta (platform, duration), which
	 * the Video block's picker shows; visitors and members get none.
	 *
	 * @since 1.4.1
	 *
	 * @param \WP_REST_Response $response Prepared response.
	 * @param \WP_Post          $post     The video.
	 * @return \WP_REST_Response
	 */
	public static function hide_meta_from_viewers( \WP_REST_Response $response, \WP_Post $post ): \WP_REST_Response {
		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! current_user_can( 'edit_posts' ) ) {
			unset( $data['meta'] );
		} elseif ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$data['meta'] = array_diff_key( $data['meta'], array_flip( self::PROTECTED_META ) );
		}
		$response->set_data( $data );
		return $response;
	}

	/**
	 * Register video post meta fields.
	 */
	public static function register_meta(): void {
		$meta_fields = array(
			'_ms_platform'          => array(
				'type' => 'string',
				// Deliberately '' and not 'self', for the same reason
				// `_ms_protection_level` is '' below: a registered default is
				// returned by get_post_meta() for a row that does not exist, so
				// a non-empty one turns "no platform set" into "self-hosted" at
				// read time. The edit screen then renders that into its hidden
				// field and the save persists it, so merely opening a video and
				// pressing Update marked it self-hosted with no source URL -
				// the exact state that makes the player hang with no error
				// (BC#10225483994). Renderer still coalesces an absent platform
				// to 'self' for rendering; StreamController and Cleanup
				// correctly do nothing when it is unset.
				'default' => '',
			),
			'_ms_platform_video_id' => array(
				'type'    => 'string',
				'default' => '',
			),
			'_ms_source_url'        => array(
				'type'     => 'string',
				'default'  => '',
				// A URL, not text: sanitize_text_field() strips %-encoded
				// octets, so "my%20lesson.mp4" was stored as "mylesson.mp4"
				// (BC#10370649823).
				'sanitize' => 'esc_url_raw',
			),
			'_ms_protection_level'  => array(
				'type'    => 'string',
				// Empty string means "inherit the operator-configured global
				// `ms_default_protection`". `Renderer::render()` and the
				// metabox both perform that fallback. A pre-baked literal
				// default would mask the Settings → Default Protection Level
				// toggle (filed as bug 9857698036).
				'default' => '',
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
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'esc_url_raw',
			),
		);

		foreach ( $meta_fields as $key => $args ) {
			register_post_meta(
				'mediashield_video',
				$key,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => $args['type'],
					'default'           => $args['default'],
					'sanitize_callback' => $args['sanitize'] ?? ( 'string' === $args['type'] ? 'sanitize_text_field' : 'absint' ),
					'auth_callback'     => static function ( $allowed, $meta_key, $object_id ) {
						return current_user_can( 'edit_post', (int) $object_id );
					},
				)
			);
		}

		// Milestone tags are a nested object ({ pct: { enabled, tag } }), so they
		// need a custom REST schema + sanitizer instead of the scalar loop above.
		// Without this the admin SPA cannot read/write milestone tags via REST —
		// only the classic meta box's $_POST path works (card 9909829782).
		register_post_meta(
			'mediashield_video',
			'_ms_milestone_tags',
			array(
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array(
							'type'       => 'object',
							'properties' => array(
								'enabled' => array( 'type' => 'boolean' ),
								'tag'     => array( 'type' => 'string' ),
							),
						),
					),
				),
				'single'            => true,
				'type'              => 'object',
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_milestone_tags' ),
				'auth_callback'     => static function ( $allowed, $meta_key, $object_id ) {
					return current_user_can( 'edit_post', (int) $object_id );
				},
			)
		);
	}

	/**
	 * Sanitize the _ms_milestone_tags object (mirrors save_meta_box()).
	 *
	 * Keeps only percentages 1-100 that have a non-empty tag; coerces enabled to
	 * a boolean. Shared shape between the classic meta box and the REST API.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array<int, array{tag: string, enabled: bool}>
	 */
	public static function sanitize_milestone_tags( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();
		foreach ( $value as $pct => $data ) {
			$pct = absint( $pct );
			if ( $pct < 1 || $pct > 100 || ! is_array( $data ) ) {
				continue;
			}
			$tag = sanitize_text_field( $data['tag'] ?? '' );
			if ( '' !== $tag ) {
				$clean[ $pct ] = array(
					'tag'     => $tag,
					'enabled' => ! empty( $data['enabled'] ),
				);
			}
		}

		return $clean;
	}

	/**
	 * How many videos does one author have in each status?
	 *
	 * The per-author twin of core's wp_count_posts(), for the status links of
	 * a Videos list scoped to its author (Admin\Menu::scope_creator_video_counts()).
	 * Served by the posts table's post_author index.
	 *
	 * @param int $author_id Author user ID.
	 * @return object Status name => count, every registered status present.
	 */
	public static function count_by_author( int $author_id ): object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One grouped count per list screen, as core's wp_count_posts() runs.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s AND post_author = %d GROUP BY post_status",
				'mediashield_video',
				$author_id
			),
			ARRAY_A
		);

		$counts = array_fill_keys( get_post_stati(), 0 );
		foreach ( (array) $rows as $row ) {
			$counts[ $row['post_status'] ] = (int) $row['num_posts'];
		}

		return (object) $counts;
	}
}
