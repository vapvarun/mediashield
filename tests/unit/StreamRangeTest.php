<?php
/**
 * Byte ranges for self-hosted streams (card 10474873971).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\REST\StreamController;
use WP_UnitTestCase;

/**
 * StreamController::byte_range() against RFC 9110 14.1.1.
 */
class StreamRangeTest extends WP_UnitTestCase {

	/**
	 * Range header => expected [first, last] byte, or null for 416.
	 *
	 * @return array<string, array{0:string, 1:?array}>
	 */
	public function ranges(): array {
		return array(
			'plain'               => array( 'bytes=1000-1999', array( 1000, 1999 ) ),
			'open end'            => array( 'bytes=0-', array( 0, 9999 ) ),
			'suffix'              => array( 'bytes=-500', array( 9500, 9999 ) ),
			'suffix over size'    => array( 'bytes=-15000', array( 0, 9999 ) ),
			'end past file'       => array( 'bytes=9900-10099', array( 9900, 9999 ) ),
			'window past the end' => array( 'bytes=0-20000', array( 0, 9999 ) ),
			'start at size'       => array( 'bytes=10000-', null ),
			'empty suffix'        => array( 'bytes=-0', null ),
			'start after end'     => array( 'bytes=500-100', null ),
		);
	}

	/**
	 * @dataProvider ranges
	 *
	 * @param string     $header   Range header.
	 * @param array|null $expected Expected bytes.
	 */
	public function test_byte_range( string $header, ?array $expected ): void {
		$this->assertSame( $expected, StreamController::byte_range( $header, 10000 ) );
	}
}
