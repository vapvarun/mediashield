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
use MediaShield\Tags\TagManager;

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

		if ( $installed >= MEDIASHIELD_DB_VERSION ) {
			return;
		}

		Schema::create_tables();
		Settings::seed_defaults();

		// v3 — promote legacy free-form milestone tags into the unified
		// `ms_tags` dictionary and record the canonical `tag_id` alongside
		// the display string in each user's `_ms_video_tags` meta entry.
		// The per-version gate lives inside the helper itself so each
		// migration step is independently idempotent and PHPStan can't
		// narrow the comparison via the outer version-bump branch.
		self::backfill_milestone_tags();

		update_option( 'ms_db_version', MEDIASHIELD_DB_VERSION );
	}

	/**
	 * Walk every `_ms_video_tags` user meta record and ensure each `tag`
	 * string exists in `ms_tags`. Stores the resolved `tag_id` back into the
	 * meta entry and links the originating video to the tag via `ms_video_tags`.
	 *
	 * Idempotent: a re-run only touches entries that don't already carry a
	 * non-zero `tag_id`. Self-gates by reading `ms_db_version` so installs
	 * already at v3+ short-circuit before any usermeta scan.
	 */
	private static function backfill_milestone_tags(): void {
		if ( (int) get_option( 'ms_db_version', 0 ) >= 3 ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot migration of legacy user meta records.
		$rows = $wpdb->get_results(
			"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_ms_video_tags'"
		);
		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$user_id   = (int) $row->user_id;
			$user_tags = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $user_tags ) ) {
				continue;
			}
			$changed = false;
			foreach ( $user_tags as $key => $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['tag'] ) ) {
					continue;
				}
				if ( ! empty( $entry['tag_id'] ) ) {
					continue; // Already migrated.
				}
				$tag_id = TagManager::ensure( (string) $entry['tag'], $user_id );
				if ( $tag_id > 0 ) {
					$user_tags[ $key ]['tag_id'] = $tag_id;
					if ( ! empty( $entry['video_id'] ) ) {
						TagManager::assign_to_video( (int) $entry['video_id'], $tag_id, $user_id );
					}
					$changed = true;
				}
			}
			if ( $changed ) {
				update_user_meta( $user_id, '_ms_video_tags', $user_tags );
			}
		}
	}
}
