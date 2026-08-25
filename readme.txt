=== MediaShield ===
Contributors: wbcomdesigns
Tags: video protection, watermark, video analytics, video player, video security
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect your video content with dynamic watermarking, session tracking, multi-platform support, engagement analytics, and milestone automation.

== Description ==

MediaShield protects your video content on WordPress with dynamic watermarking, session-based access control, engagement analytics, and milestone tracking. It supports self-hosted videos, YouTube, Vimeo, Bunny Stream, and Wistia embeds, so you keep your existing video hosting.

= Video Protection =

* **Dynamic Watermarking.** User-specific watermarks (name plus IP) overlaid on every video to deter unauthorized screen recording. Watermark position swaps at configurable intervals.
* **Anti-Download Protection.** Right-click blocking, keyboard shortcut prevention, devtools detection, and source URL hiding for self-hosted videos.
* **Domain Whitelisting.** Restrict video embeds to approved domains only.

= Session Tracking and Access Control =

* **HMAC Session Tokens.** Cryptographic session validation, so the token itself proves who started the session. Good performance even on busy sites.
* **Concurrent Stream Limits.** Configurable max simultaneous streams per logged-in user. Default is 2.
* **Login-Gated Playback.** Require users to log in before watching videos, or turn it off and let guests watch with their views still recorded.
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
* **Self-Hosted.** Upload and protect MP4, WebM, MOV, and M4V files from Videos, Add New.

= Player Controls =

* **Speed Control.** Playback speed adjustment for self-hosted videos.
* **Keyboard Shortcuts.** Space, arrow keys, M (mute), F (fullscreen) when the player is focused.
* **Sticky Player.** Player sticks to a corner when scrolling past a playing video.
* **End Screen CTA.** Configurable call-to-action after video completion.
* **Per-Video Overrides.** Override global settings on individual videos.

= Developer Friendly =

* **Gutenberg Blocks.** Video, Playlist, and My Videos blocks with full block editor integration.
* **Shortcodes.** `[mediashield id=X]` for protected videos. `[mediashield_my_videos]` for watch history.
* **REST API.** 23 REST routes for tags, sessions, playlists, uploads, streaming, settings, and analytics.
* **Hooks and Filters.** 15 actions and 29 filters for custom integrations (LMS, CRM, etc.).
* **Output Buffer Detection.** Finds videos from your MediaShield library anywhere on the page, whatever wrote the markup, and wraps them. Videos MediaShield does not manage are left alone.

= Privacy and Compliance =

* **GDPR Compliant.** Built-in personal data exporter and eraser for WordPress privacy tools.
* **Data Anonymization.** PII is anonymized during erasure while aggregate analytics are retained.

= Pro Features =

