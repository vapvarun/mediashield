<?php
/**
 * WB Ad Manager -> MediaShield in-video ad bridge.
 *
 * Pulls "video"-type ad creatives from WB Ad Manager (Pro) and builds the
 * pre / mid / post-roll break list MediaShield's player engine (ad-breaks.js)
 * consumes. Optional integration: when WB Ad Manager isn't active (or has no
 * video ads) the filter returns no breaks and the engine stays dormant, so
 * MediaShield never hard-depends on the ad plugin.
 *
 * Flow:
 *   Player\Renderer
 *     -> apply_filters( 'mediashield_video_ad_breaks', [], $video_id, $duration )
 *     -> emits data-ad-breaks JSON on .ms-protected-player + enqueues ad-breaks.js
 *   ad-breaks.js
 *     -> at each break: pause main video, overlay creative, countdown/skip, resume.
 *
 * @package MediaShield
 */

namespace MediaShield\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies in-video ad breaks sourced from WB Ad Manager video ads.
 */
class AdManagerBridge {

	/**
	 * Hook the break-supplier filter.
	 */
	public function register(): void {
		add_filter( 'mediashield_video_ad_breaks', array( $this, 'supply_breaks' ), 10, 3 );
		// Give the ad-breaks engine the endpoint to record impressions/clicks
		// back into WB Ad Manager analytics (the same admin-ajax action the rest
		// of the ad stack uses), so in-video plays show up in sponsor stats.
		add_action( 'wp_enqueue_scripts', array( $this, 'localize_tracking' ), 20 );
	}

