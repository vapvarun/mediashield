<?php
/**
 * Shared player renderer — outputs the .ms-protected-player container
 * with .ms-player-target div for JS adapter initialization.
 *
 * Used by Shortcode, Gutenberg block render.php, and single template.
 * No more raw iframes — JS adapters create the player via platform APIs.
 *
 * @package MediaShield\Player
 */

namespace MediaShield\Player;

use MediaShield\Support\Platforms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Access\AccessControl;
use MediaShield\Core\Assets;
use MediaShield\Core\Settings;

/**
 * Class Renderer
 *
 * Shared player renderer for protected video containers.
 *
 * @since 1.0.0
 */
class Renderer {

	/**
	 * The player-sized "This video is unavailable." state for a video that
	 * cannot play here (missing, in the trash, not published, private, no
	 * source). Viewers get the calm state the player shows for a refused
	 * platform video, never a blank area (front-end walk F25); someone who
	 * may edit the video also gets the cause and the one fix.
	 *
	 * @since 1.4.4
	 *
	 * @param string $cause      Editor-only cause, already translated; '' for none.
	 * @param string $fix_url    Where the fix is (Restore, Edit video); '' for none.
	 * @param string $fix_label  Link text for $fix_url.
	 * @param bool   $for_editor Whether the current user may see the cause.
	 * @return string HTML.
	 */
	private static function unavailable( string $cause, string $fix_url, string $fix_label, bool $for_editor ): string {
		wp_enqueue_style( 'mediashield-player' );

		$note = '';
		if ( $for_editor && '' !== $cause ) {
			$note = '<p class="ms-error-admin"><span>' . esc_html( __( 'Only editors see this:', 'mediashield' ) . ' ' . $cause ) . '</span>'
				. ( '' !== $fix_url ? '<a href="' . esc_url( $fix_url ) . '">' . esc_html( $fix_label ) . '</a>' : '' )
				. '</p>';
		}

		return '<div class="ms-player-unavailable"><div class="ms-error-overlay ms-error-overlay--solid" role="status"><div class="ms-error-message">'
			. '<p class="ms-error-title">' . esc_html( Settings::message( 'ms_unavailable_text', __( 'This video is unavailable.', 'mediashield' ) ) ) . '</p>'
			. $note
			. '</div></div></div>';
	}

	/**
	 * Render a video with no protection applied.
	 *
	 * Used when MediaShield is switched off site-wide. The protected player is a
	 * JS-filled shell, and the player JS is not registered while the plugin is
	 * off, so this emits self-sufficient native markup instead: an <iframe> for
	 * platform embeds, a <video> for self-hosted files. No watermark, no session
	 * tracking, no protection overlay — just the video.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $video_id      Video CPT post ID.
	 * @param string $platform      Platform identifier.
	 * @param string $source_url    Source URL.
	 * @param string $stream_url    Stream URL, used when no source URL is stored.
	 * @param string $wrapper_attrs Pre-escaped block wrapper attributes.
	 * @return string Player HTML, or empty string when there is nothing playable.
	 */
	public static function render_unprotected( int $video_id, string $platform, string $source_url, string $stream_url = '', string $wrapper_attrs = '' ): string {
		$url = ! empty( $source_url ) ? $source_url : $stream_url;

		if ( empty( $url ) ) {
			return '';
		}

		if ( 'self' === $platform ) {
			$player = sprintf(
				'<video class="ms-unprotected-player" controls playsinline src="%s"></video>',
				esc_url( $url )
			);
		} else {
			$player = sprintf(
				'<iframe class="ms-unprotected-player" src="%s" frameborder="0" allowfullscreen title="%s"></iframe>',
				esc_url( $url ),
				esc_attr( get_the_title( $video_id ) )
			);
		}

		$html = sprintf(
			'<div class="ms-player-unprotected"%s>%s</div>',
			$wrapper_attrs ? ' ' . $wrapper_attrs : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by get_block_wrapper_attributes().
			$player
		);

		/**
		 * Filter the unprotected player HTML used while MediaShield is switched off.
		 *
		 * @since 1.2.0
		 *
		 * @param string $html     The rendered unprotected player HTML.
		 * @param int    $video_id Video CPT post ID.
		 * @param string $platform Platform identifier.
		 */
		return apply_filters( 'mediashield_unprotected_player_html', $html, $video_id, $platform );
	}

