=== MediaShield ===
Contributors: wbcomdesigns
Tags: video protection, watermark, video analytics, video player, video security
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
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
* **Email Gate.** Capture emails before video access with webhook integration for marketing tools.
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

= 1.0.0 =
Initial release of MediaShield.
