<?php
/**
 * PHPStan bootstrap file for MediaShield.
 *
 * Defines plugin constants so PHPStan can analyse without WordPress loaded.
 */

// MediaShield constants.
define( 'MEDIASHIELD_VERSION', '1.0.0' );
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
