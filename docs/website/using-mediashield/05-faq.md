# Frequently Asked Questions

## General

**Which video platforms does MediaShield support?**

Five, out of the box: self-hosted files (MP4, WebM, MOV, M4V), YouTube, Vimeo, Bunny Stream, and Wistia. The free plugin protects embeds from all five. Pro adds direct API connections for browsing, importing, and uploading through each platform's own API.

**Does MediaShield work with page builders?**

Yes. MediaShield scans page output and wraps video embeds automatically, regardless of how they were inserted, so it works with Elementor, Beaver Builder, Divi, WPBakery, and anything else that outputs standard `<video>` or `<iframe>` elements. Two caveats: only videos that exist in your MediaShield library are wrapped, and builders that render video via JavaScript after page load need the MediaShield block or shortcode instead of a raw URL.

**Does it work with LMS plugins?**

Yes. MediaShield works alongside LearnDash, LifterLMS, Tutor LMS, Sensei, and others. The free plugin fires milestone actions that you can hook for LMS completion tracking. Pro ships pre-built adapters for LearnDash, LifterLMS, and TutorLMS.

**Will MediaShield slow down my site?**

Very little. The player CSS and JavaScript are registered on every page but only loaded when there is something to play: a MediaShield shortcode or block, or a page whose HTML contains a video or iframe element. A page with neither loads nothing. Session tokens are signed rather than looked up, so validating one costs no database query, though the player does record a heartbeat every 30 seconds while a video plays.

**Does it work on mobile?**

Yes. The watermark, player wrapping, session tracking, and playback all work on iOS Safari, Android Chrome, and modern mobile browsers. Two behaviours differ on small screens by design: the watermark shows the display name only (no IP) when the player is under 640 px wide, and developer-tools detection is disabled on touch devices and screens under 1024 px to avoid false positives.

**Can guests watch videos?**

Yes, if you turn off Settings > General > Require Login. Guests then play the video and their sessions are recorded, with the watermark showing "Guest" plus their IP. Concurrent stream limits are not applied to guests, since there is no account to share. Note that before 1.3.0 this setting did nothing when switched off - guests still met a login overlay.

**How do I set a video thumbnail?**

For platform videos (YouTube, Vimeo, Wistia, Bunny), the thumbnail is fetched automatically from the platform when the video is saved, unless you have already set a Featured Image. For self-hosted videos, MediaShield does not generate one - set the Featured Image on the video edit screen manually.

**Does it work with caching plugins?**

Yes, with one configuration step: exclude `/wp-json/mediashield/` from your cache. Most caching plugins handle REST API exclusion by default. Check your caching plugin's documentation. For Cloudflare, add a Page Rule to bypass cache for `*yoursite.com/wp-json/*`.

**Does it work with Cloudflare or a CDN?**

Yes. Admin and REST endpoints use WordPress authentication and are not cached by CDNs. Frontend assets are versioned and safe to cache. For self-hosted video streaming, add a bypass rule for the stream endpoint so the CDN does not interfere.

## Video Protection

**How does the watermark work?**

MediaShield draws a canvas overlay on top of the video showing the viewer's display name and IP address. It cycles through five positions - four corners and the center - at an interval you choose, stays visible in fullscreen, and pauses playback if the overlay is removed or hidden.

**Can viewers still screen-record my videos?**

MediaShield makes screen recording traceable, not impossible. No web software can block screen recording. The dynamic watermark with the viewer's identity means any leaked recording can be traced back to the account that was watching. For the full explanation of what protection can and cannot do, see "What MediaShield does not promise" in the [Introduction](../getting-started/01-introduction.md).

**Are my uploaded video files really protected?**

On Apache, yes: MediaShield writes an `.htaccess` deny rule into `wp-content/uploads/mediashield/`, so the files can only be watched through the permission-checked player.