[MediaShield Pro](https://wbcomdesigns.com/downloads/mediashield-pro/) extends the free plugin with:

* **Platform Connections.** Browse and bulk import videos from Bunny, YouTube, Vimeo, and Wistia.
* **DRM Encryption (experimental).** ClearKey DRM, software-based AES-128 encryption via Shaka Player, with Bunny Stream cloud packaging or local Shaka Packager. Selectable only once a packaging method is configured. Widevine L1 hardware DRM is not included.
* **Advanced Watermark.** 7 configurable fields: username, email, IP, user ID, timestamp, site name, custom text.
* **Heatmap Analytics.** Per-video playback heatmaps with 10-second position buckets and retention curves.
* **Realtime Dashboard.** Live active viewer count with 15-second auto-refresh.
* **Suspicious Activity Detection.** Multi-IP, devtools, and rapid seek detection with alert management. Optional VPN and proxy detection needs your own lookup API key.
* **Milestone Actions.** Tag user, send email, or fire webhook at completion milestones.
* **Weekly Digest.** Automated analytics summary email to site admins.
* **CSV and PDF Export.** Export watch sessions, milestones, and per-user summaries as CSV, or generate async PDF reports.

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

Yes, for videos you have added to MediaShield. There is no builder widget to drag in, but MediaShield scans the finished page and wraps any video that matches one in your library, matched on its platform video ID or its file URL. That works with Elementor, Beaver Builder, Divi, WPBakery, and any builder that outputs standard video or iframe elements. A video you never added to MediaShield is left exactly as the builder wrote it.

= Does it work with LMS plugins? =

Yes. MediaShield works alongside LearnDash, LifterLMS, Tutor LMS, Sensei, and other LMS plugins. Use the `mediashield_milestone_reached` action to integrate with LMS completion tracking.

= Does Hide Video Source URL work on YouTube and Vimeo? =

No, and it cannot. It applies to self-hosted files only: those play through a permission-checked address, and the file path never appears in the page. A YouTube, Vimeo, Wistia, or Bunny embed has to hand the browser its own video ID for the platform's player to work, so there is nothing to hide.

= Can guests watch without logging in? =

Yes. Turn off Require Login and logged-out visitors can watch, with their views and watch time recorded. Milestones still need a logged-in user, because there is no account to attach them to.

= How does the watermark work? =

MediaShield renders a dynamic canvas overlay on top of the video player showing the viewer's display name and IP address. The watermark position changes at configurable intervals to prevent easy removal. Pro extends this with 7 configurable fields.

= How are concurrent streams limited? =

Each logged-in user is allowed a configurable number of simultaneous video streams. Default is 2. When the limit is reached, the user must close another video before starting a new one. Sessions are tracked via HMAC tokens with 30-second heartbeats. Logged-out visitors are not limited, because there is no account for them to share.

= Will MediaShield slow down my site? =

No. MediaShield only loads its CSS and JavaScript on pages that contain video content. Pages without videos have zero performance impact. Session validation uses HMAC cryptography with no database lookups.

= Is it GDPR compliant? =

Yes. MediaShield registers personal data exporters and erasers with the WordPress privacy tools. Watch session PII (IP address, user agent) is anonymized during erasure while aggregate analytics are retained. Archived sessions are covered too, in both the export and the erasure.

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

= 1.3.0 - August 2026 =

* New      - Added a "Prevent Skipping Ahead" setting. Viewers cannot seek past the furthest point they have watched; rewinding still works. The feature was already built and shipped with no way to switch it on.
* New      - Self-hosted videos are matched to their MediaShield entry by source URL, so automatic detection protects them instead of skipping them.
* New      - Upload a video file directly from Add New Video, instead of uploading through the Media Library and pasting the URL back.
* New      - Bunny dashboard URLs are recognised. Pasting the address bar from Bunny Stream now detects the video instead of silently saving it as self-hosted with no ID.
* New      - The video editor says when a URL was not recognised, rather than quietly falling back to "Self-hosted".
* New      - Analytics Retention setting controls how long watch history stays in reports. Default is to keep everything.
* New      - wp mediashield repair bunny-urls repairs videos already saved from a Bunny dashboard URL. Dry-run by default.
* New      - Site Health check that asks your web server whether it will serve video files directly, and warns if it does. The folder rule MediaShield ships only works on Apache; on nginx it is ignored.
* Improve  - The Allowed Domains list now accepts one domain per line or comma-separated, and a pasted web address works too. Following the on-screen instruction produced a list nothing matched, which blocked playback on every other site instead of allowing it.
* Improve  - A video's protection level is now the same wherever it appears. A video set to Strict was served at the site default when it was picked up automatically on a page rather than added with the block or shortcode, and playlists ignored the site default entirely.
* Improve  - Changing the default protection level now applies to videos you already have. Every video was saved with a copy of whatever the default was on the day it was added, so changing it later moved nothing.
* Improve  - The "Require enrollment" and "Default Upload Target" settings now do something. Both saved and were read by nothing: enrollment was decided per video whatever the site setting said, and uploads ignored the chosen destination.
* Improve  - Uploaded video files are stored under unguessable names, so a file address cannot be worked out from the video title.
* Improve  - A video that cannot load now says so after 15 seconds instead of showing a player that never starts.
* Improve  - Analytics history is no longer archived automatically. Sessions older than 24 months used to be moved out of every report with no warning; anything already moved is restored on upgrade.
* Improve  - Ad rotation for in-video breaks is decided by WB Ad Manager instead of MediaShield, so the site's configured rotation model applies to video ads the same way it applies to banners.
* Improve  - Removed the Pro email gate coupling. The gate itself is gone from MediaShield Pro 1.3.0; the generic access-type extension point it used remains for other integrations.
* Improve  - Removed the Max Upload Size setting. WordPress already enforces the server upload limit before MediaShield sees the file, so the setting could only ever restrict uploads further, never allow more. Uploads now use whatever the site accepts, with no configuration.
* Improve  - An upload that exceeds the server limit now names the actual limit instead of reporting a size nobody configured.
* Improve  - A shortcode or block pointing at a missing, unpublished, or sourceless video now explains itself to anyone who can edit content, instead of rendering blank space. Visitors still see nothing.
* Improve  - Corrected the setup wizard, which said embeds pasted into a post are protected automatically. Only videos added to MediaShield are protected.
* Improve  - Removed the Custom URL Patterns setting. It asked for a regular expression, silently ignored any pattern containing a slash, and could not match a video in the first place.
* Improve  - The watermark Position Swap Interval no longer offers "0 = static", which was never honoured and produced a watermark that moved every second. The field now explains what the interval is for.
* Improve  - The Upload and Storage settings card is hidden when no cloud platform is connected, instead of rendering empty, and no longer claims that connecting a platform stops files being stored locally.
* Fix      - Deleting a video no longer deletes it from Bunny, Vimeo, YouTube or Wistia. Tidying a video list could destroy the original on a service you pay for, with no warning and no way to get it back. The video is now left in place, so you can add it again whenever you want.
* Fix      - Videos placed on a page as a plain Bunny embed are now protected. They were not recognised, so they played with no watermark, no protection and no viewing figures.
* Fix      - Deleting the plugin no longer removes your videos while the Pro version is still installed, and no longer leaves the video files behind on your server.
* Fix      - Repairing videos added from a Bunny dashboard address now makes them playable. The repair corrected the record but left it pointing at a page that cannot be played.
* Fix      - Require Login now works when turned off. Logged-out visitors can watch, and their views are recorded, instead of always seeing the login overlay.
* Fix      - Hide Video Source URL now hides the source URL. Self-hosted videos play through a permission-checked address; the file path no longer appears in the page. Does not apply to YouTube, Vimeo, Wistia or Bunny embeds, which expose their own IDs.
* Fix      - Two logged-out visitors watching the same video no longer share a session, which could hand one viewer another's playback position.
* Fix      - Logged-out visitors are no longer counted against each other's concurrent stream limit, which blocked the third simultaneous viewer of a public video.
* Fix      - Per-ad "Allow skip after" is honoured. Setting a skip delay on an individual video ad previously had no effect and the site default was always used.
* Fix      - Erasing a person's data now also clears IP addresses and user agents from archived sessions, and a data export now includes them.
* Fix      - Uploading a video file through the API works. Uploads were rejected as an invalid file type regardless of the file, which also affected the front-end upload form in Pro.
* Fix      - The plugin no longer fails to load from a fresh clone or a package built with development dependencies.
* Fix      - In-video ads no longer play past their Total Impressions limit. The break plan is now filled by WB Ad Manager, and the player checks with it immediately before each break, so a creative that runs out mid-video stops there instead of at the next page load.
* Fix      - In-video ads now count toward a visitor's per-ad Session Limit. That limit previously had no effect on this surface at any value.
* Fix      - A DRM-protected video now hands the encrypted player its playback address. The address was removed from the session response by an earlier security fix and never restored, so the encrypted player never started and the video played through the standard player unencrypted. Needs MediaShield Pro.
* Fix      - Videos that MediaShield does not manage are no longer wrapped in the protection player. Regular YouTube, Vimeo, Bunny and Wistia embeds now pass through untouched instead of receiving a watermark and protection overlay.
* Fix      - Plain self-hosted video tags no longer lose their source and stop playing on pages where MediaShield is active.
* Fix      - Turning off Enable MediaShield now renders videos unprotected instead of an empty player. Shortcodes, blocks and playlists all play normally with protection off.
* Fix      - Self-hosted uploads no longer fail on a default install. The Max Upload Size setting was measured in megabytes everywhere except the check that enforced it, so a default install advertised a 500 MB limit and rejected anything over 500 bytes.
* Fix      - Removed a WordPress 6.7 "translation loaded too early" notice that appeared when the monthly cron schedule was registered or the plugin was activated before the init hook.
* Dev      - Added the mediashield_protection_levels filter so extensions can register additional protection levels.
* Dev      - Added the mediashield_stored_filename filter for the on-disk name given to an uploaded video file.
* Dev      - /upload/init accepts a video_id to attach an upload to an existing video.
* Dev      - /stream/{id} accepts a signed ms_token, so media elements that cannot send an auth header can play.
* Dev      - Added EmbedLink::token() and EmbedLink::STREAM_TTL for signing playback URLs.
* Dev      - Test suite now runs. It required PHPUnit 10, which WordPress's own test suite does not support.
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

= 1.3.0 =
Require Login and Hide Video Source URL both work now, guests can watch with their views recorded, and analytics history is no longer archived out of your reports without asking. Videos are uploaded from Videos, Add New. Run Tools, Site Health after updating: on nginx your video files are directly downloadable and the new check tells you the rule to add. Install MediaShield Pro 1.3.0 at the same time.

= 1.1.0 =
Bug fixes, accessibility and UX polish, and a complete documentation set. Safe upgrade with no schema change beyond the v3 milestone-tags migration shipped in 1.0.0.

= 1.0.0 =
Initial release of MediaShield.
