/**
 * Platform auto-detection shared by the Gutenberg video block, the admin
 * Videos page, and the setup wizard. Centralises the host-matching rules so
 * adding a new platform only requires editing this file.
 *
 * @package MediaShield
 */

/**
 * Best-guess platform from a video URL.
 *
 * @param {string} url Public URL pasted by the site owner.
 * @return {string} One of: youtube, vimeo, bunny, wistia, self, iframe.
 */
export function detectPlatform( url ) {
	if ( ! url || typeof url !== 'string' ) return 'iframe';
	if ( /youtube\.com|youtu\.be|youtube-nocookie\.com/.test( url ) ) return 'youtube';
	if ( /vimeo\.com/.test( url ) ) return 'vimeo';
	if ( /iframe\.mediadelivery\.net|player\.mediadelivery\.net|b-cdn\.net/.test( url ) ) return 'bunny';
	if ( /wistia\.com|wistia\.net|wi\.st/.test( url ) ) return 'wistia';
	if ( /\.(mp4|webm|mov|m4v|ogv)(\?|$)/i.test( url ) ) return 'self';
	return 'iframe';
}

/**
 * Extract the platform-specific video ID from a URL.
 *
 * Bunny IDs are stored as `<library>/<guid>` so downstream consumers
 * (Renderer, block thumbnail, JS adapter) can build embed + thumbnail URLs
 * from a single value.
 *
 * @param {string} url      Source URL.
 * @param {string} platform Result of {@link detectPlatform}.
 * @return {string} ID, or empty string when we can't extract one.
 */
export function extractVideoId( url, platform ) {
	if ( ! url ) return '';

	switch ( platform ) {
		case 'youtube': {
			const m = url.match( /(?:embed\/|v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/ );
			return m ? m[ 1 ] : '';
		}
		case 'vimeo': {
			const m = url.match( /vimeo\.com\/(?:video\/)?(\d+)/ );
			return m ? m[ 1 ] : '';
		}
		case 'bunny': {
			// iframe.mediadelivery.net/embed/<libraryId>/<guid>
			// player.mediadelivery.net/embed/<libraryId>/<guid>
			// vz-<libraryId>.b-cdn.net/<guid>/playlist.m3u8
			let m = url.match( /mediadelivery\.net\/(?:embed\/|play\/)?(\d+)\/([a-f0-9-]{36})/i );
			if ( m ) return `${ m[ 1 ] }/${ m[ 2 ] }`;
			m = url.match( /vz-(\d+)\.b-cdn\.net\/([a-f0-9-]{36})/i );
			return m ? `${ m[ 1 ] }/${ m[ 2 ] }` : '';
		}
		case 'wistia': {
			// wistia.com/medias/<hashedId>
			// wistia.net/embed/iframe/<hashedId>
			// support.wistia.com/medias/<hashedId>
			// fast.wistia.net/embed/iframe/<hashedId>
			const m = url.match( /wistia\.(?:com|net)\/(?:medias|embed\/(?:iframe|playlists))\/([a-z0-9]{10,})/i );
			return m ? m[ 1 ] : '';
		}
		default:
			return '';
	}
}