On nginx, `.htaccess` is ignored and those files are directly downloadable until you add a rule to your server config. MediaShield does not assume - it asks your own server for one of your video files and reports the result under **Tools > Site Health**, with the exact nginx rule to add if the answer is bad. Adding it does not affect playback, because the player streams through MediaShield rather than requesting that path.

**What does developer-tools detection do?**

When a viewer opens browser developer tools while watching, MediaShield detects it, records the event, and can optionally pause the video. In the free plugin the event is written to the WordPress debug log and exposed as an action for your own logging - there is no report screen. Pro turns it into a suspicious activity alert. Events are rate limited to one per viewer per hour, and detection is skipped on mobile and small screens.

**Can I disable protection for a specific video?**

Yes. Set that video's Protection Level to "None". It then plays with no gate, no watermark, and no session tracking, while every other video keeps its settings. Bear in mind that "Basic" also skips the watermark and records nothing - if you want analytics, use Standard or Strict.

**If I delete a video in MediaShield, is it deleted from Bunny, Vimeo, YouTube or Wistia?**

No. Deleting a video in MediaShield removes this site's record of it and nothing else. The original stays on the platform, so you can add it back at any time from Videos > Import.

That is deliberate. Those services have no trash and no undo, so a mistake would be permanent, and the master is usually something you pay to store and may be using elsewhere. If you genuinely want a video gone from the platform, delete it in that platform's own dashboard, where you can see what else uses it.

The one exception is a **self-hosted** video: that file lives in this site's own uploads folder, was put there by MediaShield, and is deleted along with the video.

## Sessions and Access

**How do concurrent stream limits work?**

Each account can watch on a set number of devices at once. Default is 2. The player sends a heartbeat every 30 seconds, and a session that has not sent one for 5 minutes stops counting toward the limit. A viewer who exceeds the limit is told "Too many active streams. Please close another video first." Guests are not counted.

**Can I revoke a user's access?**

Yes, though not from a button in this release. MediaShield exposes an administrator REST endpoint that ends every active session for one account:

```
POST /wp-json/mediashield/v1/session/revoke-user
{ "user_id": 42 }
```

Their streams stop immediately. To stop them starting new ones, change what they are entitled to watch or deactivate the account.

## GDPR and Privacy

**Is MediaShield GDPR compliant?**

Yes. MediaShield registers with WordPress's built-in privacy tools. Personal Data Export returns all watch sessions, milestones, and earned milestone tags for a user. Personal Data Erasure anonymizes IP addresses and user agents in watch sessions (the sessions themselves are kept for aggregate analytics), and deletes milestone records and the user's earned-tag data.

**What data does MediaShield collect?**

For each watch session: user ID, video ID, IP address, user agent, device type, browser, session start time, last heartbeat, total watch time, furthest position, and completion percentage. All data is stored in your own WordPress database. Nothing is sent to external servers.

## Pro and Licensing

**What happens if my Pro license expires?**

Your Pro features keep working. License status in MediaShield Pro controls update access only. When your license lapses, you stop receiving plugin updates, but every Pro feature continues working as configured. Renewal restores update access.

**Can I white-label the watermark?**

In the free plugin you can adjust opacity, color, and swap interval, but the watermark always shows display name and IP. Pro lets you choose from 7 fields (display name, email, IP, user ID, timestamp, site name, custom text) and set the font size.

**How do I remove the "Protected by MediaShield" badge?**

Settings > Watermark > Show MediaShield Badge. Toggle it off. Works in both free and Pro.

**Is the Pro DRM feature ready to use?**

Treat it as a preview. Before 1.3.0 it could not be enabled at all, because nothing in the admin could store the DRM protection level. It is selectable now, but playback has not been verified end to end, so do not build a launch around it.

**What is the refund policy?**

14-day money-back guarantee on MediaShield Pro. Email `support@wbcomdesigns.com` with your license key for a refund within 48 hours. The free plugin is GPL-licensed.
