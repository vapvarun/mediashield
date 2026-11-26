<?php
/**
 * Every Free REST route declares who may reach it, and the public ones are probed.
 *
 * Release Gate G2 (authorisation matrix as a test). Pro carries the same test
 * over mediashield-pro/v1 and its own manifest.
 *
 * Nothing else in the pipeline asks the authorisation question: WPCS, PHPStan,
 * the contract audit and the browser smoke all passed on the 1.4.4 tag routes
 * that let a contributor untag the admin's videos and delete site-wide tags.
 * So the claim moves out of comments and into audit/route-authority.json,
 * where it is checked against the LIVE registry:
 *
 *   1. a registered route missing from the manifest fails;
 *   2. a manifest entry with no live route fails (a conditional entry must
 *      name the option that mounts it);
 *   3. the live permission_callback must be the declared one; a __return_true
 *      route must be declared public with a reason written from an observed
 *      dispatch; a member/owner/admin route whose callback answers true to a
 *      signed-out request fails;
 *   4. every public route that takes an object id, and every route that
 *      declares a probe, is DISPATCHED signed out against a private video
 *      and must refuse it.
 *
 * Seed or refresh the manifest from the live registry with
 * ~/.claude/skills/wp-plugin-release/scripts/route-authority-scan.php mediashield/v1.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Route authority for mediashield/v1.
 */
class RouteAuthorityTest extends WP_UnitTestCase {

	private const MANIFEST = __DIR__ . '/../../audit/route-authority.json';

	private const NAMESPACES = array( 'mediashield/v1' );

	private const AUDIENCES = array( 'public', 'member', 'owner', 'admin', 'guarded' );

	private const SECRET = 'ROUTE-AUTHORITY-SECRET';

	/**
	 * Declared routes, keyed "METHODS /route".
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $manifest = array();

	/**
	 * Read the manifest and register the routes.
	 */
	public function set_up(): void {
		parent::set_up();
		$decoded        = wp_json_file_decode( self::MANIFEST, array( 'associative' => true ) );
		$this->manifest = is_array( $decoded ) && is_array( $decoded['routes'] ?? null ) ? $decoded['routes'] : array();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		do_action( 'rest_api_init' );
	}

	/**
	 * A callback's name as the manifest records it.
	 *
	 * @param mixed $cb Permission callback.
	 */
	private static function callback_name( $cb ): string {
		if ( is_array( $cb ) && isset( $cb[0], $cb[1] ) ) {
			return ( is_object( $cb[0] ) ? get_class( $cb[0] ) : (string) $cb[0] ) . '::' . $cb[1];
		}
		if ( $cb instanceof \Closure ) {
			return 'Closure';
		}
		return is_string( $cb ) ? $cb : 'NONE';
	}

	/**
	 * Live routes, keyed exactly as the manifest keys them.
	 *
	 * @return array<string, array{route:string, methods:string[], callback:mixed, name:string, object_id:bool}>
	 */
	private function live_routes(): array {
		$out = array();

		foreach ( rest_get_server()->get_routes() as $route => $handlers ) {
			$ns = '';
			foreach ( self::NAMESPACES as $candidate ) {
				if ( 0 === strpos( ltrim( $route, '/' ), $candidate . '/' ) ) {
					$ns = $candidate;
					break;
				}
			}
			if ( '' === $ns ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				$methods = array_keys( array_filter( (array) ( $handler['methods'] ?? array() ) ) );
				$cb      = $handler['permission_callback'] ?? null;

				$out[ implode( ',', $methods ) . ' ' . $route ] = array(
					'route'     => $route,
					'methods'   => $methods,
					'callback'  => $cb,
					'name'      => self::callback_name( $cb ),
					'object_id' => (bool) preg_match( '/\(\?P<(id|[a-z_]+_id)>/', $route ),
				);
			}
		}

		return $out;
	}

	/**
	 * Does a signed-out caller with no parameters get past this callback?
	 *
	 * Catches a callback that is public in effect without being __return_true.
	 *
	 * @param array{route:string, methods:string[], callback:mixed} $live Live route.
	 */
	private static function passes_anonymously( array $live ): bool {
		if ( ! is_callable( $live['callback'] ) ) {
			return false;
		}
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( $live['methods'][0], $live['route'] );
		return true === call_user_func( $live['callback'], $request );
	}

	/**
	 * Tripwire: a walk that finds nothing passes every check below vacuously.
	 */
	public function test_the_registry_and_manifest_are_not_empty(): void {
		$this->assertGreaterThan( 20, count( $this->live_routes() ), 'The live registry came back (nearly) empty for ' . implode( ', ', self::NAMESPACES ) . '.' );
		$this->assertGreaterThan( 20, count( $this->manifest ), 'audit/route-authority.json could not be read.' );
	}

	/**
	 * Check 1: a registered route missing from the manifest fails.
	 */
	public function test_every_live_route_declares_its_audience(): void {
		$problems = array_keys( array_diff_key( $this->live_routes(), $this->manifest ) );

		foreach ( $this->manifest as $key => $declared ) {
			if ( ! in_array( $declared['audience'] ?? '', self::AUDIENCES, true ) ) {
				$problems[] = $key . ' has no valid audience (' . implode( '|', self::AUDIENCES ) . ')';
			}
		}

		$this->assertSame(
			array(),
			$problems,
			"These routes are registered but not declared in audit/route-authority.json, or declare no valid audience.\n"
			. 'A route nobody declared is a route nobody reviewed.'
		);
	}