	/**
	 * Localise the WB Ad Manager tracking endpoint for the engine.
	 */
	public function localize_tracking(): void {
		if ( ! wp_script_is( 'mediashield-ad-breaks', 'registered' ) ) {
			return;
		}
		wp_localize_script(
			'mediashield-ad-breaks',
			'mediashieldAds',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'action'          => 'wbam_track_event',
				// Asked immediately before each break: the ad plugin either
				// grants the impression (and counts it) or refuses because the
				// creative's total cap or the viewer's session limit is spent,
				// in which case the engine skips that break. The break plan is
				// built at page render, so it cannot know what is still
				// deliverable several minutes into playback.
				'claimAction'     => 'wbam_claim_video_impression',
				'nonce'           => wp_create_nonce( 'wbam_track' ),
				'placement'       => 'mediashield_video',
				// Mandatory viewing — engine hides the Skip button entirely.
				'requireFullView' => (bool) \MediaShield\Core\Settings::get( 'ms_ads_require_full_view' ),
				// Show the ad-break marker ruler under the player.
				'showMarkers'     => (bool) \MediaShield\Core\Settings::get( 'ms_ads_show_markers' ),
			)
		);
	}

	/**
	 * Build the ad-break list for a video from available video ads.
	 *
	 * @param array $breaks   Existing breaks (filter chain).
	 * @param int   $video_id MediaShield video post ID.
	 * @param int   $duration Video duration in seconds.
	 * @return array
	 */
	public function supply_breaks( $breaks, $video_id, $duration ) {
		$breaks   = is_array( $breaks ) ? $breaks : array();
		$video_id = (int) $video_id;
		$duration = (int) $duration;

		// Master switch — site owner can turn in-video ads off entirely.
		if ( ! \MediaShield\Core\Settings::get( 'ms_ads_enabled' ) ) {
			return $breaks;
		}

		$ads = $this->video_ads();

		/**
		 * Filter the ordered list of video-ad creatives used to build a video's
		 * breaks. The default is the global pool of every enabled WB Ad Manager
		 * video ad; MediaShield Pro hooks this to apply per-video explicit
		 * selection with a site-wide default-set fallback. Return an empty array
		 * to suppress ads for this video. Each item must keep the shape
		 * { id, label, video_url, click_url, skip_after }; order is preserved
		 * (breaks are filled round-robin in this order).
		 *
		 * @param array $ads      Global pool of enabled video-ad creatives.
		 * @param int   $video_id Video ID.
		 * @param int   $duration Duration in seconds.
		 */
		$ads = apply_filters( 'mediashield_video_ads', $ads, $video_id, $duration );
		$ads = is_array( $ads ) ? array_values( array_filter( $ads ) ) : array();
		if ( empty( $ads ) ) {
			return $breaks;
		}

		/**
		 * Ad-break plan. Default: a pre-roll plus up to 3 evenly-spaced
		 * mid-rolls (YouTube / Prime style), no post-roll. Site owners /
		 * per-video meta can override.
		 *
		 * @param array $plan     { pre:bool, mid_count:int, post:bool }
		 * @param int   $video_id Video ID.
		 * @param int   $duration Duration in seconds.
		 */
		$plan = apply_filters(
			'mediashield_ad_break_plan',
			array(
				'pre'       => (bool) \MediaShield\Core\Settings::get( 'ms_ads_preroll' ),
				'mid_count' => (int) \MediaShield\Core\Settings::get( 'ms_ads_midroll_count' ),
				'post'      => false,
			),
			$video_id,
			$duration
		);

		// Work out the slots this video needs, then let the ad plugin decide
		// which creative fills each one.
		$mid   = ( $duration > 0 ) ? max( 0, (int) ( $plan['mid_count'] ?? 0 ) ) : 0;
		$slots = array();

		if ( ! empty( $plan['pre'] ) ) {
			$slots[] = array(
				'type' => 'pre',
				'at'   => 0,
			);
		}

		if ( $mid > 0 ) {
			// Spread mid-rolls across the watchable middle (10%-90%) so an ad
			// never lands on the very first or last seconds.
			$start = $duration * 0.1;
			$span  = $duration * 0.8;
			for ( $i = 1; $i <= $mid; $i++ ) {
				$slots[] = array(
					'type' => 'mid',
					'at'   => (int) round( $start + ( $span * $i / ( $mid + 1 ) ) ),
				);
			}
		}

		if ( ! empty( $plan['post'] ) ) {
			$slots[] = array(
				'type' => 'post',
				'at'   => 0,
			);
		}

		$filled = $this->fill_slots( $ads, count( $slots ) );

		foreach ( $slots as $i => $slot ) {
			if ( ! isset( $filled[ $i ] ) ) {
				break; // Fewer creatives than slots — the rest stay empty.
			}
			$breaks[] = $this->to_break( $slot['type'], $slot['at'], $filled[ $i ] );
		}

		return array_values( array_filter( $breaks ) );
	}

	/**
	 * Ask WB Ad Manager which creative fills each break slot.
	 *
	 * MediaShield owns the player, not the inventory. Which creative goes in
	 * which slot depends on the site's rotation model and on how much of each
	 * ad's paid-for allowance is left — both of which are the ad plugin's to
	 * answer. Deciding it here instead used to mean rotation settings were
	 * ignored on this surface, and that one creative was repeated into every
	 * slot regardless of whether its impression cap could cover them.
	 *
	 * @param array $ads   Ordered creative pool.
	 * @param int   $count Slots to fill.
	 * @return array<int,array<string,mixed>>
	 */
	private function fill_slots( array $ads, $count ) {
		if ( $count < 1 || empty( $ads ) ) {
			return array();
		}

		// Held in a variable rather than written inline: the ad plugin is an
		// optional dependency that static analysis here cannot see, and the
		// class_exists() guard is what makes the call safe at runtime. Same
		// pattern as video_ads() above.
		$resolver = '\WBAM_Pro\Modules\AdTypes\Video_Ad_Resolver';

		if ( class_exists( $resolver ) && is_callable( array( $resolver, 'fill_slots' ) ) ) {
			return $resolver::fill_slots( $ads, $count, 'mediashield_video' );
		}

		// Older WB Ad Manager Pro without slot filling: one creative per slot,
		// no repeats. Under-filling is the safe direction — repeating without
		// the ad plugin's allowance check is what over-delivers.
		return array_slice( array_values( $ads ), 0, $count );
	}

	/**
	 * Fetch enabled "video"-type ads from WB Ad Manager.
	 *
	 * @return array<int,array{id:int,video_url:string,click_url:string,skip_after:int}>
	 */
	private function video_ads() {
		// Ask the ad plugin which creatives this viewer may be shown. Selection
		// and frequency capping are WB Ad Manager's rules to enforce, not ours —
		// querying `wbam-ad` here directly would silently bypass the per-ad
		// Session Limit and per-page cap that the same creative respects in a
		// banner slot, and would have to filter creative type in PHP (the type
		// lives inside a serialised blob), which drops video ads on any site
		// with more enabled ads than one page.
		//
		// No resolver means no video ads. MediaShield owns the player, not the
		// ad inventory, so it degrades to zero breaks rather than inventing its
		// own selection.
		if ( ! class_exists( '\WBAM_Pro\Modules\AdTypes\Video_Ad_Resolver' ) ) {
			return array();
		}

		$creatives = \WBAM_Pro\Modules\AdTypes\Video_Ad_Resolver::available();

		return is_array( $creatives ) ? $creatives : array();
	}

	/**
	 * Shape one ad break for the player engine.
	 *
	 * @param string     $type 'pre' | 'mid' | 'post'.
	 * @param int        $at   Mid-roll timecode (seconds); ignored for pre/post.
	 * @param array|null $ad   Creative row from video_ads().
	 * @return array|null
	 */
	private function to_break( $type, $at, $ad ) {
		if ( ! $ad ) {
			return null;
		}

		$open  = $ad['click_url'] ? '<a href="' . esc_url( $ad['click_url'] ) . '" target="_blank" rel="noopener sponsored">' : '';
		$close = $ad['click_url'] ? '</a>' : '';
		$html  = $open
			. '<video class="ms-ad-overlay__video" src="' . esc_url( $ad['video_url'] ) . '" playsinline preload="auto"></video>'
			. $close;

		// Skip policy, most specific wins:
		//
		//   1. Mandatory full-view removes the Skip button outright (null). This
		//      is a site-wide compliance switch (CLE and similar), so it still
		//      overrides everything - an advertiser must not be able to opt a
		//      viewer out of a rule the site owner set for legal reasons.
		//   2. The per-ad "Allow skip after" configured on the creative.
		//   3. The plugin-level default.
		//
		// (2) used to be skipped entirely: this read the global and threw the
		// creative's own value away, so setting 2s or 10s on an individual ad
		// did nothing and the player always counted down from the 5s default
		// (BC#10128639184). The creative row already carries skip_after - it is
		// in video_ads()'s own return docblock - so the setting was collected
		// and discarded rather than never available.
		$require_full = (bool) \MediaShield\Core\Settings::get( 'ms_ads_require_full_view' );

		if ( $require_full ) {
			$skip_after = null;
		} elseif ( isset( $ad['skip_after'] ) && '' !== $ad['skip_after'] && null !== $ad['skip_after'] ) {
			// Clamped to the same 0-60 range the global setting validates to, so
			// a bad value from the ad plugin cannot produce an ad that is
			// skippable at a negative offset or effectively never.
			$skip_after = max( 0, min( 60, (int) $ad['skip_after'] ) );
		} else {
			$skip_after = (int) \MediaShield\Core\Settings::get( 'ms_ads_skip_after' );
		}

		return array(
			'id'        => 'wbam-' . $ad['id'] . '-' . $type . '-' . (int) $at,
			'adId'      => (int) $ad['id'],
			'label'     => isset( $ad['label'] ) ? (string) $ad['label'] : '',
			'type'      => $type,
			'at'        => (int) $at,
			'skipAfter' => $skip_after,
			'html'      => $html,
		);
	}
}
