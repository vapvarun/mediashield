<?php
/**
 * Database version tracking and migration runner.
 *
 * @package MediaShield\Core
 */

namespace MediaShield\Core;

use MediaShield\DB\Schema;

class Migrator {

	/**
	 * Run migrations if the DB version is stale.
	 */
	public static function run(): void {
		$installed = (int) get_option( 'ms_db_version', 0 );

		if ( $installed < MEDIASHIELD_DB_VERSION ) {
			Schema::create_tables();
			update_option( 'ms_db_version', MEDIASHIELD_DB_VERSION );
		}
	}
}
