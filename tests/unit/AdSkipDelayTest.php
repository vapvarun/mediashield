<?php
/**
 * Regression tests for the per-ad skip delay (BC#10128639184).
 *
 * The precedence here was got wrong twice: first by ignoring the creative's
 * value entirely, then by treating a 0 from WB Ad Manager as a configured
 * "skip instantly" when it actually means "field left blank". Both are pinned.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Integrations\AdManagerBridge;
use WP_UnitTestCase;

/**
 * Skip-delay precedence.
 */
class AdSkipDelayTest extends WP_UnitTestCase {

	/**
	 * Build a break from a creative row, as the bridge would.
	 *
	 * @param array $ad Creative row.
	 * @return array|null
	 */
	private function to_break( array $ad ) {
		$bridge = new AdManagerBridge();
		$method = new \ReflectionMethod( $bridge, 'to_break' );
		$method->setAccessible( true );

		return $method->invoke( $bridge, 'pre', 0, $ad );
	}

	/**
	 * A creative row in the shape WB Ad Manager 3.1.0 actually returns.
	 *
	 * @param mixed $skip_after Value for the skip_after key.
	 * @return array
	 */
	private function creative( $skip_after ): array {
		return array(
			'id'         => 1869,
			'label'      => 'Test Ad',
			'video_url'  => 'https://example.test/ad.mp4',
			'click_url'  => '',
			'skip_after' => $skip_after,
		);
	}

	/**
	 * Set the plugin-level defaults these tests assume.
	 */
	public function set_up(): void {
		parent::set_up();
		update_option( 'ms_ads_require_full_view', false );
		update_option( 'ms_ads_skip_after', 5 );
	}

	/**
	 * The original bug: the per-ad value was read and thrown away.
	 */
	public function test_per_ad_delay_is_used(): void {
		$break = $this->to_break( $this->creative( 2 ) );

		$this->assertSame( 2, $break['skipAfter'], 'A configured per-ad delay must win over the global.' );
	}

	/**
	 * The second bug: WB Ad Manager's resolver ALWAYS emits skip_after and
	 * defaults it to 0, and its edit field stores 0 when left blank. Treating 0
	 * as configured made the plugin-level default unreachable on every site.
	 */
	public function test_zero_means_unset_and_falls_back_to_the_global(): void {
		$break = $this->to_break( $this->creative( 0 ) );

		$this->assertSame( 5, $break['skipAfter'], 'A blank field (stored as 0) must fall back to the site default.' );
	}

	/**
	 * A missing key behaves the same as a blank one.
	 */
	public function test_absent_key_falls_back_to_the_global(): void {
		$ad = $this->creative( 0 );
		unset( $ad['skip_after'] );

		$this->assertSame( 5, $this->to_break( $ad )['skipAfter'] );
	}

	/**
	 * Mandatory full-view is a site-wide compliance switch and still overrides
	 * everything: an advertiser must not be able to opt a viewer out of a rule
	 * the owner set for legal reasons.
	 */
	public function test_full_view_overrides_a_per_ad_delay(): void {
		update_option( 'ms_ads_require_full_view', true );

		$this->assertNull( $this->to_break( $this->creative( 2 ) )['skipAfter'] );
	}

	/**
	 * A bad value from the ad plugin must not produce an ad that is
	 * effectively never skippable.
	 */
	public function test_absurd_per_ad_delay_is_clamped(): void {
		$this->assertSame( 60, $this->to_break( $this->creative( 999 ) )['skipAfter'] );
	}

	/**
	 * Negative values are nonsense and fall back rather than clamping to 0,
	 * which would silently mean "skip instantly".
	 */
	public function test_negative_per_ad_delay_falls_back(): void {
		$this->assertSame( 5, $this->to_break( $this->creative( -3 ) )['skipAfter'] );
	}
}
