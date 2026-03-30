/**
 * MediaShield Video Block — Frontend view (Interactivity API store).
 *
 * The actual player initialization (session start, watermark, tracker, protection)
 * is handled by the global vanilla JS scripts (player-wrapper.js, watermark.js, etc.)
 * which scan for .ms-protected-player elements. This view module provides the
 * Interactivity API store for block-specific state if needed.
 *
 * @package MediaShield
 */

/**
 * Interactivity API: minimal store for mediashield/video.
 * Extended by future features (playlist integration, block-specific controls).
 */
if ( window.wp && window.wp.interactivity ) {
	const { store } = window.wp.interactivity;

	store( 'mediashield/video', {
		state: {
			get isReady() {
				return true;
			},
		},
		actions: {},
	} );
}
