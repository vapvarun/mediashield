<?php
/**
 * Regression tests for the Allowed Domains whitelist.
 *
 * The field's help text said "One domain per line" and the parser split on
 * commas. An owner who followed the instruction produced a value matching no
 * referer, and since the whitelist is only consulted when non-empty, filling it
 * in correctly switched on a check that could never pass - denying every
 * cross-origin embed. It presents as a playback bug, so the field is the last
 * place anyone would look.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\AccessControl;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Whitelist parsing.
 */
class AllowedDomainsTest extends WP_UnitTestCase {

	/**
	 * Call the private parser.
	 *
	 * @param string $raw Field value.
	 * @return string[]
	 */
	private function parse( string $raw ): array {
		$method = new ReflectionMethod( AccessControl::class, 'parse_domains' );
		$method->setAccessible( true );

		return $method->invoke( null, $raw );
	}

	/**
	 * Newlines - what the on-screen instruction produces.
	 */
	public function test_newline_separated_domains_are_parsed(): void {
		$this->assertSame(
			array( 'example.com', 'partner.org' ),
			$this->parse( "example.com\npartner.org" )
		);
	}

	/**
	 * Commas - what sites configured before the help text existed are storing.
	 */
	public function test_comma_separated_domains_are_parsed(): void {
		$this->assertSame(
			array( 'example.com', 'partner.org' ),
			$this->parse( 'example.com, partner.org' )
		);
	}

	/**
	 * Both at once, because real fields end up messy.
	 */
	public function test_mixed_separators_are_parsed(): void {
		$this->assertSame(
			array( 'a.com', 'b.com', 'c.com' ),
			$this->parse( "a.com,\n b.com\r\nc.com," )
		);
	}

	/**
	 * A pasted URL yields its host. "Domain" is not a distinction most people
	 * make when the thing in their clipboard is a full address.
	 */
	public function test_a_pasted_url_is_reduced_to_its_host(): void {
		$this->assertSame(
			array( 'example.com' ),
			$this->parse( 'https://example.com/embed/123?x=1' )
		);
	}

	/**
	 * A bare host with a port is still a host.
	 */
	public function test_host_with_port_is_reduced(): void {
		$this->assertSame( array( 'example.com' ), $this->parse( 'example.com:8443' ) );
	}

	/**
	 * Case and a trailing DNS dot never appear in a Referer host.
	 */
	public function test_entries_are_normalised(): void {
		$this->assertSame( array( 'example.com' ), $this->parse( 'Example.COM.' ) );
	}

	/**
	 * Duplicates collapse and empties are dropped, so an owner who leaves
	 * stray commas or blank lines does not get empty entries that match
	 * nothing but still count as a configured whitelist.
	 */
	public function test_duplicates_and_empties_are_removed(): void {
		$this->assertSame(
			array( 'example.com' ),
			$this->parse( "example.com\n\n,  ,\nexample.com\n" )
		);
	}

	/**
	 * An empty field yields no entries at all - the caller relies on that to
	 * decide the whitelist is not configured.
	 */
	public function test_empty_value_yields_no_domains(): void {
		$this->assertSame( array(), $this->parse( "  \n\t " ) );
	}
}
