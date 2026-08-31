/**
 * MediaShield Admin -- REST URL helper.
 *
 * @package MediaShield
 */

const config = window.mediashieldAdmin || {};

/**
 * Build an absolute REST URL for the plugin namespace, with optional query args.
 *
 * `config.restUrl` is `rest_url()` output. On a site using plain permalinks --
 * WordPress's default on a fresh install -- that is
 * `index.php?rest_route=/mediashield/v1/`, which already carries a query
 * string. Concatenating `?arg=value` onto it produces a second `?`, so the
 * args fold into the `rest_route` value, no route matches, and the request
 * 404s. Appending with the correct separator works under either permalink
 * structure.
 *
 * @param {string} path Route path relative to the namespace, e.g. 'analytics/overview'.
 * @param {Object} args Optional query args. Empty values are omitted.
 * @return {string} A REST URL safe under any permalink structure.
 */
export const restUrl = ( path, args = {} ) => {
	const base = `${ config.restUrl }${ path }`;

	const query = Object.entries( args )
		.filter(
			( [ , value ] ) =>
				value !== undefined && value !== null && value !== ''
		)
		.map(
			( [ key, value ] ) =>
				`${ encodeURIComponent( key ) }=${ encodeURIComponent(
					value
				) }`
		)
		.join( '&' );

	if ( ! query ) {
		return base;
	}

	return `${ base }${ base.includes( '?' ) ? '&' : '?' }${ query }`;
};
