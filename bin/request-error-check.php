<?php
/**
 * Guard: no MediaShield script shows the browser's or the parser's words for
 * a failed request (card 10498410926).
 *
 * A dropped connection rejects fetch() with "Failed to fetch" / "Load failed"
 * / "NetworkError...", and an HTML error page (a 502, a PHP fatal) fails JSON
 * parsing with "Unexpected token '<' ..." or apiFetch's "The response is not a
 * valid JSON response.". The one reader is window.mediashieldRequest in
 * assets/js/request.js: message() for the words, readJSON() for parsing.
 *
 * Static scan of every script under src/ and assets/js/ (no build output, no
 * vendor, no minified files) of the plugin given, naming file:line for:
 *   1. a response parsed with `.json()` instead of mediashieldRequest.readJSON();
 *   2. a caught error's `.message` shown directly (err / error / e / ex /
 *      reason) instead of through mediashieldRequest.message().
 *   3. in a script that also saves (POST / PUT / PATCH / DELETE, or an
 *      auto-save), a catch that is empty or only logs: a failed load then
 *      leaves defaults on screen that the next save stores over the owner's
 *      real data (card 10499285498). A deliberate silence carries an
 *      `ms-silent-ok:` comment with its reason on the same or previous line.
 *
 * RUN:  php bin/request-error-check.php [plugin-dir]   (default: this plugin)
 *       MediaShield Pro's local CI runs it against Pro with the sibling Free copy.
 *
 * @package MediaShield
 */

$root  = rtrim( $argv[1] ?? dirname( __DIR__ ), '/' ) . '/';
$fail  = array();
$files = array();

foreach ( array( 'src', 'assets/js' ) as $dir ) {
	if ( ! is_dir( $root . $dir ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = (string) $file;
		if ( str_ends_with( $path, '.js' ) && ! str_ends_with( $path, '.min.js' ) && false === strpos( $path, '/vendor/' ) && false === strpos( $path, '/build/' ) ) {
			$files[] = $path;
		}
	}
}

if ( count( $files ) < 10 ) {
	$fail[] = 'tripwire: only ' . count( $files ) . ' scripts found under ' . $root;
}

foreach ( $files as $file ) {
	$rel = substr( $file, strlen( $root ) );
	if ( 'assets/js/request.js' === $rel ) {
		continue;
	}
	$text  = (string) file_get_contents( $file );
	$saves = (bool) preg_match( "/method:\s*['\"](POST|PUT|PATCH|DELETE)['\"]|useAutoSave|saveField\(/i", $text );
	$lines = explode( "\n", $text );
	foreach ( $lines as $i => $line ) {
		$code = trim( $line );
		if ( str_starts_with( $code, '//' ) || str_starts_with( $code, '*' ) || str_starts_with( $code, '/*' ) ) {
			continue;
		}
		if ( preg_match( '/\.json\(\s*\)(?!\s*\.catch)/', $line ) ) {
			$fail[] = sprintf( "%s:%d parses a response with .json() instead of mediashieldRequest.readJSON(): %s", $rel, $i + 1, substr( $code, 0, 100 ) );
		}
		$silent_ok = false !== strpos( $line, 'ms-silent-ok:' ) || ( $i > 0 && false !== strpos( $lines[ $i - 1 ], 'ms-silent-ok:' ) );
		// play(), requestFullscreen() and exitFullscreen() are not requests: a
		// browser may refuse them, and that refusal is meant to be quiet.
		$not_request = (bool) preg_match( '/\.(play|requestFullscreen|exitFullscreen|webkitExitFullscreen)\(\)\s*\.catch/', $line );
		if ( $saves && ! $silent_ok && ! $not_request && preg_match( '/\.catch\(\s*(?:\(\s*[\w\s,]*\)\s*=>|function\s*\([^)]*\))\s*(?:\{\s*(?:console\.\w+\([^;]*\);?\s*)?\}|null|undefined|console\.\w+\([^)]*\))\s*\)/', $line ) ) {
			$fail[] = sprintf( "%s:%d swallows a failed request in a script that saves (a failed load can be saved over real data): %s", $rel, $i + 1, substr( $code, 0, 100 ) );
		}
		if ( preg_match( '/(?<![\w.])(err|error|e|ex|reason)\??\.message\b/', $line ) && false === strpos( $line, 'mediashieldRequest' ) ) {
			$fail[] = sprintf( "%s:%d shows a caught error's message instead of mediashieldRequest.message(): %s", $rel, $i + 1, substr( $code, 0, 100 ) );
		}
	}
}

$reader = __DIR__ . '/../assets/js/request.js';
$src    = is_file( $reader ) ? (string) file_get_contents( $reader ) : '';
if ( false === strpos( $src, 'window.mediashieldRequest = {' ) || ! preg_match( "/'fetch_error'.*'invalid_json'/s", $src ) || false === strpos( $src, 'readJSON:' ) ) {
	$fail[] = 'assets/js/request.js has no mediashieldRequest.message()/readJSON() treating fetch_error and invalid_json as network failures';
}

foreach ( $fail as $label ) {
	echo "FAIL: {$label}\n";
}
echo 'request-error-check (' . basename( $root ) . '): ' . ( $fail ? count( $fail ) . ' fail' : 'PASS (' . count( $files ) . ' scripts)' ) . "\n";
exit( $fail ? 1 : 0 );
