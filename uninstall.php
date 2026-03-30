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
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// Delete all plugin options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ms\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Remove custom capability from all roles.
global $wp_roles;
if ( isset( $wp_roles ) ) {
	foreach ( $wp_roles->roles as $role_name => $role_info ) {
		$role = get_role( $role_name );
		if ( $role ) {
			$role->remove_cap( 'upload_mediashield' );
		}
	}
}

// Delete all video and playlist CPT posts.
$post_types = array( 'mediashield_video', 'mediashield_playlist' );
foreach ( $post_types as $post_type ) {
	$posts = get_posts( array(
		'post_type'      => $post_type,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );

	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

// Clean up transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ms\_%' OR option_name LIKE '_transient_timeout_ms\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
