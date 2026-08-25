<?php
/**
 * WordPress Site Health tests for MediaShield.
 *
 * @package MediaShield\Admin
 */

namespace MediaShield\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers MediaShield checks in Tools > Site Health.
 *
 * @since 1.3.0
 */
class HealthCheck {

	/**
	 * Hook the Site Health test.
	 */
	public static function register(): void {
		add_filter( 'site_status_tests', array( __CLASS__, 'add_tests' ) );
	}

	/**
	 * Add MediaShield's tests to the Site Health suite.
	 *
	 * Registered as an async test: it performs an HTTP request, which is far too
	 * slow to run inline while the Site Health screen renders.
	 *
	 * @param array $tests Existing tests.
	 * @return array
	 */
	public static function add_tests( array $tests ): array {
		$tests['async']['mediashield_upload_dir_protected'] = array(
			'label'     => __( 'MediaShield video files are not publicly downloadable', 'mediashield' ),
			'test'      => 'mediashield_upload_dir_protected',
			'has_rest'  => false,
			'async_direct_test' => array( __CLASS__, 'test_upload_dir_protected' ),
		);

		return $tests;
	}

	/**
	 * Ask the web server whether it will serve a file from the video directory.
	 *
	 * WHY THIS FETCHES RATHER THAN INSPECTS
	 *
	 * The upload directory carries an .htaccess containing "Require all denied",
	 * and the plugin treated that as protection - StreamController's own docblock
	 * said direct access was "blocked by .htaccess". That is an Apache directive.
	 * nginx never reads .htaccess, so on nginx hosts every self-hosted video is
	 * downloadable by URL, bypassing AccessControl::can_watch() entirely
	 * (BC#10231033764). Measured on nginx 1.26: HTTP 200 for the file, 403 for
	 * the gated endpoint.
	 *
	 * Checking that the .htaccess EXISTS would therefore report success on
	 * exactly the hosts where the protection does not work. The only answer that
	 * means anything is the server's own, so this asks for a real file over HTTP
	 * and reports what comes back.
	 *
	 * @return array Site Health result.
	 */
	public static function test_upload_dir_protected(): array {
		$result = array(
			'label'       => __( 'MediaShield video files are not publicly downloadable', 'mediashield' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'mediashield' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Your web server refuses direct requests for MediaShield video files, so they can only be watched through the permission-checked player.', 'mediashield' ) . '</p>',
			'actions'     => '',
			'test'        => 'mediashield_upload_dir_protected',
		);

		$upload  = wp_upload_dir();
		$dir     = trailingslashit( $upload['basedir'] ) . 'mediashield/';
		$probe   = self::find_probe_file( $dir );

		// Nothing self-hosted yet: there is nothing to expose, so nothing to
		// report. Saying "protected" here would be a guess.
		if ( '' === $probe ) {
			$result['status']      = 'good';
			$result['description'] = '<p>' . esc_html__( 'No self-hosted video files are stored yet, so there is nothing that could be downloaded directly. This check will run once you upload one.', 'mediashield' ) . '</p>';
			return $result;
		}

		$url      = trailingslashit( $upload['baseurl'] ) . 'mediashield/' . rawurlencode( $probe );
		$response = wp_remote_head(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => false,
				// A redirect to a login page still means the file itself was not
				// served, so let the status speak rather than following.
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'MediaShield could not check whether your video files are downloadable', 'mediashield' );
			$result['description'] = '<p>' . sprintf(
				/* translators: %s: error message from the HTTP request. */
				esc_html__( 'The check could not reach your own site to test this (%s). That usually means outgoing requests are blocked, not that anything is wrong with your videos.', 'mediashield' ),
				esc_html( $response->get_error_message() )
			) . '</p>';
			return $result;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return $result;
		}

		$result['status'] = 'critical';
		$result['badge']['color'] = 'red';
		$result['label']  = __( 'MediaShield video files can be downloaded directly, bypassing access checks', 'mediashield' );
		$result['description'] = '<p>' . esc_html__( 'Your web server is serving MediaShield video files straight from their storage folder. Anyone with the file address can download a video without logging in, and without any of your access rules being applied.', 'mediashield' ) . '</p>'
			. '<p>' . esc_html__( 'MediaShield ships an .htaccess rule to prevent this, but that only works on Apache. If your site runs nginx, the rule is ignored and the folder needs a matching rule in your server configuration.', 'mediashield' ) . '</p>';
		$result['actions'] = '<p>' . esc_html__( 'Ask your host to deny direct requests to the /wp-content/uploads/mediashield/ folder. Video playback will keep working, because the player does not use that address.', 'mediashield' ) . '</p>';

		return $result;
	}

	/**
	 * Pick any stored video file to test with.
	 *
	 * @param string $dir Absolute path to the video directory.
	 * @return string Filename, or '' when the directory holds no video.
	 */
	private static function find_probe_file( string $dir ): string {
		if ( ! is_dir( $dir ) ) {
			return '';
		}

		foreach ( (array) glob( $dir . '*.{mp4,webm,mov,m4v}', GLOB_BRACE ) as $path ) {
			if ( is_file( $path ) ) {
				return basename( $path );
			}
		}

		return '';
	}
}
