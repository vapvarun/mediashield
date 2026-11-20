<?php
/**
 * One notice offering the 1.4.3 defaults to sites that kept the old ones.
 *
 * @package MediaShield\Admin
 */

namespace MediaShield\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Offer the 1.4.3 defaults to a site still on the old ones, once.
 *
 * 1.4.2 stored its defaults on activation, so after an update the stored
 * values win and the new defaults (badge off; watermark name + email, IP
 * opt-in) never reach existing sites. Nothing is changed silently: an
 * administrator sees one calm notice on MediaShield screens with Apply new
 * defaults / Keep my settings; either closes it for good. Option
 * `ms_new_defaults_decided` is 'pending' on a site that updated (Migrator
 * v12), then 'apply' or 'keep'; a fresh install never gets 'pending'. "Not now" hides it for that administrator for
 * a week. Shown only while a stored value still differs from the new
 * default, so a fresh install never sees it (owner decision on Basecamp
 * 10530930695 / 10530931001).
 *
 * Each difference is an item from `mediashield_new_defaults`: Free adds the
 * badge, Pro its watermark fields.
 *
 * @since 1.4.3
 */
class NewDefaultsNotice {

	/**
	 * Site option: 'pending' after an update, then 'apply' or 'keep'.
	 */
	public const DECIDED = 'ms_new_defaults_decided';

	/**
	 * Per-user snooze (timestamp until which the notice stays hidden).
	 */
	public const SNOOZE = '_ms_new_defaults_snooze';

	/**
	 * Hook up.
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_mediashield_new_defaults', array( __CLASS__, 'handle' ) );
	}

	/**
	 * The differences still to offer: [ key => [ 'label' => string, 'apply' => callable ] ].
	 *
	 * @return array<string, array{label: string, apply: callable}>
	 */
	public static function changes(): array {
		$changes = array();
		if ( (bool) get_option( 'ms_show_badge' ) ) {
			$changes['badge'] = array(
				'label' => __( 'The "Protected by MediaShield" badge is off.', 'mediashield' ),
				'apply' => static function () {
					update_option( 'ms_show_badge', false );
				},
			);
		}

		/**
		 * Stored settings that still differ from the 1.4.3 defaults.
		 *
		 * @since 1.4.3
		 *
		 * @param array $changes [ key => [ 'label' => sentence, 'apply' => callable ] ].
		 */
		$changes = (array) apply_filters( 'mediashield_new_defaults', $changes );

		return array_filter(
			$changes,
			static function ( $change ) {
				return is_array( $change ) && ! empty( $change['label'] ) && isset( $change['apply'] ) && is_callable( $change['apply'] );
			}
		);
	}

	/**
	 * Should the current administrator see it on this screen?
	 *
	 * @return bool
	 */
	public static function should_show(): bool {
		if ( ! current_user_can( 'manage_options' ) || 'pending' !== get_option( self::DECIDED ) ) {
			return false;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'mediashield' ) ) {
			return false;
		}
		if ( (int) get_user_meta( get_current_user_id(), self::SNOOZE, true ) > time() ) {
			return false;
		}
		return (bool) self::changes();
	}

	/**
	 * Print the notice.
	 */
	public static function render(): void {
		if ( ! self::should_show() ) {
			return;
		}
		$action = admin_url( 'admin-post.php' );
		?>
		<div class="notice notice-info ms-notice mediashield-new-defaults">
			<p><strong><?php esc_html_e( 'MediaShield has new defaults', 'mediashield' ); ?></strong></p>
			<ul class="ul-disc">
				<?php foreach ( self::changes() as $change ) : ?>
					<li><?php echo esc_html( $change['label'] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><?php esc_html_e( 'Your site still uses the earlier settings. Apply the new defaults, or keep your current settings. Nothing changes until you choose.', 'mediashield' ); ?></p>
			<form method="post" action="<?php echo esc_url( $action ); ?>">
				<input type="hidden" name="action" value="mediashield_new_defaults" />
				<?php wp_nonce_field( 'mediashield_new_defaults' ); ?>
				<p>
					<button type="submit" name="choice" value="apply" class="button button-primary"><?php esc_html_e( 'Apply new defaults', 'mediashield' ); ?></button>
					<button type="submit" name="choice" value="keep" class="button"><?php esc_html_e( 'Keep my settings', 'mediashield' ); ?></button>
					<button type="submit" name="choice" value="later" class="button-link"><?php esc_html_e( 'Not now', 'mediashield' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Apply, keep or snooze, then go back.
	 */
	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change these settings.', 'mediashield' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mediashield_new_defaults' );
		$choice = isset( $_POST['choice'] ) ? sanitize_key( wp_unslash( $_POST['choice'] ) ) : '';
		self::decide( $choice );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=mediashield' ) );
		exit;
	}

	/**
	 * Carry out a choice.
	 *
	 * @param string $choice 'apply', 'keep' or 'later'.
	 */
	public static function decide( string $choice ): void {
		if ( 'later' === $choice ) {
			update_user_meta( get_current_user_id(), self::SNOOZE, time() + WEEK_IN_SECONDS );
			return;
		}
		if ( 'apply' === $choice ) {
			foreach ( self::changes() as $change ) {
				call_user_func( $change['apply'] );
			}
		}
		if ( in_array( $choice, array( 'apply', 'keep' ), true ) ) {
			update_option( self::DECIDED, $choice, false );
		}
	}
}
