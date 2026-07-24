=== MediaShield ===
Contributors: wbcomdesigns
Tags: video protection, watermark, video analytics, video player, video security
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect your video content with dynamic watermarking, session tracking, multi-platform support, engagement analytics, and milestone automation.

== Description ==

MediaShield protects your video content on WordPress with dynamic watermarking, session-based access control, engagement analytics, and milestone tracking. It supports self-hosted videos, YouTube, Vimeo, Bunny Stream, and Wistia embeds, so you keep your existing video hosting.

= Video Protection =

* **Dynamic Watermarking.** User-specific watermarks (name plus IP) overlaid on every video to deter unauthorized screen recording. Watermark position swaps at configurable intervals.
* **Anti-Download Protection.** Right-click blocking, keyboard shortcut prevention, devtools detection, and source URL hiding.
* **Domain Whitelisting.** Restrict video embeds to approved domains only.

= Session Tracking and Access Control =

* **HMAC Session Tokens.** Cryptographic session validation without database lookups. Good performance even on busy sites.
* **Concurrent Stream Limits.** Configurable max simultaneous streams per user. Default is 2.
* **Login-Gated Playback.** Require users to log in before watching videos.
* **Role-Based Restriction.** Restrict specific videos to certain WordPress roles.
* **30-Second Heartbeat.** Continuous progress tracking with automatic session cleanup.

= Analytics and Milestones =

* **Analytics Dashboard.** Views, sessions, completion rates, active viewers, top videos, and per-user drill-down.
* **Milestone Tracking.** Fires actions at 25%, 50%, 75%, and 100% completion thresholds.
* **Per-Video Tags.** Assign tags to users at milestone completion for LMS and CRM integration.

= Multi-Platform Support =

* **YouTube.** Protect YouTube embeds with watermark overlay and session tracking.
* **Vimeo.** Protect Vimeo embeds with the full protection suite.
* **Bunny Stream.** Native support for Bunny.net video hosting.
* **Wistia.** Protect Wistia inline embeds.
* **Self-Hosted.** Upload and protect MP4, WebM, MOV, and M4V files.

= Player Controls =

* **Speed Control.** Playback speed adjustment for self-hosted videos.
* **Keyboard Shortcuts.** Space, arrow keys, M (mute), F (fullscreen) when the player is focused.
* **Sticky Player.** Player sticks to a corner when scrolling past a playing video.
* **End Screen CTA.** Configurable call-to-action after video completion.
* **Per-Video Overrides.** Override global settings on individual videos.

= Developer Friendly =

* **Gutenberg Blocks.** Video, Playlist, and My Videos blocks with full block editor integration.
* **Shortcodes.** `[mediashield id=X]` for protected videos. `[mediashield_my_videos]` for watch history.
* **REST API.** Full REST API for tags, sessions, playlists, uploads, settings, and analytics.
* **Hooks and Filters.** 8 actions and 8 filters for custom integrations (LMS, CRM, etc.).
* **Output Buffer Detection.** Automatically wraps video embeds from any page builder.

= Privacy and Compliance =

* **GDPR Compliant.** Built-in personal data exporter and eraser for WordPress privacy tools.
* **Data Anonymization.** PII is anonymized during erasure while aggregate analytics are retained.

= Pro Features =

