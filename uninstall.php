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

// Delete free plugin options — explicit list to avoid destroying Pro config.
if ( ! defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
	$free_options = array(
		'ms_enabled',
		'ms_default_protection',
		'ms_require_login',
		'ms_watermark_opacity',
		'ms_watermark_color',
		'ms_watermark_swap_interval',
		'ms_allowed_domains',
		'ms_max_concurrent_streams',
		'ms_custom_url_patterns',
		'ms_show_badge',
		'ms_max_upload_size',
		'ms_login_overlay_text',
		'ms_login_button_text',
		'ms_access_denied_text',
		'ms_db_version',
		'mediashield_wizard_complete',
	);
	foreach ( $free_options as $option ) {
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
