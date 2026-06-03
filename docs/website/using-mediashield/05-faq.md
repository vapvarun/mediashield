# Frequently Asked Questions

## General

**Which video platforms does MediaShield support?**

Five platforms out of the box: Self-hosted (MP4, WebM, MOV, M4V files), YouTube, Vimeo, Bunny Stream, and Wistia. The free plugin detects and protects embeds from all five. Pro adds direct API connections for browsing, importing, and uploading through each platform's own API.

**Does MediaShield work with page builders?**

Yes. MediaShield uses output buffering to detect and wrap video embeds automatically regardless of how they're inserted. It works with Elementor, Beaver Builder, Divi, WPBakery, and any builder that outputs standard `<video>` or `<iframe>` elements. If your builder renders videos via JavaScript after page load, use the MediaShield block or shortcode directly instead of pasting a raw URL.

**Does it work with LMS plugins?**

Yes. MediaShield works alongside LearnDash, LifterLMS, Tutor LMS, Sensei, and others. The free plugin fires milestone actions that you can hook for LMS completion tracking. Pro ships pre-built adapters for LearnDash, LifterLMS, and TutorLMS.

**Will MediaShield slow down my site?**

No. MediaShield only loads its CSS and JavaScript on pages that contain video content. Session validation uses signed tokens so your site does not query the database on every check. Pages without videos have zero performance impact.

**Does it work on mobile?**

Yes. The watermark, player wrapping, session tracking, and playback all work on iOS Safari, Android Chrome, and modern mobile browsers. Developer-tools detection is intentionally disabled on devices under 1024 px wide to avoid false positives from on-screen keyboards and orientation changes.

**How do I set a video thumbnail?**

For platform videos (YouTube, Vimeo, Wistia, Bunny), the thumbnail is fetched automatically from the platform when the video is saved. For self-hosted videos, MediaShield does not auto-generate a thumbnail - set the Featured Image on the video edit screen manually.

**Does it work with caching plugins?**

Yes, with one configuration step: exclude `/wp-json/mediashield/` from your cache. Most caching plugins handle REST API exclusion by default. Check your caching plugin's documentation. For Cloudflare, add a Page Rule to bypass cache for `*yoursite.com/wp-json/*`.

**Does it work with Cloudflare or a CDN?**

Yes. Admin and REST endpoints use WordPress authentication and are not cached by CDNs. Frontend assets are versioned and safe to cache. For self-hosted video streaming, add a bypass rule for the stream endpoint so the CDN does not interfere.

## Video Protection

**How does the watermark work?**

MediaShield renders a canvas overlay on top of the video showing the viewer's display name and IP address. The watermark moves to a new position at configurable intervals, stays visible in fullscreen, and re-renders automatically if the DOM is modified.

**Can viewers still screen-record my videos?**

MediaShield makes screen recording traceable, not impossible. No web software can block screen recording. The dynamic watermark with the viewer's identity means any leaked recording can be traced back to who made it. For the full explanation of what protection can and cannot do, see the Protection Philosophy in the Introduction section.

**What does developer-tools detection do?**

When a viewer opens browser developer tools while watching, MediaShield detects it and can optionally pause the video. The event is logged and Pro adds it as a suspicious activity alert. This deters casual attempts to inspect video source URLs. Detection is disabled on mobile and small screens to avoid false positives.

**Can I disable protection for a specific video?**

Yes. Set the per-video Protection Level to "None" on the video edit screen. That video plays without any protection layer while all other videos keep their settings.

## Sessions and Access

**How do concurrent stream limits work?**

Each user can watch on a set number of devices at once. Default is 2. MediaShield tracks active sessions via heartbeat pings every 30 seconds. When a viewer tries to start a stream beyond the limit, they see an error and need to close another video first. Sessions without a heartbeat for 5 minutes are automatically expired.

**Can I revoke a user's access?**

Yes. Admins can revoke all active sessions for a user from the Students admin page. This immediately terminates all their active video streams.

## GDPR and Privacy

**Is MediaShield GDPR compliant?**

Yes. MediaShield registers with WordPress's built-in privacy tools. Personal Data Export returns all watch sessions, milestones, and tags for a user. Personal Data Erasure anonymizes IP addresses and user agents in watch sessions and deletes milestones and tag assignments.

**What data does MediaShield collect?**

For each watch session: user ID, video ID, IP address, user agent, device type, browser, session start time, last heartbeat, total watch time, furthest position, and completion percentage. All data is stored in your own WordPress database. Nothing is sent to external servers.

## Pro and Licensing

**What happens if my Pro license expires?**

Your Pro features keep working. License status in MediaShield Pro controls update access only. When your license lapses, you stop receiving plugin updates, but every Pro feature continues working as configured. Renewal restores update access.

**Can I white-label the watermark?**

In the free plugin you can adjust opacity, color, and swap interval, but the watermark always shows display name and IP. Pro lets you choose from 7 fields and add custom text.

**How do I remove the "Protected by MediaShield" badge?**

Settings > General > Show Badge. Toggle it off. Works in both free and Pro.

**What is the refund policy?**

14-day money-back guarantee on MediaShield Pro. Email `support@wbcomdesigns.com` with your license key for a refund within 48 hours. The free plugin is GPL-licensed.
