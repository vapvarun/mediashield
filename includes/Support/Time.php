<?php
/**
 * Time display helpers: the one timezone and the one formatter.
 *
 * MediaShield stores every time in UTC. Showing one goes through these two
 * methods only, on admin screens, REST output, emails and PDFs alike, so one
 * moment never reads as two different days on two screens. MediaShield Pro
 * calls these; it never keeps its own copy (Wbcom time standard).
 *
 * @package MediaShield\Support
 * @since   1.4.3
 */

namespace MediaShield\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display timezone and date formatter.
 */
class Time {

	/**
	 * Which timezone is a time shown in? The site's, unless a site filters it.
	 *
	 * The one place MediaShield decides it. Use date_label() to format a time;
	 * call this only when you need the zone itself.
	 *
	 * @param int $user_id The person the time is shown to (the recipient for an
	 *                     email); 0 means the current user.
	 * @return \DateTimeZone
	 */
	public static function display_timezone( int $user_id = 0 ): \DateTimeZone {
		/**
		 * The timezone MediaShield shows times in. Return a member's own
		 * timezone to show each person their local time.
		 *
		 * @since 1.4.3
		 *
		 * @param \DateTimeZone $timezone The site's timezone.
		 * @param int           $user_id  Who the time is shown to.
		 */
		$timezone = apply_filters( 'mediashield_display_timezone', wp_timezone(), $user_id > 0 ? $user_id : get_current_user_id() );

		return $timezone instanceof \DateTimeZone ? $timezone : wp_timezone();
	}

	/**
	 * How is a stored UTC time shown to a person? Or the fallback when there is no time.
	 *
	 * The formatter for every time MediaShield shows. Do not call wp_date(),
	 * date_i18n() or mysql2date() anywhere else: CI fails on them. NULL, an empty
	 * string, the zero date and a value that resolves to a non-positive
	 * timestamp all give the fallback. A string without an offset is read as UTC.
	 *
	 * @param string|int|null $utc      A stored UTC datetime, or a Unix timestamp.
	 * @param string          $fallback Shown when there is no usable time.
	 * @param string|null     $format   PHP date format; the site's date format by default.
	 * @param int             $user_id  Who it is shown to (see display_timezone()).
	 * @return string
	 */
	public static function date_label( $utc, string $fallback = '', ?string $format = null, int $user_id = 0 ): string {
		if ( is_int( $utc ) ) {
			$timestamp = $utc;
		} else {
			$utc = null === $utc ? '' : trim( (string) $utc );
			if ( '' === $utc || 0 === strncmp( $utc, '0000-00-00', 10 ) ) {
				return $fallback;
			}
			$timestamp = strtotime( $utc . ( preg_match( '/(Z|[+-]\d{2}:?\d{2}|UTC|GMT)$/i', $utc ) ? '' : ' UTC' ) );
		}
		if ( false === $timestamp || $timestamp <= 0 ) {
			return $fallback;
		}

		return (string) wp_date( $format ?? (string) get_option( 'date_format' ), $timestamp, self::display_timezone( $user_id ) );
	}
}
