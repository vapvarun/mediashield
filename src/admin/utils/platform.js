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
	// No b-cdn.net here: a pull-zone file names no library. It is saved as a
	// direct stream, and the server upgrades it to Bunny on save when the host
	// is one of this site's connected pull zones (Platforms::bunny_from_url).
	if ( /mediadelivery\.net|video\.bunnycdn\.com\/(?:embed|play)\/|dash\.bunny\.net\/stream\/\d+\/library\/[a-f0-9-]{36}/i.test( url ) ) return 'bunny';
	if ( /wistia\.com|wistia\.net|wi\.st/.test( url ) ) return 'wistia';
	if ( /\.(mp4|webm|mov|m4v|ogv|m3u8)(\?|$)/i.test( url ) ) return 'self';
	return 'iframe';
}

/**
 * Extract the platform-specific video ID from a URL.
 *
 * Bunny IDs are the bare video GUID - the same value the server stores
 * (Platforms::bunny_from_url() in PHP, which re-derives it on save).
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
			const m = url.match( /(?:(?:mediadelivery\.net|video\.bunnycdn\.com)\/(?:embed|play)\/\d+|dash\.bunny\.net\/stream\/\d+\/library)\/([a-f0-9-]{36})/i );
			return m ? m[ 1 ].toLowerCase() : '';
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
