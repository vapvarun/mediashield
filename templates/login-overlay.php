<?php
/**
 * Login overlay template for protected videos.
 *
 * Used as a server-side fallback. The primary login overlay is rendered
 * via JavaScript in player-wrapper.js for dynamic page support.
 *
 * @package MediaShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ms-login-overlay">
	<div class="ms-login-message">
		<p><?php esc_html_e( 'Please log in to watch this video.', 'mediashield' ); ?></p>
		<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="ms-login-button">
			<?php esc_html_e( 'Log In', 'mediashield' ); ?>
		</a>
	</div>
</div>
