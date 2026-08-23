<?php
/**
 * Uninstall handler — runs when the plugin is deleted via WP admin.
 *
 * @package MediaShield
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop all free tables.
$tables = array(
	'ms_video_tags',
	'ms_playlist_items',
	'ms_milestones',
	'ms_watch_sessions_archive',
	'ms_watch_sessions',
	'ms_tags',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall drop tables.
}

// Delete free plugin options — derived from the settings schema, never a
// hand-maintained copy. The old static list drifted 14 options behind
// Settings::schema() (every player-control and ad option added since 1.0 was
// orphaned on uninstall), and because seed_defaults() skips existing keys, a
// reinstall then resurrected the stale config instead of resetting. Deriving
// the list from the single source of truth makes that class of drift
// impossible. Scoped to Settings::schema() so it can only ever match
// free-owned keys — never Pro's, which matters when Pro is deactivated but
// still installed during a Free uninstall.
if ( ! defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
	// The plugin is not bootstrapped during uninstall, so pull the autoloader
	// in directly to reach Core\Settings.
	$ms_autoload = __DIR__ . '/vendor/autoload.php';
	if ( is_readable( $ms_autoload ) ) {
		require_once $ms_autoload;
	}

	$free_options = class_exists( '\\MediaShield\\Core\\Settings' )
		? array_keys( \MediaShield\Core\Settings::schema() )
		: array();

	// Free-owned runtime/state options that live outside the settings schema,
	// plus keys removed from the schema in past releases (kept so upgraded
	// installs still get the stale row cleaned up).
	$free_options = array_merge(
		$free_options,
		array(
			'ms_db_version',
			'ms_wizard_completed',
			'ms_activated_at',
			'ms_custom_url_patterns', // Removed in 1.3.0.
			'ms_max_upload_size',     // Removed in 1.3.0.
		)
	);

	foreach ( array_unique( $free_options ) as $option ) {
		delete_option( $option );
	}
}

// Remove custom capability from all roles.
global $wp_roles;
if ( isset( $wp_roles ) ) {
	foreach ( $wp_roles->roles as $role_name => $role_info ) {
		$wp_role = get_role( $role_name );
		if ( $wp_role ) {
			$wp_role->remove_cap( 'upload_mediashield' );
		}
	}
}

// Delete all video and playlist CPT posts.
$ms_post_types = array( 'mediashield_video', 'mediashield_playlist' );
foreach ( $ms_post_types as $ms_cpt ) {
	$ms_posts = get_posts(
		array(
			'post_type'      => $ms_cpt,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	foreach ( $ms_posts as $ms_post_id ) {
		wp_delete_post( $ms_post_id, true );
	}
}

// Clean up transients (skip if Pro is active to avoid destroying Pro transients).
if ( ! defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ms\_%' OR option_name LIKE '_transient_timeout_ms\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Uninstall cleanup, static LIKE pattern.
}