	/**
	 * The direct stream URL a player should load for a video.
	 *
	 * One place for the page render and for /stream/{id}/url, which a player
	 * calls when a signed address expires mid-playback (BC#10497708913).
	 *
	 * @param int    $video_id          Video CPT post ID.
	 * @param string $platform          Platform slug.
	 * @param string $platform_video_id Provider id / GUID.
	 * @param string $stored            Stored `_ms_stream_url`, usually empty.
	 * @return string
	 */
	public static function stream_url( int $video_id, string $platform, string $platform_video_id, string $stored ): string {
		/**
		 * Filter the direct stream URL for a video.
		 *
		 * A stream URL is what lets MediaShield play the video in a real
		 * <video> element instead of an opaque provider iframe, which is the
		 * difference between having a controllable player (watermark, speed,
		 * accurate progress, in-video ad breaks) and not. Platform integrations
		 * that can derive one — Pro knows each Bunny library's pull zone, so it
		 * can build the HLS playlist URL from the video GUID — supply it here
		 * for videos that were imported before the URL was recorded.
		 *
		 * @since 1.2.0
		 *
		 * @param string $stream_url        Stored stream URL, usually empty.
		 * @param int    $video_id          Video CPT post ID.
		 * @param string $platform          Platform slug (self, bunny, youtube…).
		 * @param string $platform_video_id Provider-side video id / GUID.
		 */
		return (string) apply_filters( 'mediashield_video_stream_url', $stored, $video_id, $platform, $platform_video_id );
	}

	/**
	 * A video's player addresses as stored, with the stream URL resolved.
	 *
	 * Step one of the page render; /stream/{id}/url runs the same two steps
	 * (this, then protect_addresses()), so a refreshed address is never one
	 * the page itself would not print.
	 *
	 * @param int $video_id Video CPT post ID.
	 * @return array{platform: string, platform_video_id: string, source_url: string, stream_url: string}
	 */
	public static function addresses( int $video_id ): array {
		$platform_raw      = (string) get_post_meta( $video_id, '_ms_platform', true );
		$platform          = '' !== $platform_raw ? $platform_raw : 'self';
		$platform_video_id = (string) get_post_meta( $video_id, '_ms_platform_video_id', true );

		return array(
			'platform'          => $platform,
			'platform_video_id' => $platform_video_id,
			'source_url'        => (string) get_post_meta( $video_id, '_ms_source_url', true ),
			'stream_url'        => self::stream_url( $video_id, $platform, $platform_video_id, (string) get_post_meta( $video_id, '_ms_stream_url', true ) ),
		);
	}

	/**
	 * Apply the render's protection to addresses() for the current viewer.
	 *
	 * Hide Video Source routing (Protection::filter_player_urls) and the
	 * access decision (withhold_addresses, which asks AccessControl::can_watch:
	 * Require Login, guest rules, roles, domains, LMS enrollment through
	 * mediashield_can_watch). A viewer who may not watch gets every address
	 * blanked and access_denied true.
	 *
	 * @param int   $video_id  Video CPT post ID.
	 * @param array $addresses From addresses().
	 * @return array addresses() plus access_denied.
	 */
	public static function protect_addresses( int $video_id, array $addresses ): array {
		$safe                    = Protection::filter_player_urls( $video_id, $addresses['platform'], $addresses['source_url'], $addresses['stream_url'] );
		$addresses['source_url'] = (string) $safe['source_url'];
		$addresses['stream_url'] = (string) $safe['stream_url'];

		$addresses['access_denied'] = self::withhold_addresses( $video_id, $addresses['platform_video_id'], $addresses['source_url'], $addresses['stream_url'], $addresses['platform'] );

		return $addresses;
	}

