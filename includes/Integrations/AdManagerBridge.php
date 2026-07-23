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

		// Round-robin through the available creatives so breaks don't repeat
		// the same ad back-to-back when several are configured.
		$queue = $ads;
		$pick  = function () use ( &$queue, $ads ) {
			if ( empty( $queue ) ) {
				$queue = $ads;
			}
			return array_shift( $queue );
		};

		if ( ! empty( $plan['pre'] ) ) {
			$breaks[] = $this->to_break( 'pre', 0, $pick() );
		}

		$mid = (int) ( $plan['mid_count'] ?? 0 );
		if ( $mid > 0 && $duration > 0 ) {
			// Spread mid-rolls across the watchable middle (10%-90%) so an ad
			// never lands on the very first or last seconds.
			$start = $duration * 0.1;
			$span  = $duration * 0.8;
			for ( $i = 1; $i <= $mid; $i++ ) {
				$at       = (int) round( $start + ( $span * $i / ( $mid + 1 ) ) );
				$breaks[] = $this->to_break( 'mid', $at, $pick() );
			}
		}

		if ( ! empty( $plan['post'] ) ) {
			$breaks[] = $this->to_break( 'post', 0, $pick() );
		}

		return array_values( array_filter( $breaks ) );
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

		// Skip policy is plugin-level, not per-ad: mandatory full-view removes the
		// Skip button outright (null); otherwise the global delay applies.
		$require_full = (bool) \MediaShield\Core\Settings::get( 'ms_ads_require_full_view' );
		$skip_after   = $require_full ? null : (int) \MediaShield\Core\Settings::get( 'ms_ads_skip_after' );

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