[MediaShield Pro](https://wbcomdesigns.com/downloads/mediashield-pro/) extends the free plugin with:

* **Platform Connections.** Browse and bulk import videos from Bunny, YouTube, Vimeo, and Wistia.
* **DRM Encryption.** ClearKey DRM, software-based AES-128 encryption via Shaka Player, with Bunny Stream cloud packaging or local Shaka Packager. Widevine L1 hardware DRM is not included.
* **Advanced Watermark.** 7 configurable fields: username, email, IP, user ID, timestamp, site name, custom text.
* **Heatmap Analytics.** Per-video playback heatmaps with 10-second position buckets and retention curves.
* **Realtime Dashboard.** Live active viewer count with 15-second auto-refresh.
* **Suspicious Activity Detection.** Multi-IP, devtools, rapid seek, and VPN detection with alert management.
* **Milestone Actions.** Tag user, send email, or fire webhook at completion milestones.
* **Weekly Digest.** Automated analytics summary email to site admins.
* **CSV and PDF Export.** Export watch data as CSV or generate async PDF reports.
* **Frontend Upload.** `[mediashield_upload]` shortcode for user video submissions.
* **PWA Offline Playback.** Service Worker-based offline viewing for DRM-protected content.

== Installation ==

1. Upload the `mediashield` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Follow the setup wizard to configure your initial settings.
4. Create your first video under MediaShield, Videos.
5. For Pro features, install and activate MediaShield Pro separately.

= Requirements =

* WordPress 6.5 or higher
* PHP 8.1 or higher

== Frequently Asked Questions ==

= Which video platforms are supported? =

MediaShield supports self-hosted videos (MP4, WebM, MOV, M4V), YouTube, Vimeo, Bunny Stream, and Wistia. The free plugin detects and protects embeds from all platforms. Pro adds direct API connections for browsing, importing, and uploading.

= Does MediaShield work with page builders? =

Yes. MediaShield uses output buffering to detect and wrap video embeds automatically. It works with Elementor, Beaver Builder, Divi, WPBakery, and any builder that outputs standard video or iframe elements.

= Does it work with LMS plugins? =

Yes. MediaShield works alongside LearnDash, LifterLMS, Tutor LMS, Sensei, and other LMS plugins. Use the `mediashield_milestone_reached` action to integrate with LMS completion tracking.

= How does the watermark work? =

MediaShield renders a dynamic canvas overlay on top of the video player showing the viewer's display name and IP address. The watermark position changes at configurable intervals to prevent easy removal. Pro extends this with 7 configurable fields.

= How are concurrent streams limited? =

Each user is allowed a configurable number of simultaneous video streams. Default is 2. When the limit is reached, the user must close another video before starting a new one. Sessions are tracked via HMAC tokens with 30-second heartbeats.

= Will MediaShield slow down my site? =

No. MediaShield only loads its CSS and JavaScript on pages that contain video content. Pages without videos have zero performance impact. Session validation uses HMAC cryptography with no database lookups.

= Is it GDPR compliant? =

Yes. MediaShield registers personal data exporters and erasers with the WordPress privacy tools. Watch session PII (IP address, user agent) is anonymized during erasure while aggregate analytics are retained.

= Can I customize the access control logic? =

Yes. Use the `mediashield_can_watch` filter to implement custom access logic:

`add_filter( 'mediashield_can_watch', function( $allowed, $video_id, $user_id ) {`
`    // Your custom logic here`
`    return $allowed;`
`}, 10, 3 );`

= Does it support multisite? =

Yes. MediaShield is multisite-aware with per-site tables using `$wpdb->prefix`. Pro adds network-wide platform connections.

== Screenshots ==

1. Admin dashboard with video analytics overview
2. Video protection settings with per-video overrides
3. Dynamic watermark overlay on video player
4. Setup wizard for first-time configuration
5. Gutenberg Video block in the editor
6. Session tracking with concurrent stream management

== Changelog ==

= 1.2.0 - July 2026 =

* New      - Self-hosted videos are matched to their MediaShield entry by source URL, so automatic detection protects them instead of skipping them.
* Improve  - Ad rotation for in-video breaks is decided by WB Ad Manager instead of MediaShield, so the site's configured rotation model applies to video ads the same way it applies to banners.
* Improve  - Removed the Pro email gate coupling. The gate itself is gone from MediaShield Pro 1.2.0; the generic access-type extension point it used remains for other integrations.
* Improve  - Removed the Max Upload Size setting. WordPress already enforces the server upload limit before MediaShield sees the file, so the setting could only ever restrict uploads further, never allow more. Uploads now use whatever the site accepts, with no configuration.
* Improve  - An upload that exceeds the server limit now names the actual limit instead of reporting a size nobody configured.
* Improve  - A shortcode or block pointing at a missing, unpublished, or sourceless video now explains itself to anyone who can edit content, instead of rendering blank space. Visitors still see nothing.
* Improve  - Corrected the setup wizard, which said embeds pasted into a post are protected automatically. Only videos added to MediaShield are protected.
* Improve  - Removed the Custom URL Patterns setting. It asked for a regular expression, silently ignored any pattern containing a slash, and could not match a video in the first place.
* Improve  - The watermark Position Swap Interval no longer offers "0 = static", which was never honoured and produced a watermark that moved every second. The field now explains what the interval is for.
* Improve  - The Upload and Storage settings card is hidden when no cloud platform is connected, instead of rendering empty, and no longer claims that connecting a platform stops files being stored locally.
* Fix      - In-video ads no longer play past their Total Impressions limit. The break plan is now filled by WB Ad Manager, and the player checks with it immediately before each break, so a creative that runs out mid-video stops there instead of at the next page load.
* Fix      - In-video ads now count toward a visitor's per-ad Session Limit. That limit previously had no effect on this surface at any value.
* Fix      - Videos that MediaShield does not manage are no longer wrapped in the protection player. Regular YouTube, Vimeo, Bunny and Wistia embeds now pass through untouched instead of receiving a watermark and protection overlay.
* Fix      - Plain self-hosted video tags no longer lose their source and stop playing on pages where MediaShield is active.
* Fix      - Turning off Enable MediaShield now renders videos unprotected instead of an empty player. Shortcodes, blocks and playlists all play normally with protection off.
* Fix      - Self-hosted uploads no longer fail on a default install. The Max Upload Size setting was measured in megabytes everywhere except the check that enforced it, so a default install advertised a 500 MB limit and rejected anything over 500 bytes.
* Dev      - Added the mediashield_unprotected_player_html filter for the markup used while MediaShield is switched off.
* Dev      - Removed the unused data-ms-untracked attribute, which was written but never read.

= 1.1.0 - June 2026 =

Bug fixes, accessibility and UX polish, a complete documentation set, and new developer extension points. Safe upgrade with no schema change beyond the v3 migration shipped in 1.0.0.

* New      - Search and filter the Videos admin list by title.
* New      - Complete customer and developer documentation set covering getting started, configuration, everyday use, and the developer guide.
* New      - Player container honors a data-access-type attribute so extensions can declare alternative gate flows, which powers the Pro email gate.
* Improve  - Login overlay aria-label now reads the configured Login Overlay Text so screen readers announce the visible message.
* Improve  - Migrated all admin SPA, block, and meta-box icons from Dashicons to inline Lucide SVGs.
* Improve  - Videos admin table is responsive at 782px and below so action buttons no longer clip.
* Improve  - Copy-shortcode and sticky-player close buttons now meet the 40px tap-target minimum.
* Improve  - Duration field has an honest "leave 0 if unknown" label with server-side validation.
* Improve  - Admin success and error toasts render above the WordPress admin bar instead of behind it.
* Fix      - Unknown-platform embed URLs now route through an iframe adapter instead of rendering a broken video element.
* Fix      - Per-video email gate now works end to end for anonymous visitors: the toggle saves, the gate fires, captures the email, sets the cookie, and unlocks playback.
* Fix      - Resume Playback per-video override is now honored on the frontend.
* Fix      - "Last Active" and "Reached At" relative times use UTC consistently so they no longer drift with the site timezone.
* Fix      - Protection Level tiers behave distinctly again: basic skips session, watermark, and tracker, while strict forces devtools detection and source hiding.
* Fix      - Recent Milestones no longer renders the username and video title as one concatenated string.
* Fix      - Thumbnail sideload now handles extensionless CDN URLs from Vimeo, Wistia, and self-hosted sources.
* Fix      - Creating a video through the REST API now attaches the platform thumbnail on the first save instead of the second.
* Fix      - Thumbnail attachment now surfaces a WP_Error on failure instead of silently continuing.
* Fix      - Permanent video delete drops orphan tag rows and purges per-user milestone-tag meta for the deleted video.
* Fix      - Trashed videos are excluded from Top Videos, Milestones, and User Detail analytics.
* Fix      - The dashboard period selector now also filters the Recent Milestones panel.
* Dev      - New filters mediashield_player_access_type and mediashield_session_allow_anonymous_start, plus new hooks for frontend config, empty-referer policy, privacy erase, and the upload lifecycle.
* Dev      - The mediashield:access-denied DOM event is now cancelable so listeners can suppress the default overlay and render their own gate.
* Dev      - Registered _ms_milestone_tags in the REST schema so the admin SPA can save milestone-tag configuration.
* Dev      - Cleared all PHPStan level 5 errors and reduced WPCS to zero errors; the bundled EDD licensing SDK is excluded from linting.
* Dev      - Bundled the Action Scheduler and symfony/yaml runtime dependencies so a fresh clone activates without composer install.
* Dev      - Added three critical regression journeys for playlist rendering, video-delete cascade, and GDPR export and erase.

= 1.0.0 =
* Initial release.
* Video and Playlist custom post types with REST API support.
* Dynamic watermark overlay with configurable opacity, color, and swap interval.
* HMAC-based watch session tracking with concurrent stream limits.
* Engagement analytics dashboard with charts and user drill-down.
* Milestone tracking at 25%, 50%, 75%, and 100% completion.
* Multi-platform video detection (YouTube, Vimeo, Bunny Stream, Wistia, self-hosted).
* Anti-download protection (right-click blocking, devtools detection, source hiding).
* Player controls: speed, keyboard shortcuts, sticky player, end screen CTA.
* Gutenberg blocks for Video, Playlist, and My Videos.
* Shortcodes: `[mediashield]` and `[mediashield_my_videos]`.
* Self-hosted video upload with .htaccess protection and streaming proxy.
* Tag management system for video organization.
* Login overlay, domain restriction, and role-based access control.
* Setup wizard for first-time configuration.
* GDPR personal data exporter and eraser.
* Action Scheduler-based cron for session cleanup and archival.
* Full REST API with 22 routes across 6 controllers.
* Output buffer video detection for automatic embed wrapping.
* 8 actions and 8 filters for developer integrations.

== Upgrade Notice ==

= 1.1.0 =
Bug fixes, accessibility and UX polish, and a complete documentation set. Safe upgrade with no schema change beyond the v3 milestone-tags migration shipped in 1.0.0.

= 1.0.0 =
Initial release of MediaShield.
