<?php
/**
 * The platform capability questions, and the one that used to be duplicated.
 *
 * A scan reported "enum drift" across four files. Most of it was not drift -
 * CPT\Thumbnail asks which platforms expose a thumbnail API, the player sites
 * ask which need an adaptive player, and those are different questions. What
 * WAS real is that the second question was written out twice, once in Renderer
 * and once in PlayerWrapper, which is exactly how the two render paths came to
 * disagree about protection level earlier in 1.3.0.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Support\Platforms;
use WP_UnitTestCase;

/**
 * Platforms helper.
 */
class PlatformsTest extends WP_UnitTestCase {

	/**
	 * The two platforms whose media is an adaptive stream.
	 */
	public function test_adaptive_platforms_need_the_streaming_player(): void {
		$this->assertTrue( Platforms::needs_adaptive_player( 'self' ) );
		$this->assertTrue( Platforms::needs_adaptive_player( 'bunny' ) );
	}

	/**
	 * Everything else plays in the provider's own embed and needs no HLS
	 * library. Loading one for them is pure page weight.
	 */
	public function test_iframe_platforms_do_not(): void {
		foreach ( array( 'youtube', 'vimeo', 'wistia' ) as $platform ) {
			$this->assertFalse(
				Platforms::needs_adaptive_player( $platform ),
				"{$platform} plays in its own embed and must not pull the streaming library."
			);
		}
	}

	/**
	 * An unset platform must not be treated as adaptive - a video with no
	 * platform meta should not drag a 400 KB library onto the page.
	 */
	public function test_empty_platform_is_not_adaptive(): void {
		$this->assertFalse( Platforms::needs_adaptive_player( '' ) );
		$this->assertFalse( Platforms::needs_adaptive_player( 'not-a-platform' ) );
	}

	/**
	 * A driver registered by Pro or a third party can opt into the adaptive
	 * player without editing this class.
	 */
	public function test_the_adaptive_list_is_filterable(): void {
		$this->assertFalse( Platforms::needs_adaptive_player( 'wistia' ) );

		$add = static function ( array $platforms ): array {
			$platforms[] = 'wistia';
			return $platforms;
		};
		add_filter( 'mediashield_adaptive_platforms', $add );

		$this->assertTrue( Platforms::needs_adaptive_player( 'wistia' ) );

		remove_filter( 'mediashield_adaptive_platforms', $add );
	}

	/**
	 * all() comes from the registered drivers, not a hardcoded list, so a
	 * platform added through `mediashield_upload_drivers` appears here.
	 */
	public function test_all_is_derived_from_registered_drivers(): void {
		$add = static function ( array $drivers ): array {
			$drivers['acme'] = 'Acme\\Driver';
			return $drivers;
		};
		add_filter( 'mediashield_upload_drivers', $add );

		$all = Platforms::all();

		$this->assertContains( 'acme', $all, 'A registered driver must appear in the platform list.' );
		$this->assertContains( 'self', $all, 'Self-hosted is always available.' );
		$this->assertNotContains(
			'self_hosted',
			$all,
			'The driver is named self_hosted but the meta value is self; the two must not both appear.'
		);

		remove_filter( 'mediashield_upload_drivers', $add );
	}
}
