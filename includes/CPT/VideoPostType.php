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
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_mediashield_video', array( __CLASS__, 'save_meta_box' ), 10, 2 );
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
		add_meta_box(
			'mediashield-video-settings',
			__( 'Video Settings', 'mediashield' ),
			array( __CLASS__, 'render_settings_meta_box' ),
			'mediashield_video',
			'normal',
			'high'
		);

		add_meta_box(
			'mediashield-video-source',
			__( 'Video Source', 'mediashield' ),
			array( __CLASS__, 'render_source_meta_box' ),
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
			__( 'Milestone Tags', 'mediashield' ),
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

		// Pro teaser meta boxes (only when Pro is NOT active).
		if ( ! defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
			add_meta_box(
				'mediashield-pro-lms-teaser',
				__( 'LMS Integration', 'mediashield' ) . ' <span class="ms-pro-badge-small">PRO</span>',
				array( __CLASS__, 'render_lms_teaser' ),
				'mediashield_video',
				'side',
				'low'
			);
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
		wp_enqueue_script( 'mediashield-admin-video', MEDIASHIELD_URL . 'assets/js/admin-video.js', array(), MEDIASHIELD_VERSION, true );
		wp_localize_script(
			'mediashield-admin-video',
			'mediashieldVideoAdmin',
			array(
				'labels' => array(
					'youtube' => __( 'YouTube', 'mediashield' ),
					'vimeo'   => __( 'Vimeo', 'mediashield' ),
					'wistia'  => __( 'Wistia', 'mediashield' ),
					'bunny'   => __( 'Bunny Stream', 'mediashield' ),
					'self'    => __( 'Self-hosted / Direct URL', 'mediashield' ),
				),
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
					<?php esc_html_e( 'Paste a video URL from YouTube, Vimeo, Wistia, or Bunny Stream. The platform and video ID will be detected automatically.', 'mediashield' ); ?>
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
						<?php esc_html_e( 'Upgrade to MediaShield Pro to connect cloud platforms (Bunny, YouTube, Vimeo, Wistia) for protected streaming, bulk import, and advanced analytics.', 'mediashield' ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="ms-video-url"><?php esc_html_e( 'Video URL', 'mediashield' ); ?></label></th>
					<td>
						<input type="url" id="ms-video-url" name="_ms_source_url"
							value="<?php echo esc_url( $source ); ?>" class="large-text"
							autocomplete="off"
							data-form-type="other"
							placeholder="<?php esc_attr_e( 'https://www.youtube.com/watch?v=... or https://vimeo.com/...', 'mediashield' ); ?>" />
						<p class="description"><?php esc_html_e( 'Paste the video URL. Supported: YouTube, Vimeo, Wistia, Bunny Stream embed URLs, or a direct video file URL.', 'mediashield' ); ?></p>
					</td>
				</tr>
				<tr id="ms-detected-platform-row" <?php echo $platform ? '' : 'style="display:none;"'; ?>>
					<th><?php esc_html_e( 'Detected Platform', 'mediashield' ); ?></th>
					<td>
						<strong id="ms-detected-platform-label"><?php echo esc_html( self::get_platform_label( $platform ) ); ?></strong>
						<input type="hidden" id="ms-platform" name="_ms_platform" value="<?php echo esc_attr( $platform ); ?>" />
						<input type="hidden" id="ms-platform-video-id" name="_ms_platform_video_id" value="<?php echo esc_attr( $video_id ); ?>" />
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
			'self'    => __( 'Self-hosted', 'mediashield' ),
		);
		return $labels[ $platform ] ?? $platform;
	}

	/**
	 * Render the Video Settings meta box (protection level, access role).
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render_settings_meta_box( \WP_Post $post ): void {
		$protection = get_post_meta( $post->ID, '_ms_protection_level', true ) ?: get_option( 'ms_default_protection', 'standard' );
		$access     = get_post_meta( $post->ID, '_ms_access_role', true );

		$levels = array(
			'none'     => __( 'None', 'mediashield' ),
			'basic'    => __( 'Basic — Login required, right-click disabled', 'mediashield' ),
			'standard' => __( 'Standard — Watermark + session tracking', 'mediashield' ),
			'strict'   => __( 'Strict — Devtools detection + source hiding', 'mediashield' ),
		);

		$roles = wp_roles()->get_names();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ms-protection-level"><?php esc_html_e( 'Protection Level', 'mediashield' ); ?></label></th>
				<td>
					<select id="ms-protection-level" name="_ms_protection_level">
						<?php foreach ( $levels as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $protection, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ms-access-role"><?php esc_html_e( 'Restrict to Role', 'mediashield' ); ?></label></th>
				<td>
					<select id="ms-access-role" name="_ms_access_role">
						<option value=""><?php esc_html_e( '— Any logged-in user —', 'mediashield' ); ?></option>
						<?php foreach ( $roles as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $access, $slug ); ?>>
								<?php echo esc_html( translate_user_role( $name ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Only users with this role can watch. Leave empty for all logged-in users.', 'mediashield' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
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
	 * Render the Milestone Tags meta box.
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

		$default_pcts = array( 10, 25, 50, 75, 100 );
		?>
		<p class="description" style="margin-bottom: 12px;">
			<?php esc_html_e( 'Assign tags to users when they reach specific watch milestones on this video. Tags are stored as user meta and can be used for automation, reporting, or LMS integration.', 'mediashield' ); ?>
		</p>
		<table class="widefat striped" style="border: 1px solid #e2e8f0;">
			<thead>
				<tr>
					<th style="width: 80px;"><?php esc_html_e( 'Milestone', 'mediashield' ); ?></th>
					<th><?php esc_html_e( 'Tag (assigned to user)', 'mediashield' ); ?></th>
					<th style="width: 60px;"><?php esc_html_e( 'Active', 'mediashield' ); ?></th>
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
							aria-label="<?php echo esc_attr( sprintf( /* translators: %d: milestone percentage */ __( 'Tag for %d%% milestone', 'mediashield' ), $pct ) ); ?>"
							placeholder="
							<?php
								/* translators: %d: milestone percentage */
								echo esc_attr( sprintf( __( 'e.g. watched-%d-pct', 'mediashield' ), $pct ) );
							?>
							"
							class="regular-text" style="width: 100%;" />
					</td>
					<td style="text-align: center;">
						<input type="checkbox" name="_ms_milestone_tags[<?php echo esc_attr( $pct ); ?>][enabled]"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %d: milestone percentage */ __( 'Enable %d%% milestone', 'mediashield' ), $pct ) ); ?>"
							value="1" <?php checked( $enabled ); ?> />
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description" style="margin-top: 8px;">
			<?php esc_html_e( 'When a user reaches a milestone, the tag is saved to their profile as: ms_tag_{video_id}_{percentage} → tag_value. Leave blank to use global milestone settings.', 'mediashield' ); ?>
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
				<?php esc_html_e( 'These options apply to self-hosted and Bunny videos. YouTube, Vimeo, and Wistia use their own player controls.', 'mediashield' ); ?>
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
			$overrides = array(
				'_ms_player_speed'     => __( 'Speed Control', 'mediashield' ),
				'_ms_player_keyboard'  => __( 'Keyboard Shortcuts', 'mediashield' ),
				'_ms_player_resume'    => __( 'Resume Playback', 'mediashield' ),
				'_ms_player_sticky'    => __( 'Sticky Player', 'mediashield' ),
				'_ms_player_endscreen' => __( 'End Screen', 'mediashield' ),
			);
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
				<li><strong><?php esc_html_e( 'DRM', 'mediashield' ); ?></strong> — <?php esc_html_e( 'Widevine encryption', 'mediashield' ); ?></li>
				<li><strong><?php esc_html_e( 'Heatmaps', 'mediashield' ); ?></strong> — <?php esc_html_e( 'See where viewers watch', 'mediashield' ); ?></li>
			</ul>
			<a href="https://wbcomdesigns.com/downloads/mediashield-pro/" target="_blank" rel="noopener noreferrer" class="button"><?php esc_html_e( 'Upgrade', 'mediashield' ); ?> &rarr;</a>
		</div>
		<?php
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

		foreach ( $fields as $key => $sanitize ) {
			if ( isset( $_POST[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via $sanitize callback (sanitize_text_field, esc_url_raw, absint).
				update_post_meta( $post_id, $key, $sanitize( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		// Player options (checkboxes — absent means unchecked).
		$checkboxes = array( '_ms_autoplay', '_ms_loop', '_ms_muted', '_ms_show_controls' );
		foreach ( $checkboxes as $cb ) {
			update_post_meta( $post_id, $cb, isset( $_POST[ $cb ] ) ? '1' : '0' );
		}

		// Player feature overrides (tri-state: '', 'on', 'off').
		$override_keys = array( '_ms_player_speed', '_ms_player_keyboard', '_ms_player_resume', '_ms_player_sticky', '_ms_player_endscreen' );
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
				'type'    => 'string',
				'default' => '',
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
					'sanitize_callback' => 'string' === $args['type'] ? 'sanitize_text_field' : 'absint',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
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
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
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
}
