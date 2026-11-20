/**
 * Time labels shared by the admin screens.
 *
 * @package MediaShield
 */

/**
 * A video's length as m:ss, or h:mm:ss from an hour; an em dash when it is
 * unknown (0 or empty).
 *
 * @param {number|string} secs Seconds.
 * @return {string} Label, for example "4:05" or "1:02:09".
 */
export function formatDuration( secs ) {
	const s = parseInt( secs, 10 ) || 0;
	if ( s <= 0 ) {
		return '—';
	}
	const h = Math.floor( s / 3600 );
	const m = Math.floor( ( s % 3600 ) / 60 );
	const r = String( s % 60 ).padStart( 2, '0' );
	return h ? `${ h }:${ String( m ).padStart( 2, '0' ) }:${ r }` : `${ m }:${ r }`;
}