	/**
	 * Check 2: a declared route that is not registered fails, unless it names the toggle that mounts it.
	 */
	public function test_the_manifest_has_no_routes_that_no_longer_exist(): void {
		$problems = array();

		foreach ( array_diff_key( $this->manifest, $this->live_routes() ) as $key => $declared ) {
			if ( empty( $declared['conditional'] ) ) {
				$problems[] = $key . ' is declared but not registered. Remove it.';
			} elseif ( '' === trim( (string) ( $declared['conditional_on'] ?? '' ) ) ) {
				$problems[] = $key . ' is conditional but names no option that mounts it.';
			}
		}

		$this->assertSame( array(), $problems, implode( "\n", $problems ) );
	}

	/**
	 * Check 3a: the authority in the code is the one declared.
	 */
	public function test_each_route_runs_the_declared_permission_callback(): void {
		$problems = array();

		foreach ( $this->live_routes() as $key => $live ) {
			$declared = (string) ( $this->manifest[ $key ]['permission_callback'] ?? '' );
			if ( isset( $this->manifest[ $key ] ) && $declared !== $live['name'] ) {
				$problems[] = $key . ' runs ' . $live['name'] . ' but declares ' . $declared;
			}
		}

		$this->assertSame( array(), $problems, "The authority in the code is not the one in the manifest:\n" . implode( "\n", $problems ) );
	}

	/**
	 * Check 3b: whatever a signed-out caller passes is declared public, with a reason and a probe.
	 */
	public function test_public_routes_are_declared_public_and_say_why(): void {
		$problems = array();

		foreach ( $this->live_routes() as $key => $live ) {
			$declared = $this->manifest[ $key ] ?? null;
			if ( ! $declared ) {
				continue; // Covered by the declaration test.
			}
			$audience = (string) ( $declared['audience'] ?? '' );

			// A guarded callback may admit guests by design (login gate off), so
			// only member/owner/admin routes are held to "refuses a signed-out caller".
			if ( ( 'public' !== $audience && '__return_true' === $live['callback'] )
				|| ( in_array( $audience, array( 'member', 'owner', 'admin' ), true ) && self::passes_anonymously( $live ) ) ) {
				$problems[] = $key . ' lets a signed-out caller through but is declared "' . $audience . '"';
				continue;
			}

			if ( 'public' === $audience ) {
				$reason = trim( (string) ( $declared['reason'] ?? '' ) );
				if ( '' === $reason || 0 === strpos( $reason, 'TODO' ) ) {
					$problems[] = $key . ' is declared public with no reason. Say what a signed-out caller observably gets.';
				}
				if ( $live['object_id'] && empty( $declared['probe'] ) ) {
					$problems[] = $key . ' is public and takes an object id but declares no probe.';
				}
			}
		}

		$this->assertSame( array(), $problems, implode( "\n", $problems ) );
	}

	/**
	 * Check 4, the teeth: routes a guest may reach must refuse a private video.
	 */
	public function test_guest_reachable_routes_refuse_a_private_video(): void {
		global $wpdb;

		$video    = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'private',
				'post_title'  => self::SECRET,
				'post_author' => self::factory()->user->create( array( 'role' => 'administrator' ) ),
			)
		);
		$playlist = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_playlist',
				'post_status' => 'publish',
			)
		);
		// A published playlist still holding a private video: the shape that would leak.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- test fixture in a custom table.
			$wpdb->prefix . 'ms_playlist_items',
			array(
				'playlist_id' => $playlist,
				'video_id'    => $video,
				'sort_order'  => 0,
			)
		);

		wp_set_current_user( 0 );

		$probed = 0;
		$leaks  = array();

		foreach ( $this->live_routes() as $key => $live ) {
			$probe = $this->manifest[ $key ]['probe'] ?? null;
			if ( ! is_array( $probe ) ) {
				continue;
			}

			$path = (string) preg_replace_callback(
				'/\(\?P<(\w+)>[^)]+\)/',
				static fn( array $m ): string => (string) ( 'playlist_id' === $m[1] ? $playlist : $video ),
				$live['route']
			);
			if ( false !== strpos( $path, '(?P<' ) ) {
				$leaks[] = $key . ' - the probe could not build a path, so it proved nothing';
				continue;
			}

			$request = new WP_REST_Request( (string) ( $probe['method'] ?? $live['methods'][0] ), $path );
			foreach ( (array) ( $probe['params'] ?? array() ) as $name => $value ) {
				$request->set_param( (string) $name, $value );
			}
			if ( isset( $probe['body'] ) ) {
				$request->set_header( 'content-type', 'application/json' );
				$request->set_body( (string) wp_json_encode( $probe['body'] ) );
			}
			$response = rest_do_request( $request );
			++$probed;

			if ( 400 === $response->get_status() ) {
				$leaks[] = $key . ' - answered 400, which proves nothing about authority. Send schema-valid params.';
			} elseif ( $response->get_status() < 400 || false !== strpos( (string) wp_json_encode( $response->get_data() ), self::SECRET ) ) {
				$leaks[] = $key . ' - answered ' . $response->get_status() . ' to the signed-out probe (private video fixture, no token)';
			}
		}

		$this->assertGreaterThan( 0, $probed, 'No route was probed: the manifest declares no probe, so this check tested nothing.' );
		$this->assertSame( array(), $leaks, "A guest-reachable route did not refuse a private video:\n" . implode( "\n", $leaks ) );
	}
}
