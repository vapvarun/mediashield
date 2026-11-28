/**
 * The protection levels in the words every screen uses.
 *
 * PHP owns the words (Protection::levels(), printed as
 * mediashieldAdmin.protectionLevels); the English here is only a fallback for
 * a page whose config predates 1.4.4.
 *
 * @package MediaShield
 */
import { __, sprintf } from '@wordpress/i18n';

const FALLBACK = {
	none: { label: 'None', summary: 'The video plays as the platform shows it.', adds: 'The video plays as the platform shows it.' },
	basic: { label: 'Basic', summary: 'Right-click and download blocked.', adds: 'Right-click and download blocked.' },
	standard: { label: 'Standard', summary: 'Right-click and download blocked, a watermark with the viewer\'s name, and each playback tracked.', adds: 'Plus a watermark and tracked playback.' },
	strict: { label: 'Strict', summary: 'Everything in Standard, plus the file address hidden from the page and developer tools detected.', adds: 'Plus file address hidden and developer tools detected.' },
};

/**
 * Level slug => { label, summary, adds }, weakest first.
 *
 * @return {Object} Levels.
 */
export function protectionLevels() {
	const levels = window.mediashieldAdmin?.protectionLevels;
	return levels && levels.none ? levels : FALLBACK;
}

/**
 * The site's default level slug.
 *
 * @return {string} Slug.
 */
export function defaultLevel() {
	return window.mediashieldAdmin?.defaultProtection || 'standard';
}

/**
 * "Site default (Standard)", for the current default.
 *
 * @return {string} Label.
 */
export function siteDefaultLabel() {
	const levels = protectionLevels();
	const def = defaultLevel();
	return sprintf(
		/* translators: %s: the site-wide protection level, e.g. Standard. */
		__( 'Site default (%s)', 'mediashield' ),
		levels[ def ]?.label || def
	);
}

/**
 * Select options: Site default first, then the four levels.
 *
 * @param {boolean} withDefault Include "Site default (X)" as value 'default'.
 * @return {Array<{value: string, label: string}>} Options.
 */
export function protectionOptions( withDefault = true ) {
	const levels = protectionLevels();
	const opts = Object.keys( levels ).map( ( value ) => ( { value, label: levels[ value ].label } ) );
	return withDefault ? [ { value: 'default', label: siteDefaultLabel() }, ...opts ] : opts;
}