	/**
	 * Render the protected player container for a video CPT.
	 *
	 * @param int    $video_id        Video CPT post ID.
	 * @param string $wrapper_attrs   Optional extra wrapper attributes (e.g. from get_block_wrapper_attributes).
	 * @return string HTML output.
	 */
	public static function render( int $video_id, string $wrapper_attrs = '' ): string {
		$video = get_post( $video_id );

		if ( ! $video || 'mediashield_video' !== $video->post_type ) {
			return self::unavailable(
				/* translators: %d: video ID used in the shortcode or block. */
				sprintf( __( 'No video found with ID %d.', 'mediashield' ), $video_id ),
				'',
				'',
				current_user_can( 'edit_posts' )
			);
		}

		if ( 'publish' !== $video->post_status ) {
			$editor = current_user_can( 'edit_post', $video_id );
			if ( 'trash' === $video->post_status ) {
				return self::unavailable(
					__( 'This video is in the trash.', 'mediashield' ),
					current_user_can( 'delete_post', $video_id ) ? wp_nonce_url( admin_url( 'post.php?post=' . $video_id . '&action=untrash' ), 'untrash-post_' . $video_id ) : '',
					__( 'Restore it', 'mediashield' ),
					$editor
				);
			}
			return self::unavailable(
				'private' === $video->post_status
					? __( 'This video is private. Publish it to show it here.', 'mediashield' )
					: __( 'This video is not published yet.', 'mediashield' ),
				(string) get_edit_post_link( $video_id, 'raw' ),
				__( 'Edit video', 'mediashield' ),
				$editor
			);
		}

		$addresses         = self::addresses( $video_id );
		$platform          = $addresses['platform'];
		$platform_video_id = $addresses['platform_video_id'];
		$source_url        = $addresses['source_url'];
		$stream_url        = $addresses['stream_url'];

		// Per-video override, then the owner's global default, then 'standard'.
		// Shared with PlayerWrapper so the auto-wrap path cannot resolve this
		// differently from the shortcode path, which is exactly what it did.
		$protection_level = Protection::resolve_level( $video_id );
		$duration         = (int) get_post_meta( $video_id, '_ms_duration', true );
		$player_type      = apply_filters( 'mediashield_player_type', 'standard', $video_id );

		if ( empty( $source_url ) && empty( $stream_url ) && empty( $platform_video_id ) ) {
			return self::unavailable(
				__( 'This video has no source file or link yet.', 'mediashield' ),
				(string) get_edit_post_link( $video_id, 'raw' ),
				__( 'Edit video', 'mediashield' ),
				current_user_can( 'edit_post', $video_id )
			);
		}

		// MediaShield is switched off site-wide. Assets::register_frontend() bails
		// in that state, so the player JS that fills .ms-player-target never loads
		// and the protected container would render as an empty box. Turning
		// protection off must cost the viewer the protection, not the video.
		if ( ! get_option( 'ms_enabled', true ) ) {
			return self::render_unprotected( $video_id, $platform, $source_url, $stream_url, $wrapper_attrs );
		}

		// Enforce "Hide Video Source URL" server-side. Stripping the URL in JS
		// after the server had already printed it into data-source-url never hid
		// anything (BC#10143668134).
		//
		// Applied AFTER the switched-off bailout above on purpose: that path
		// deliberately hands the viewer the plain URL so the video still plays
		// with protection off, and routing it through the gated /stream/
		// endpoint instead would re-impose a check the operator just disabled.
		$addresses         = self::protect_addresses( $video_id, $addresses );
		$platform_video_id = $addresses['platform_video_id'];
		$source_url        = $addresses['source_url'];
		$stream_url        = $addresses['stream_url'];
		$access_denied     = $addresses['access_denied'];

		// Enqueue frontend assets (only loads when video content exists).
		Assets::enqueue();

		// One implementation of "does this platform need the adaptive player",
		// shared with PlayerWrapper - the two used to carry a copy each.
		if ( Platforms::needs_adaptive_player( (string) $platform ) ) {
			do_action( 'mediashield_needs_shaka' );
		}

		/**
		 * Fires before a protected player is rendered.
		 *
		 * @since 1.1.0
		 *
		 * @param int $video_id Video CPT post ID.
		 */
		do_action( 'mediashield_before_player', $video_id );

		/**
		 * Filter the CSS classes applied to the player container.
		 *
		 * @since 1.1.0
		 *
		 * @param array $classes  Array of CSS class names.
		 * @param int   $video_id Video CPT post ID.
		 */
		$classes = apply_filters( 'mediashield_player_classes', array( 'ms-protected-player' ), $video_id );

		/**
		 * Filter the per-video access type.
		 *
		 * An extension returns a slug (typically the `_ms_access_type` meta value)
		 * so the client can route gated videos to the right overlay
		 * instead of the default login shortcut. Empty string means no special gate.
		 *
		 * @since 1.1.0
		 *
		 * @param string $access_type Access type slug, '' when not gated.
		 * @param int    $video_id    Video CPT post ID.
		 */
		$access_type = (string) apply_filters( 'mediashield_player_access_type', '', $video_id );

		/**
		 * In-video ad breaks (pre / mid / post-roll) for this video. Populated
		 * by the WB Ad Manager bridge; empty when no ad source is active. When
		 * present, the ad-breaks engine is enqueued and the list emitted on the
		 * container for ad-breaks.js to consume.
		 *
		 * @since 1.2.0
		 *
		 * @param array $breaks   List of break descriptors.
		 * @param int   $video_id Video CPT post ID.
		 * @param int   $duration Video duration in seconds.
		 */
		$ad_breaks = apply_filters( 'mediashield_video_ad_breaks', array(), $video_id, (int) $duration );
		if ( ! empty( $ad_breaks ) ) {
			wp_enqueue_script( 'mediashield-ad-breaks' );
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			data-video-id="<?php echo esc_attr( $video_id ); ?>"
			<?php if ( '' !== AccessControl::context_string() ) : ?>
				data-ms-context="<?php echo esc_attr( AccessControl::context_string() ); ?>"
			<?php endif; ?>
			data-platform="<?php echo esc_attr( $platform ); ?>"
			data-protection-level="<?php echo esc_attr( $protection_level ); ?>"
			data-player-type="<?php echo esc_attr( $player_type ); ?>"
			<?php if ( ! empty( $ad_breaks ) ) : ?>
				data-ad-breaks="<?php echo esc_attr( wp_json_encode( $ad_breaks ) ); ?>"
			<?php endif; ?>
			<?php
			if ( '' !== $access_type ) :
				?>
				data-access-type="<?php echo esc_attr( $access_type ); ?>"<?php endif; ?>
			<?php
			echo self::guest_allowed_attr( $video_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute string.
			if ( $access_denied ) {
				echo ' data-access-denied="1"';
				// A member refused this video: the reason and the next step,
				// so the player shows them without asking /session/start
				// (AccessControl::refusal(), the same answer that route gives).
				if ( get_current_user_id() ) {
					$refusal = AccessControl::refusal( AccessControl::can_watch( (int) $video_id, get_current_user_id() ), get_current_user_id() );
					$details = (array) $refusal->get_error_data();
					printf( ' data-denied-code="%s" data-denied-message="%s"', esc_attr( (string) $refusal->get_error_code() ), esc_attr( $refusal->get_error_message() ) );
					if ( ! empty( $details['access_url'] ) ) {
						printf( ' data-access-url="%s" data-access-label="%s"', esc_url( (string) $details['access_url'] ), esc_attr( (string) ( $details['access_label'] ?? '' ) ) );
					}
				}
			}
			// Someone who may edit the video reads the cause of a failure and
			// gets an Edit video link under it (Basecamp 10545260568).
			if ( current_user_can( 'edit_post', $video_id ) ) {
				printf( ' data-edit-url="%s"', esc_url( (string) get_edit_post_link( $video_id, 'raw' ) ) );
			}
			if ( $wrapper_attrs ) {
				echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by get_block_wrapper_attributes().
			}
			?>
		>
			<?php
			// Per-video player feature overrides (tri-state: on/off/empty=global).
			// One map, shared with PlayerWrapper and the edit screen.
			$video_overrides = FeatureOverrides::for_video( (int) $video_id );
			// Per-video end screen text/URL.
			$es_text = get_post_meta( $video_id, '_ms_player_endscreen_text', true );
			$es_url  = get_post_meta( $video_id, '_ms_player_endscreen_url', true );
			if ( ! empty( $es_text ) ) {
				$video_overrides['endscreenText'] = $es_text;
			}
			if ( ! empty( $es_url ) ) {
				$video_overrides['endscreenUrl'] = $es_url;
			}

			// Binary playback options stored on the video CPT. Meta is tri-state:
			// '1' = explicit on, '0' = explicit off, '' = pristine (never saved —
			// treat as adapter default so older videos keep working unchanged).
			$playback_keys  = array(
				'_ms_autoplay'      => 'autoplay',
				'_ms_loop'          => 'loop',
				'_ms_muted'         => 'muted',
				'_ms_show_controls' => 'controls',
			);
			$playback_attrs = '';
			foreach ( $playback_keys as $meta_key => $data_attr ) {
				$raw = (string) get_post_meta( $video_id, $meta_key, true );
				if ( '1' === $raw || '0' === $raw ) {
					$playback_attrs .= sprintf( ' data-%s="%s"', $data_attr, esc_attr( $raw ) );
				}
			}
			?>
			<div class="ms-player-target"
				data-platform-video-id="<?php echo esc_attr( $platform_video_id ); ?>"
				data-source-url="<?php echo esc_url( $source_url ); ?>"
				data-stream-url="<?php echo esc_url( $stream_url ); ?>"
				data-duration="<?php echo esc_attr( $duration ); ?>"
				<?php
				// The video's featured image: shown before a self-hosted video
				// plays, and behind the login message, instead of a black box.
				// Through Thumbnail::poster_url(), so a poster still named after
				// the platform ID never reaches a viewer who may not watch.
				$poster = \MediaShield\CPT\Thumbnail::poster_url( $video_id, 'large', ! $access_denied );
				if ( $poster ) :
					?>
					data-poster="<?php echo esc_url( $poster ); ?>"
				<?php endif; ?>
				<?php echo $playback_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes pre-escaped above. ?>
				<?php
				// Where this member stopped last time, so the player can offer
				// "Resume from m:ss" before any viewing starts: a viewing now
				// starts at the first play, not on page load (Basecamp 10545260660).
				$resume_at = $access_denied ? 0.0 : \MediaShield\Access\SessionManager::resume_position( (int) $video_id, get_current_user_id() );
				// The site's "Watch X% to complete" applies to this member: at
				// None and Basic too, the player measures watch time (cards
				// 10541776164, 10537102216). A lesson's own % arrives as
				// data-watch-required on the container.
				if ( ! $access_denied && get_current_user_id() && self::measures_watch( (int) $video_id ) ) :
					?>
					data-watch-track="1"
					<?php
				endif;
				if ( $resume_at > 0 ) :
					?>
					data-resume-position="<?php echo esc_attr( (string) round( $resume_at, 1 ) ); ?>"
				<?php endif; ?>
				<?php if ( ! empty( $video_overrides ) ) : ?>
					data-player-overrides="<?php echo esc_attr( wp_json_encode( $video_overrides ) ); ?>"
				<?php endif; ?>
			>
			</div>
			<canvas class="ms-watermark-canvas" aria-hidden="true"></canvas>
			<div class="ms-protection-overlay"></div>
			<button class="ms-fullscreen-btn" aria-label="<?php esc_attr_e( 'Fullscreen', 'mediashield' ); ?>" title="<?php esc_attr_e( 'Fullscreen', 'mediashield' ); ?>">
				<?php echo \MediaShield\Support\Icons::svg( 'maximize', array( 'size' => 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static SVG. ?>
			</button>
		</div>
		<?php
		$html = ob_get_clean();

		/**
		 * Filter the complete player HTML output.
		 *
		 * @since 1.1.0
		 *
		 * @param string $html     The rendered player HTML.
		 * @param int    $video_id Video CPT post ID.
		 * @param array  $atts     Player attributes (platform, protection_level, etc.).
		 */
		$html = apply_filters(
			'mediashield_player_html',
			$html,
			$video_id,
			array(
				'platform'         => $platform,
				'protection_level' => $protection_level,
				'player_type'      => $player_type,
			)
		);

		/**
		 * Fires after a protected player is rendered.
		 *
		 * @since 1.1.0
		 *
		 * @param int $video_id Video CPT post ID.
		 */
		do_action( 'mediashield_after_player', $video_id );

		return $html;
	}

	/**
	 * Decide which of a video's addresses the page may carry for the current viewer.
	 *
	 * The one place both renderers (Renderer and PlayerWrapper: shortcode,
	 * block, playlist page, playlist player route, embed page, auto-wrapped
	 * embeds) ask before printing an address.
	 *
	 * Always, for every viewer: a self-hosted video's provider id is blanked.
	 * For `self` it is the obscured file name in uploads/mediashield/, and on a
	 * host that does not honour the folder's deny rule (nginx) that name is a
	 * permanent download link, the very thing the random name exists to keep
	 * private (Basecamp 10482094152). The player never needs it: it streams
	 * self-hosted video from the signed /stream URL and reads the id only for
	 * YouTube, Vimeo and Wistia. A raw URL into that folder is blanked for the
	 * same reason; playback goes through /stream.
	 *
	 * For a viewer who may not watch: every address is blanked, not the source
	 * URL, not the provider id (a Bunny embed or an unlisted Vimeo id plays on
	 * its own), not even our signed /stream address. The player asks
	 * /session/start for the reason and shows the login or access message.
	 *
	 * @since 1.4.1
	 * @since 1.4.2 Blanks the self-hosted file name and protected-folder URLs for everyone.
	 *
	 * @param int    $video_id          Video CPT post ID.
	 * @param string $platform_video_id Provider id, blanked in place when it may not be shown.
	 * @param string $source_url        Source URL, blanked in place when it may not be shown.
	 * @param string $stream_url        Stream URL, blanked in place when denied.
	 * @param string $platform          Platform slug; read from the video when ''.
	 * @return bool True when the viewer may not watch; print data-access-denied.
	 */
	public static function withhold_addresses( int $video_id, &$platform_video_id, &$source_url, &$stream_url, string $platform = '' ): bool {
		if ( '' === $platform ) {
			$platform = (string) get_post_meta( $video_id, '_ms_platform', true );
		}
		if ( '' === $platform || 'self' === $platform ) {
			$platform_video_id = '';
		}
		foreach ( array( &$source_url, &$stream_url ) as &$url ) {
			if ( Protection::in_protected_folder( (string) $url ) ) {
				$url = '';
			}
		}
		unset( $url );

		if ( AccessControl::can_watch( $video_id, get_current_user_id() )['allowed'] ) {
			return false;
		}
		$platform_video_id = '';
		$source_url        = '';
		$stream_url        = '';
		return true;
	}

	/**
	 * Does the site's "Watch X% to complete" apply to this player for the member?
	 *
	 * Only where a lesson can use it: the site % is about lessons, and the
	 * player cannot tell a lesson from a blog post, so an LMS integration
	 * says so through `mediashield_measure_watch` (Pro's LearnDash: its lesson
	 * and topic screens). Learnomy 2.4.1 lessons put their own % on the
	 * player instead (data-watch-required). Free review #8.
	 *
	 * @since 1.4.4
	 *
	 * @param int $video_id Video CPT post ID.
	 * @return bool
	 */
	private static function measures_watch( int $video_id ): bool {
		if ( null === \MediaShield\Access\SessionManager::watch_requirement( $video_id, get_current_user_id() ) ) {
			return false;
		}

		/**
		 * Filter whether this player measures the member's watch time for the
		 * site's "Watch X% to complete" (Settings > Lesson Completion).
		 *
		 * Default false: a video on a blog post or a landing page is not a
		 * lesson. Return true on the screens where a lesson's completion uses
		 * the rule. Standard and Strict videos measure anyway; this decides
		 * for None and Basic.
		 *
		 * @since 1.4.4
		 *
		 * @param bool $measure  Default false.
		 * @param int  $video_id Video CPT post ID.
		 * @param int  $post_id  The post or page being shown (0 when none).
		 */
		return (bool) apply_filters( 'mediashield_measure_watch', false, $video_id, (int) get_queried_object_id() );
	}

	/**
	 * Does the player tell the script that this guest may skip the login overlay?
	 *
	 * Returns ` data-guest-allowed="1"` for a logged-out visitor the login gate
	 * does not apply to (Require Login off, or mediashield_guest_can_watch said
	 * yes for this video), so the script asks /session/start instead of
	 * painting the overlay. Shared with PlayerWrapper so both render paths
	 * agree with AccessControl::login_gate_applies().
	 *
	 * @since 1.4.1
	 *
	 * @param int $video_id Video CPT post ID.
	 * @return string Attribute string with a leading space, or ''.
	 */
	public static function guest_allowed_attr( int $video_id ): string {
		if ( get_current_user_id() || AccessControl::login_gate_applies( $video_id, 0 ) ) {
			return '';
		}
		return ' data-guest-allowed="1"';
	}
}
