<?php
/**
 * Database version tracking and migration runner.
 *
 * @package MediaShield\Core
 */

namespace MediaShield\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\DB\Schema;

/**
 * Class Migrator
 *
 * Database version tracking and migration runner.
 *
 * @since 1.0.0
 */
class Migrator {

	/**
	 * Run migrations if the DB version is stale.
	 *
	 * Re-seeds option defaults on every version bump so newly-declared settings
	 * in `Core\Settings::schema()` reach existing installs without a manual
	 * deactivate/reactivate.
	 */
	public static function run(): void {
		$installed = (int) get_option( 'ms_db_version', 0 );

		if ( $installed < MEDIASHIELD_DB_VERSION ) {
			Schema::create_tables();
			Settings::seed_defaults();
			update_option( 'ms_db_version', MEDIASHIELD_DB_VERSION );
		}
	}
}
