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
			<?php if ( $is_new ) : ?>
				<p class="ms-source-intro">
					<?php esc_html_e( 'Paste a video URL from YouTube, Vimeo, Wistia, or Bunny Stream. The platform and video ID will be detected automatically.', 'mediashield' ); ?>
				</p>

				<?php if ( $has_pro && ! empty( $connected_platforms ) ) : ?>
					<p class="ms-source-intro" style="color: #2271b1;">
						<span class="dashicons dashicons-yes-alt" style="margin-right: 4px;"></span>
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
							<span class="dashicons dashicons-cloud" style="margin-right: 4px;"></span>
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
						<span class="dashicons dashicons-lock" style="margin-right: 4px;"></span>
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
							value="<?php echo esc_attr( $duration ); ?>" class="small-text" min="0" />
						<span class="description"><?php esc_html_e( 'seconds (auto-filled on import)', 'mediashield' ); ?></span>
					</td>
				</tr>
			</table>
		</div>

		<script>
		(function() {
			var urlField = document.getElementById('ms-video-url');
			var platformField = document.getElementById('ms-platform');
			var videoIdField = document.getElementById('ms-platform-video-id');
			var platformRow = document.getElementById('ms-detected-platform-row');
			var platformLabel = document.getElementById('ms-detected-platform-label');

			var patterns = {
				youtube: [
					/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
				],
				vimeo: [
					/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/,
				],
				wistia: [
					/(?:wistia\.com\/medias\/|fast\.wistia\.net\/embed\/iframe\/)([a-z0-9]+)/,
				],
				bunny: [
					/(?:iframe\.mediadelivery\.net\/embed\/\d+\/)([a-f0-9-]+)/,
					/(?:b-cdn\.net\/)([a-f0-9-]+)/,
				],
			};

			var labels = {
				youtube: <?php echo wp_json_encode( __( 'YouTube', 'mediashield' ) ); ?>,
				vimeo: <?php echo wp_json_encode( __( 'Vimeo', 'mediashield' ) ); ?>,
				wistia: <?php echo wp_json_encode( __( 'Wistia', 'mediashield' ) ); ?>,
				bunny: <?php echo wp_json_encode( __( 'Bunny Stream', 'mediashield' ) ); ?>,
				self: <?php echo wp_json_encode( __( 'Self-hosted / Direct URL', 'mediashield' ) ); ?>,
			};

			if (urlField) {
				urlField.addEventListener('input', function() {
					var url = this.value.trim();
					var detected = 'self';
					var vid = '';

					for (var p in patterns) {
						for (var i = 0; i < patterns[p].length; i++) {
							var m = url.match(patterns[p][i]);
							if (m) { detected = p; vid = m[1]; break; }
						}
						if (vid) break;
					}

					platformField.value = detected;
					videoIdField.value = vid;
					platformLabel.textContent = labels[detected] + (vid ? ' (' + vid + ')' : '');
					platformRow.style.display = url ? '' : 'none';
				});
			}
		})();
		</script>
		<?php
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
					<input type="text" value="<?php echo esc_attr( $shortcode ); ?>" readonly class="ms-embed-input" id="ms-shortcode-input" onclick="this.select();" />
					<button type="button" class="button ms-embed-copy-btn" data-copy="ms-shortcode-input" title="<?php esc_attr_e( 'Copy', 'mediashield' ); ?>">
						<span class="dashicons dashicons-clipboard"></span>
					</button>
				</div>
				<p class="description"><?php esc_html_e( 'Paste in any page, post, or lesson using the Classic Editor.', 'mediashield' ); ?></p>

				<label class="ms-embed-label" style="margin-top: 14px; display: block;"><?php esc_html_e( 'Gutenberg Block', 'mediashield' ); ?></label>
				<p class="description">
					<?php esc_html_e( 'In the Block Editor, search for "MediaShield" to find the Video block. Select this video from the block settings.', 'mediashield' ); ?>
				</p>

				<label class="ms-embed-label" style="margin-top: 14px; display: block;"><?php esc_html_e( 'PHP Template', 'mediashield' ); ?></label>
				<div class="ms-embed-copy-row">
					<input type="text" value="<?php echo esc_attr( '<?php echo do_shortcode(\'[mediashield id=' . $post_id . ']\'); ?>' ); ?>" readonly class="ms-embed-input" id="ms-php-input" onclick="this.select();" />
					<button type="button" class="button ms-embed-copy-btn" data-copy="ms-php-input" title="<?php esc_attr_e( 'Copy', 'mediashield' ); ?>">
						<span class="dashicons dashicons-clipboard"></span>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<style>
			.ms-embed-meta-box { padding: 2px 0; }
			.ms-embed-label { display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px; color: #1e1e1e; text-transform: uppercase; letter-spacing: 0.03em; }
			.ms-embed-copy-row { display: flex; gap: 4px; margin-bottom: 4px; }
			.ms-embed-input { flex: 1; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; padding: 4px 8px; background: #f7f7f5; border: 1px solid #e2e8f0; border-radius: 4px; color: #1e1e1e; }
			.ms-embed-copy-btn { padding: 0 6px !important; min-width: 32px; }
			.ms-embed-copy-btn .dashicons { font-size: 16px; width: 16px; height: 16px; margin-top: 2px; }
			.ms-embed-copy-btn.copied { color: #2ea44f; }
		</style>

		<script>
		(function() {
			document.querySelectorAll('.ms-embed-copy-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var input = document.getElementById(this.dataset.copy);
					if (input) {
						input.select();
						navigator.clipboard.writeText(input.value).then(function() {
							btn.classList.add('copied');
							var icon = btn.querySelector('.dashicons');
							if (icon) { icon.classList.replace('dashicons-clipboard', 'dashicons-yes'); }
							setTimeout(function() {
								btn.classList.remove('copied');
								if (icon) { icon.classList.replace('dashicons-yes', 'dashicons-clipboard'); }
							}, 2000);
						});
					}
				});
			});
		})();
		</script>
		<?php
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
			foreach ( $overrides as $key => $label ) :
				$val = get_post_meta( $post->ID, $key, true );
				?>
				<p style="margin: 4px 0;">
					<label><?php echo esc_html( $label ); ?>:
						<select name="<?php echo esc_attr( $key ); ?>" style="margin-left: 4px;">
							<option value="" <?php selected( $val, '' ); ?>><?php esc_html_e( 'Default (global)', 'mediashield' ); ?></option>
							<option value="on" <?php selected( $val, 'on' ); ?>><?php esc_html_e( 'On', 'mediashield' ); ?></option>
							<option value="off" <?php selected( $val, 'off' ); ?>><?php esc_html_e( 'Off', 'mediashield' ); ?></option>
						</select>
					</label>
				</p>
			<?php endforeach; ?>

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

			<script>
			(function(){
				var sel = document.querySelector('select[name="_ms_player_endscreen"]');
				var fields = document.getElementById('ms-endscreen-fields');
				if (sel && fields) {
					sel.addEventListener('change', function(){ fields.style.display = this.value === 'on' ? '' : 'none'; });
				}
			})();
			</script>
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
				<li><strong><?php esc_html_e( 'Email Gate', 'mediashield' ); ?></strong> — <?php esc_html_e( 'Require email before watching', 'mediashield' ); ?></li>
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
	}
}
