<?php
/**
 * GDPR Personal Data Eraser.
 *
 * Anonymizes watch sessions and removes activity alerts
 * for a given user email via the WordPress privacy erasure system.
 *
 * @package MediaShield\Privacy
 */

namespace MediaShield\Privacy;

class PrivacyEraser {

	/**
	 * Register the eraser.
	 */
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Add MediaShield eraser to the list.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['mediashield'] = array(
			'eraser_friendly_name' => __( 'MediaShield Video Data', 'mediashield' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Erase (anonymize) personal data for the given email.
	 *
	 * @param string $email Email address of the user.
	 * @param int    $page  Page number for pagination.
	 * @return array Erasure result per WordPress privacy spec.
	 */
	public static function erase( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;

		$items_removed  = 0;
		$items_retained = 0;

		// Anonymize watch sessions (keep aggregate data, remove PII).
		$sessions_table  = "{$wpdb->prefix}ms_watch_sessions";
		$sessions_updated = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$sessions_table} SET ip_address = '', user_agent = '' WHERE user_id = %d AND ( ip_address != '' OR user_agent != '' )",
				$user->ID
			)
		);

		$items_removed += $sessions_updated;

		// Sessions themselves are retained (anonymized, not deleted) for aggregate analytics.
		$total_sessions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$sessions_table} WHERE user_id = %d",
				$user->ID
			)
		);
		$items_retained += $total_sessions;

		// Remove activity alerts for this user (pro table, only if it exists).
		if ( defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
			$alerts_table = "{$wpdb->prefix}ms_activity_alerts";

			$table_exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
					DB_NAME,
					$alerts_table
				)
			);

			if ( $table_exists ) {
				$alerts_deleted = (int) $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$alerts_table} WHERE user_id = %d",
						$user->ID
					)
				);

				$items_removed += $alerts_deleted;
			}
		}

		return array(
			'items_removed'  => (bool) $items_removed,
			'items_retained' => (bool) $items_retained,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
