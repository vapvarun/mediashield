<?php
/**
 * bin/check-vendor-autoload.php — committed-autoloader integrity gate.
 *
 * Asserts that every path the COMMITTED Composer autoloader requires is
 * itself COMMITTED. Not "exists on disk" — committed. That distinction is
 * the whole point of this gate.
 *
 * Why this exists
 * ---------------
 * vendor/ is tracked (Pro ships dompdf + edd-sl-sdk as runtime deps; Free
 * ships symfony/yaml), but dev-only packages are gitignored by name. A plain
 * `composer install` installs dev deps AND regenerates vendor/composer/*
 * WITH dev. Committing that map produces an autoloader that requires
 * packages git does not carry — e.g. myclabs/deep-copy, which arrives via
 * phpunit. The result is a fatal on `require vendor/autoload.php`, so the
 * plugin dies before any of its own code runs and takes the whole site with
 * it (HTTP 500 on every request, WP-CLI included).
 *
 * It is invisible to everyone already working on the plugin, because an
 * existing checkout keeps the vendor/ tree it already had. Only a fresh
 * clone, a pull that touches vendor/, or a clean CI job sees it — which
 * means the first person to hit it is a new developer or a build.
 *
 * Checking the filesystem would NOT catch this: on a developer machine that
 * has run `composer install`, the dev package is sitting right there and
 * every file_exists() passes while a fresh clone still white-screens. So we
 * ask git what it actually carries.
 *
 * Fix when this fails: `composer dump-autoload --no-dev` and commit the
 * regenerated vendor/composer/* files.
 *
 * Exit 0 = autoloader matches the committed tree. Exit 1 = mismatch.
 *
 * @package MediaShield
 */

$plugin_dir = dirname( __DIR__ );
$vendor_dir = $plugin_dir . '/vendor';

if ( ! is_dir( $vendor_dir . '/composer' ) ) {
	echo "⚠ vendor/composer not present — run composer install. Skipping.\n";
	exit( 0 );
}

// Ask git which vendor/ paths are actually committed. A path present on disk
// but absent here is exactly the failure mode we are hunting.
$tracked_raw = shell_exec( 'cd ' . escapeshellarg( $plugin_dir ) . ' && git ls-files vendor/ 2>/dev/null' );
if ( null === $tracked_raw || '' === trim( (string) $tracked_raw ) ) {
	echo "⚠ Not a git checkout, or vendor/ is untracked entirely — nothing to verify.\n";
	exit( 0 );
}

$tracked = array_flip( array_filter( array_map( 'trim', explode( "\n", $tracked_raw ) ) ) );

/**
 * Normalise an absolute path to a repo-relative one, so it can be compared
 * against `git ls-files` output.
 */
$to_relative = static function ( string $path ) use ( $plugin_dir ): string {
	$real = realpath( $path );
	$path = false !== $real ? $real : $path;

	// Collapse the `vendor/composer/../foo` shape Composer emits.
	$path = str_replace( '/composer/../', '/', $path );

	$prefix = $plugin_dir . '/';
	return str_starts_with( $path, $prefix ) ? substr( $path, strlen( $prefix ) ) : $path;
};

$missing = array();

// ── autoload_files.php — the fatal one. These are require'd eagerly by
// autoload_real.php, so a single missing entry kills the whole request.
$files_map = $vendor_dir . '/composer/autoload_files.php';
if ( is_file( $files_map ) ) {
	$files = require $files_map;
	foreach ( (array) $files as $file ) {
		$rel = $to_relative( (string) $file );
		if ( ! isset( $tracked[ $rel ] ) ) {
			$missing[] = array( 'autoload_files.php', $rel, 'FATAL on boot' );
		}
	}
}

// ── autoload_classmap.php — fails later (class-not-found at use time)
// rather than on boot, but is the same defect and just as broken.
$classmap_file = $vendor_dir . '/composer/autoload_classmap.php';
if ( is_file( $classmap_file ) ) {
	$classmap = require $classmap_file;
	$seen     = array();
	foreach ( (array) $classmap as $class => $file ) {
		$rel = $to_relative( (string) $file );
		if ( isset( $seen[ $rel ] ) || isset( $tracked[ $rel ] ) ) {
			continue;
		}
		$seen[ $rel ] = true;
		$missing[]    = array( 'autoload_classmap.php', $rel, 'class-not-found at use time' );
	}
}

if ( empty( $missing ) ) {
	echo "✓ Committed autoloader matches the committed vendor tree.\n";
	exit( 0 );
}

echo "✗ Committed autoloader references paths that are NOT committed.\n";
echo "  A fresh clone of this branch will break.\n\n";
foreach ( $missing as $row ) {
	printf( "  [%s] %s\n      → %s\n", $row[0], $row[1], $row[2] );
}
echo "\n  Fix: composer dump-autoload --no-dev  (then commit vendor/composer/*)\n";
exit( 1 );
