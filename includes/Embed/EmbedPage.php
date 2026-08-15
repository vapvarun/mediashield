<?php
/**
 * The standalone player page a signed embed link resolves to.
 *
 * WHY THIS EXISTS
 *
 * MediaShield could be embedded three ways — shortcode, block, PHP call — and
 * every one of them requires you to be rendering a WordPress page in PHP. A
 * native app cannot do any of them, so a MediaShield video reached the mobile
 * app as a bare post ID with nothing able to resolve it, and the lesson showed
 * an empty media area (Basecamp 10199485635).
 *
 * H5P solved the same problem years ago by serving its own self-contained embed
 * page, and the app frames it without ever learning H5P's internals. This is
 * that, for MediaShield: our player, our protection, our page. The consumer
 * gets a URL and nothing else.
 *
 * Rendered on `template_redirect` as a real front-end request rather than
 * through admin-ajax, because the player's own scripts and styles enqueue on
 * `wp_enqueue_scripts`. Serving it from admin-ajax would hand back markup with
 * no runtime attached — a player that renders and cannot play.
 *
 * @package MediaShield
 * @since   1.3.0
 */

namespace MediaShield\Embed;

use MediaShield\Access\AccessControl;
use MediaShield\Player\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Serves the embed page.
 */
class EmbedPage {

	/**
	 * Hook up.
	 */
	public static function register(): void {
		// Priority 1: ahead of anything that might redirect or 404 first. This
		// request is not a normal page view and should never reach the theme.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 1 );
	}

	/**
	 * Render the page when the request carries a token.
	 */
	public static function maybe_render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the signed token IS the credential; verified below.
		$token = isset( $_GET[ EmbedLink::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ EmbedLink::QUERY_VAR ] ) ) : '';
		if ( '' === $token ) {
			return;
		}

		$claim = EmbedLink::verify( $token );
		if ( null === $claim ) {
			self::refuse(
				__( 'This video link has expired.', 'mediashield' ),
				__( 'Open the lesson again to get a fresh one.', 'mediashield' ),
				410
			);
		}

		// The token says who it was minted for; this says whether they may
		// still watch. A revoked viewer holding a link that has not yet expired
		// is refused here, which is the whole reason the check is repeated
		// rather than trusted from minting time.
		$access = AccessControl::can_watch( $claim['video_id'], $claim['user_id'] );
		if ( empty( $access['allowed'] ) ) {
			self::refuse(
				__( 'This video is not available to you.', 'mediashield' ),
				(string) ( $access['reason'] ?? '' ),
				403
			);
		}

		// No admin bar. This frame is the video and nothing else: an operator
		// watching their own course would otherwise get 32px of WordPress
		// chrome inside the app, with links that lead out of it.
		add_filter( 'show_admin_bar', '__return_false' );

		$player = Renderer::render( $claim['video_id'] );
		if ( '' === $player ) {
			self::refuse(
				__( 'This video is no longer available.', 'mediashield' ),
				__( 'It may have been removed since the lesson was published.', 'mediashield' ),
				404
			);
		}

		self::send( $claim['video_id'], $player );
	}

	/**
	 * Output the page and stop.
	 *
	 * @param int    $video_id Video being shown.
	 * @param string $player   Player markup from the renderer.
	 */
	private static function send( int $video_id, string $player ): void {
		// Never cached by a proxy: the URL is per-viewer and short-lived, and a
		// shared cache would serve one person's link to the next.
		nocache_headers();
		status_header( 200 );

		// Same-origin framing only. The app loads this in its own web view,
		// which is same-origin to the site it was given; a third-party site
		// embedding it would be a way around the protection this plugin exists
		// to provide.
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( "Content-Security-Policy: frame-ancestors 'self'" );

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
			<meta name="robots" content="noindex, nofollow">
			<title><?php echo esc_html( get_the_title( $video_id ) ); ?></title>
			<?php
			// wp_head() so the player's registered scripts and styles load the
			// same way they do on any page. Renderer::render() has already run
			// Assets::enqueue(), so the runtime is queued by this point.
			wp_head();
			?>
			<style>
				/* The frame IS the player. No theme, no chrome, no scroll: the
				   app supplies everything around this. */
				html, body { margin: 0; padding: 0; background: #000; height: 100%; overflow: hidden; }
				.ms-embed-stage { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; }
				.ms-embed-stage > * { width: 100%; }
				.ms-embed-message { color: #fff; font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; text-align: center; padding: 24px; }
				.ms-embed-message p { margin: 0 0 8px; }
				.ms-embed-message .ms-embed-message__detail { opacity: .7; font-size: 14px; }
			</style>
		</head>
		<body <?php body_class( 'ms-embed' ); ?>>
			<div class="ms-embed-stage">
				<?php
				// Trusted, pre-escaped player markup from the renderer — the
				// same output the shortcode returns on any page.
				echo $player; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Say plainly why there is no video, in the frame, and stop.
	 *
	 * A blank frame is what this whole card was about. A refusal that reads as
	 * a sentence is recoverable; a black rectangle is not.
	 *
	 * @param string $title  Headline.
	 * @param string $detail Optional second line.
	 * @param int    $status HTTP status.
	 */
	private static function refuse( string $title, string $detail, int $status ): void {
		nocache_headers();
		status_header( $status );
		header( 'X-Frame-Options: SAMEORIGIN' );

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<meta name="robots" content="noindex, nofollow">
			<title><?php echo esc_html( $title ); ?></title>
			<style>
				html, body { margin: 0; padding: 0; background: #000; height: 100%; }
				.ms-embed-stage { display: flex; align-items: center; justify-content: center; height: 100%; }
				.ms-embed-message { color: #fff; font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; text-align: center; padding: 24px; }
				.ms-embed-message p { margin: 0 0 8px; }
				.ms-embed-message .ms-embed-message__detail { opacity: .7; font-size: 14px; }
			</style>
		</head>
		<body>
			<div class="ms-embed-stage">
				<div class="ms-embed-message">
					<p><?php echo esc_html( $title ); ?></p>
					<?php if ( '' !== $detail ) : ?>
						<p class="ms-embed-message__detail"><?php echo esc_html( $detail ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</body>
		</html>
		<?php
		exit;
	}
}
