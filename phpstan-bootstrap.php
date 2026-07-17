<?php
/**
 * PHPStan bootstrap file for MediaShield.
 *
 * Defines plugin constants so PHPStan can analyse without WordPress loaded.
 */

// MediaShield constants.
define( 'MEDIASHIELD_VERSION', '1.2.0' );
define( 'MEDIASHIELD_DB_VERSION', 1 );
define( 'MEDIASHIELD_FILE', __DIR__ . '/mediashield.php' );
define( 'MEDIASHIELD_PATH', __DIR__ . '/' );
define( 'MEDIASHIELD_URL', 'https://example.com/wp-content/plugins/mediashield/' );

// WordPress constants PHPStan may need.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WP core constants for static analysis.
define( 'ABSPATH', '/tmp/wordpress/' );
define( 'WPINC', 'wp-includes' );
define( 'WP_CONTENT_DIR', '/tmp/wordpress/wp-content' );
define( 'WP_PLUGIN_DIR', '/tmp/wordpress/wp-content/plugins' );
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

// Action Scheduler function stubs — required at runtime via the vendored
// woocommerce/action-scheduler library, but not declared anywhere PHPStan
// can statically discover. Mirror only the signatures we call from this
// plugin so PHPStan can typecheck the call sites in Cron\Cleanup.
if ( ! function_exists( 'as_has_scheduled_action' ) ) {
	/**
	 * @param string              $hook
	 * @param array<mixed>|null   $args
	 * @param string              $group
	 * @return int|false
	 */
	function as_has_scheduled_action( string $hook, ?array $args = null, string $group = '' ) {
		return false;
	}
}
if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
	/**
	 * @param int                 $timestamp
	 * @param int                 $interval_in_seconds
	 * @param string              $hook
	 * @param array<mixed>        $args
	 * @param string              $group
	 * @return int
	 */
	function as_schedule_recurring_action( int $timestamp, int $interval_in_seconds, string $hook, array $args = array(), string $group = '' ): int {
		return 0;
	}
}
if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	/**
	 * @param string              $hook
	 * @param array<mixed>        $args
	 * @param string              $group
	 * @return int
	 */
	function as_enqueue_async_action( string $hook, array $args = array(), string $group = '' ): int {
		return 0;
	}
}
