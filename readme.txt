=== MediaShield ===
Contributors: wbcomdesigns
Tags: video protection, watermark, video analytics, video player, video security
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Video protection for WordPress — dynamic watermarking, multi-platform support, engagement analytics, and milestone automation.

== Description ==

MediaShield protects your video content on WordPress with dynamic watermarking, session-based access control, engagement analytics, and milestone tracking. It supports self-hosted videos, YouTube, Vimeo, Bunny Stream, and Wistia embeds.

= Key Features =

* **Dynamic Watermarking** — Overlay user-specific watermarks (name + IP) on every video to deter unauthorized screen recording.
* **Multi-Platform Support** — Protect videos from YouTube, Vimeo, Bunny Stream, Wistia, and self-hosted sources.
* **Watch Session Tracking** — HMAC-based session tokens with concurrent stream limits and automatic heartbeat tracking.
* **Engagement Analytics** — Dashboard with session counts, completion rates, active viewers, top videos, and per-user drill-down.
* **Milestone Automation** — Fire actions at configurable completion thresholds (25%, 50%, 75%, 100%) for integrations like email notifications and webhooks.
* **Access Control** — Login-gated playback, domain restrictions, and role-based access via the `mediashield_can_watch` filter.
* **Anti-Download Protection** — Right-click blocking, keyboard shortcut prevention, and source URL hiding.
* **Gutenberg Blocks** — Video, Playlist, and My Videos blocks with full block editor integration.
* **Shortcodes** — `[mediashield id=X]` for embedding protected videos and `[mediashield_my_videos]` for user watch history.
* **GDPR Compliant** — Built-in personal data exporter and eraser for WordPress privacy tools.
* **Setup Wizard** — Guided first-activation setup for quick configuration.
* **REST API** — Full REST API for tags, sessions, playlists, uploads, settings, and analytics.

= Pro Features =

MediaShield Pro extends the free plugin with:

* DRM-protected playback via Shaka Player
* Advanced watermark options (email, timestamp, custom text, font size)
* Platform-specific upload drivers (Bunny, Vimeo, YouTube, Wistia)
* Heatmap analytics and activity alerts
* Role-based access control and email gating

== Installation ==

1. Upload the `mediashield` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Follow the setup wizard to configure your initial settings.
4. Create your first video under MediaShield > Videos.

= Requirements =

* WordPress 6.5 or higher
* PHP 8.1 or higher

== Frequently Asked Questions ==

= Which video platforms are supported? =

MediaShield supports self-hosted videos (MP4, WebM, MOV, M4V), YouTube, Vimeo, Bunny Stream, and Wistia.

= Does MediaShield work with page builders? =

Yes. MediaShield uses output buffering to detect and wrap video embeds automatically, so it works with any page builder that outputs standard video or iframe elements.

= How does the watermark work? =

MediaShield renders a dynamic canvas overlay on top of the video player showing the viewer's display name and IP address. The watermark position changes at configurable intervals to prevent easy removal.

= How are concurrent streams limited? =

Each user is allowed a configurable number of simultaneous video streams (default: 2). When the limit is reached, the user must close another video before starting a new one.

= Is it GDPR compliant? =

Yes. MediaShield registers personal data exporters and erasers with the WordPress privacy tools. Watch session PII (IP address, user agent) is anonymized during erasure while retaining aggregate analytics.

== Screenshots ==

1. Admin dashboard with video analytics overview
2. Video protection settings
3. Dynamic watermark overlay on video player
4. Setup wizard

== Changelog ==

= 1.0.0 =
* Initial release.
* Video and Playlist custom post types with REST API support.
* Dynamic watermark overlay with configurable opacity, color, and swap interval.
* HMAC-based watch session tracking with concurrent stream limits.
* Engagement analytics dashboard with charts and user drill-down.
* Milestone tracking at 25%, 50%, 75%, and 100% completion.
* Multi-platform video detection (YouTube, Vimeo, Bunny Stream, Wistia, self-hosted).
* Anti-download protection (right-click blocking, devtools detection).
* Gutenberg blocks for Video, Playlist, and My Videos.
* Shortcodes: `[mediashield]` and `[mediashield_my_videos]`.
* Self-hosted video upload with .htaccess protection.
* Tag management system for video organization.
* Login overlay and domain restriction access control.
* Setup wizard for first-time configuration.
* GDPR personal data exporter and eraser.
* Action Scheduler-based cron for session cleanup and archival.
* Full REST API for all plugin features.

== Upgrade Notice ==

= 1.0.0 =
Initial release of MediaShield.
