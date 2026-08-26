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

// Drop all free tables - but not while Pro is still installed.
//
// Everything else below already skips when MEDIASHIELD_PRO_VERSION is defined,
// so that removing Free does not gut a site still running Pro. This did not,
// which made the protection incoherent: the settings and the video records
// were carefully preserved while every watch session, milestone and tag - the
// analytics history, the part an owner genuinely cannot rebuild - was dropped.
// Pro reads these tables, so it was also the destruction most likely to break
// the plugin that was being protected.
if ( ! defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
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

// Delete all video and playlist CPT posts, and the video files they own.
//
// INSIDE THE PRO GUARD, WHICH IT USED NOT TO BE.
//
// Options and transients below were already skipped while Pro is installed,
// deliberately, so that removing Free does not wipe a site that is still
// running Pro. This loop sat outside that guard and ran unconditionally - so
// the protection covered the settings and not the content, and the one thing
// a site owner actually cannot rebuild was the one thing that was destroyed.
//
// The files are removed explicitly. The plugin is not bootstrapped during
// uninstall, so `before_delete_post` never fires and Cleanup never runs: every
// self-hosted video file was left behind while its record was deleted. The
// whole directory goes rather than one file per post - it belongs to this
// plugin alone, and doing it that way also collects files orphaned by that bug
// before this release.
//
// Deleting in batches keeps a library of several thousand videos from turning
// the uninstall request into a timeout, which would leave the removal half
// finished with no indication of where it stopped.
if ( ! defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
	$ms_post_types = array( 'mediashield_video', 'mediashield_playlist' );

	foreach ( $ms_post_types as $ms_cpt ) {
		$ms_batch = 200;

		do {
			$ms_posts = get_posts(
				array(
					'post_type'      => $ms_cpt,
					'post_status'    => 'any',
					'posts_per_page' => $ms_batch,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			$ms_found = count( $ms_posts );

			foreach ( $ms_posts as $ms_post_id ) {
				wp_delete_post( $ms_post_id, true );
			}
		} while ( $ms_found === $ms_batch );
	}

	// Remove the plugin's own upload directory and everything in it.
	$ms_upload = wp_upload_dir();
	$ms_dir    = trailingslashit( $ms_upload['basedir'] ) . 'mediashield';

	if ( is_dir( $ms_dir ) ) {
		$ms_files = glob( trailingslashit( $ms_dir ) . '*' );

		foreach ( (array) $ms_files as $ms_file ) {
			if ( is_file( $ms_file ) ) {
				wp_delete_file( $ms_file );
			}
		}

		// Only removes the directory when it is genuinely empty, so anything
		// unexpected in there is left for a human rather than deleted blind.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- WP_Filesystem is not initialised during uninstall; a non-empty directory is a deliberate no-op.
		@rmdir( $ms_dir );
	}
}

// Clean up transients (skip if Pro is active to avoid destroying Pro transients).
if ( ! defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ms\_%' OR option_name LIKE '_transient_timeout_ms\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Uninstall cleanup, static LIKE pattern.
}
