<?php
/**
 * The one display timezone and the one date formatter (time standard).
 *
 * Every test here fails before includes/Support/Time.php exists.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Support\Time;
use WP_UnitTestCase;

/**
 * Time::display_timezone() and Time::date_label().
 */
class TimeLabelTest extends WP_UnitTestCase {

	/**
	 * Site timezone ahead of UTC, so a UTC read and a local read differ.
	 */
	public function set_up(): void {
		parent::set_up();
		update_option( 'timezone_string', 'Asia/Kolkata' );
	}

	/**
	 * A naive stored string is UTC and is shown in the site timezone.
	 */
	public function test_naive_string_is_read_as_utc(): void {
		$this->assertSame( '2026-01-15 05:30', Time::date_label( '2026-01-15 00:00:00', '', 'Y-m-d H:i' ) );
		$this->assertSame( '2026-01-15 05:30', Time::date_label( '2026-01-15T00:00:00Z', '', 'Y-m-d H:i' ) );
	}

	/**
	 * A Unix timestamp is accepted as is.
	 */
	public function test_timestamp_input(): void {
		$this->assertSame( '2026-01-15 05:30', Time::date_label( 1768435200, '', 'Y-m-d H:i' ) );
	}

	/**
	 * No usable time gives the fallback.
	 */
	public function test_empty_and_zero_dates_return_fallback(): void {
		$this->assertSame( 'never', Time::date_label( null, 'never' ) );
		$this->assertSame( 'never', Time::date_label( '', 'never' ) );
		$this->assertSame( 'never', Time::date_label( '0000-00-00 00:00:00', 'never' ) );
		$this->assertSame( 'never', Time::date_label( 'not a date', 'never' ) );
		$this->assertSame( 'never', Time::date_label( 0, 'never' ) );
		$this->assertSame( '', Time::date_label( '' ) );
	}

	/**
	 * The default format is the site's date format.
	 */
	public function test_default_format_is_site_date_format(): void {
		update_option( 'date_format', 'F j, Y' );
		$this->assertSame( 'January 15, 2026', Time::date_label( '2026-01-15 12:00:00' ) );
	}

	/**
	 * The filter moves both helpers, and receives the person the time is for.
	 */
	public function test_filter_sets_display_timezone(): void {
		$seen   = null;
		$filter = static function ( $tz, $user_id ) use ( &$seen ) {
			$seen = $user_id;
			return new \DateTimeZone( 'America/New_York' );
		};
		add_filter( 'mediashield_display_timezone', $filter, 10, 2 );

		$this->assertSame( 'America/New_York', Time::display_timezone( 7 )->getName() );
		$this->assertSame( 7, $seen );
		$this->assertSame( '2026-01-14 19:00', Time::date_label( '2026-01-15 00:00:00', '', 'Y-m-d H:i', 7 ) );

		remove_filter( 'mediashield_display_timezone', $filter, 10 );
		$this->assertSame( 'Asia/Kolkata', Time::display_timezone()->getName() );
	}

	/**
	 * A filter that returns junk falls back to the site timezone.
	 */
	public function test_bad_filter_value_falls_back_to_site_timezone(): void {
		add_filter( 'mediashield_display_timezone', '__return_empty_string' );
		$this->assertSame( 'Asia/Kolkata', Time::display_timezone()->getName() );
		remove_filter( 'mediashield_display_timezone', '__return_empty_string' );
	}
}
