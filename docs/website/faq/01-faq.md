# Frequently Asked Questions

## General

**Which video platforms does MediaShield support?**

Five, out of the box: self-hosted files (MP4, WebM, MOV, M4V), YouTube, Vimeo, Bunny Stream, and Wistia. The free plugin plays and protects videos from all five, once the video is in your MediaShield library. Pro adds direct API connections for browsing, importing, and uploading through each platform's own API.

**Does MediaShield work with page builders?**

There is no Elementor or Divi widget, so nothing appears in a builder's widget panel. Put `[mediashield id=X]` in the builder's shortcode, text or HTML widget, or use the MediaShield Video block. A builder that prints an ordinary video or a YouTube, Vimeo, Bunny or Wistia embed for a video that is already in your library is also picked up and wrapped. Only videos in your MediaShield library are protected, and builders that render video with JavaScript after the page loads need the block or shortcode instead of a raw address. See [Integrations](../using-mediashield/18-integrations.md).

**Does it work with LMS plugins?**

Learnomy: yes, in Free. A Learnomy lesson can use a protected MediaShield video. See [Courses with Learnomy](../using-mediashield/15-courses-with-learnomy.md). LearnDash, Tutor LMS and LifterLMS: yes, with MediaShield Pro, which links a video to a lesson and can complete it and require enrolment. Other course plugins have no built-in connector. A developer can use the milestone hooks or the `mediashield_can_watch` filter. See [Integrations](../using-mediashield/18-integrations.md).

**What does MediaShield connect to?**

Video hosts (YouTube, Vimeo, Wistia, Bunny Stream, your own server), Learnomy, LearnDash, Tutor LMS, LifterLMS, WB Ad Manager for video ads, and any service that accepts a web address call (Zapier, Make) at a milestone. Membership plugins have no built-in connector. Each one is listed with its edition and setup steps in [Integrations](../using-mediashield/18-integrations.md).

**Will MediaShield slow down my site?**

Very little. The player CSS and JavaScript are registered on every page but only loaded when there is something to play: a MediaShield shortcode or block, or a page whose HTML contains a video or iframe element. A page with neither loads nothing. Session tokens are signed rather than looked up, so validating one costs no database query, though the player does record a heartbeat every 30 seconds while a video plays.

**Does it work on mobile?**

Yes. The watermark, player wrapping, session tracking, and playback all work on iOS Safari, Android Chrome, and modern mobile browsers. Two behaviours differ on small screens by design: the watermark shows the display name only (no IP) when the player is under 640 px wide, and developer-tools detection is disabled on touch devices and screens under 1024 px to avoid false positives.

**Can guests watch videos?**

Yes, if you turn off Settings > General > Require Login. Guests then play the video and their sessions are recorded, with the watermark showing "Guest" plus their IP. Concurrent stream limits are not applied to guests, since there is no account to share. Note that before 1.3.0 this setting did nothing when switched off - guests still met a login overlay.

**How do I set a video thumbnail?**

For platform videos (YouTube, Vimeo, Wistia, Bunny), the thumbnail is fetched automatically from the platform when the video is saved, unless you have already set a Featured Image. For self-hosted videos, MediaShield does not generate one - set the Featured Image on the video edit screen manually.

From 1.4.0 the featured image is also the video's poster: a self-hosted video shows it before playback starts, and a logged-out visitor sees it, dimmed, behind the login message instead of a black box. It also appears next to the video in a playlist.

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

When a viewer opens browser developer tools while watching, MediaShield detects it, records the event, and can optionally pause the video. In the free plugin the event is exposed as an action for your own logging - nothing is stored and there is no report screen. Pro records it as a suspicious-activity entry. Events are rate limited to one per viewer per hour, and detection is skipped on mobile and small screens.

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

**Does MediaShield work with GDPR requests?**

It supports them. MediaShield registers with WordPress's built-in privacy tools. Personal Data Export returns all watch sessions, milestones, and earned milestone tags for a user. Personal Data Erasure anonymizes IP addresses and user agents in watch sessions (the sessions themselves are kept for aggregate analytics), and deletes milestone records and the user's earned-tag data.

**What data does MediaShield collect?**

For each watch session: user ID, video ID, IP address, user agent, device type, browser, session start time, last heartbeat, total watch time, furthest position, and completion percentage. All data is stored in your own WordPress database. Nothing is sent to external servers.

## Pro and Licensing

**What happens if my Pro license expires?**

Your Pro features keep working. License status in MediaShield Pro controls update access only. When your license lapses, you stop receiving plugin updates, but every Pro feature continues working as configured. Renewal restores update access.

**Can I white-label the watermark?**

In the free plugin you can adjust opacity, color, and swap interval, but the watermark always shows display name and IP. Pro lets you choose from 7 fields (display name, email, IP, user ID, timestamp, site name, custom text) and set the font size.

**How do I remove the "Protected by MediaShield" badge?**

Settings > Watermark > Show MediaShield Badge. Toggle it off. Works in both free and Pro.

**Does MediaShield encrypt my videos?**

No. Encryption is done by the video host, for example Bunny Stream. MediaShield does not encrypt or package anything. MediaShield Pro can play a host-encrypted Bunny stream through its encrypted player, and that part is experimental. Do not build a launch around it. The Pro documentation explains the encrypted player in detail.

**What is the refund policy?**

The refund terms are published with the Pro product on wbcomdesigns.com. Email `support@wbcomdesigns.com` with your order details if you need help. The free plugin is GPL-licensed.

