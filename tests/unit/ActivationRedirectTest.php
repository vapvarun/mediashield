<?php
/**
 * Only a single activation from the Plugins screen opens the setup wizard
 * (BC#10499286227). Bulk activation never sets the redirect; WP-CLI is the
 * same guard, proved live with `wp plugin activate` because WP_CLI is a
 * constant a test process cannot undefine.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Core\Activator;
use WP_UnitTestCase;

/**
 * Activation redirect flag.
 */
class ActivationRedirectTest extends WP_UnitTestCase {

	/**
	 * Start every case with no flag, no finished wizard and no request.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_transient( 'ms_activation_redirect' );
		delete_option( 'ms_wizard_completed' );
		unset( $_REQUEST['action'], $_REQUEST['action2'] );
	}

	/**
	 * Clean the request.
	 */
	public function tear_down(): void {
		unset( $_REQUEST['action'], $_REQUEST['action2'] );
		parent::tear_down();
	}

	/**
	 * A single activation still opens the wizard.
	 */
	public function test_single_activation_sets_the_redirect(): void {
		$_REQUEST['action'] = 'activate';
		Activator::activate();
		$this->assertNotFalse( get_transient( 'ms_activation_redirect' ) );
	}

	/**
	 * Bulk activation from the top or the bottom bulk-action menu does not.
	 */
	public function test_bulk_activation_does_not_set_the_redirect(): void {
		$_REQUEST['action'] = 'activate-selected';
		Activator::activate();
		$this->assertFalse( get_transient( 'ms_activation_redirect' ) );

		$_REQUEST['action']  = '-1';
		$_REQUEST['action2'] = 'activate-selected';
		Activator::activate();
		$this->assertFalse( get_transient( 'ms_activation_redirect' ) );
	}
}
