# MediaShield Frequently Asked Questions

## General

### Which video platforms does MediaShield support?

MediaShield supports five platforms out of the box:

* **Self-hosted:** MP4, WebM, MOV, M4V files uploaded to your WordPress site.
* **YouTube:** any public or unlisted YouTube video.
* **Vimeo:** standard and Pro Vimeo embeds.
* **Bunny Stream:** Bunny.net video hosting (API connection requires Pro).
* **Wistia:** Wistia inline embeds.

The free plugin protects videos from all five platforms. Pro adds direct API connections for browsing, importing, and uploading.

### Does MediaShield protect every video embed on my site?

No, and this is the most important thing to understand about how it works. **MediaShield only protects videos you have added to your MediaShield library.** An embed pasted straight into a post is left exactly as it was.

MediaShield does scan each page as it renders, and it will take over a video embed that wasn't placed with a shortcode or block -- but only when it can match that embed to one of your library videos, by platform video ID for YouTube / Vimeo / Bunny / Wistia, or by the exact file address for a self-hosted video. Anything it can't match is left untouched, on purpose: wrapping a video that isn't yours would stamp your viewer's name and IP onto someone else's content.

So the sequence is always the same: add the video to MediaShield first, then either place it with the block or shortcode, or leave the existing embed where it is for MediaShield to pick up.

### Does MediaShield work with page builders?

Yes. Once a video is in your MediaShield library, MediaShield takes over its embed however the page put it there. It works with Elementor, Beaver Builder, Divi, WPBakery, and any builder that outputs standard `<video>` or `<iframe>` elements. Builders that render the video with JavaScript after the page has loaded are the exception -- for those, place the video with the MediaShield block or `[mediashield id=X]` shortcode.

### Does it work with LMS plugins?

Yes. MediaShield works alongside LearnDash, LifterLMS, Tutor LMS, Sensei, and other LMS plugins. Use the `mediashield_milestone_reached` action to integrate with LMS completion tracking:

```php
add_action( 'mediashield_milestone_100', function( $user_id, $video_id ) {
    // Mark lesson complete when video reaches 100%.
}, 10, 2 );
```

### Will MediaShield slow down my site?

No. MediaShield loads its CSS and JavaScript only on pages that contain video or iframe markup -- a page with neither loads nothing at all. (The test is deliberately broad: a page carrying an unrelated iframe, such as an embedded map, will also load MediaShield's assets, because MediaShield has to load before it can tell whether an embed is one of yours.) Session validation uses signed tokens, so your site doesn't query the database on every check, and even high-traffic sites scale cleanly.

### Does it work on mobile?

Yes. The watermark overlay, player wrapping, session tracking, and playback all work on iOS Safari, Android Chrome, and every modern mobile browser. Notes:

* **Developer-tools detection is intentionally disabled on touch or small-screen devices** (under 1024 px wide) to avoid false positives from on-screen keyboards and orientation changes.
* **Safari (including iOS)** does not support the ClearKey encryption Pro uses elsewhere, so encrypted Pro content falls back to HLS with AES-128. Playback works.
* **Mobile screen recording** (iOS and Android built-in) cannot be prevented by any web plugin. The watermark stays visible in any recording, which is the forensic deterrent.

### How do I set a video thumbnail (poster image)?

It depends on where the video is hosted:

* **Platform videos (YouTube, Vimeo, Wistia, Bunny):** the thumbnail is fetched automatically. MediaShield pulls the poster the platform already generated for that video and sets it as the WordPress **Featured Image** when the video is saved. Nothing to do.
* **Self-hosted videos (`.mp4` you upload yourself):** there is no platform poster to fetch, so MediaShield does **not** auto-generate one. Set the **Featured Image** on the video manually (Video edit screen, Featured Image box) and it will be used as the poster in lists, blocks, and playlists.

MediaShield does not extract a frame from your uploaded file to build a thumbnail -- that requires server-side video processing (ffmpeg) that most WordPress hosts don't provide. A manually chosen Featured Image gives you full control over how the video looks in listings.

### Does it work with page caching plugins (WP Rocket, LiteSpeed, W3TC, WP Super Cache)?

Yes, with one configuration step: **exclude REST API endpoints from cache**. Most caching plugins do this by default. If you use full-page caching, make sure a signed session token in your page isn't being served to multiple users from the same cache. That breaks session start for anyone after the first viewer.

Quick checklist:

* Don't cache `/wp-json/mediashield/v1/*` responses.
* Don't cache pages with a session-started token for anonymous visitors if you require login.
* **LiteSpeed:** add `/wp-json/mediashield/` to "Do Not Cache URIs".
* **WP Rocket:** covered by default. REST API is never cached.
* **W3TC:** "Reject URIs" should already include `/wp-json/`.

See `docs/free/troubleshooting.md` for caching-specific debug steps.

### Does it work with Cloudflare or a CDN?

Yes. MediaShield is CDN-friendly by default:

* Admin and REST endpoints use standard WordPress authentication. Cloudflare does not cache these.
* Frontend JS and CSS assets are versioned and cacheable.
* Video playback for YouTube, Vimeo, Wistia, and Bunny uses the platform's own CDN. Cloudflare doesn't touch it.
* Self-hosted video streaming uses a PHP proxy endpoint that should NOT be cached. Add a Cloudflare Page Rule if needed: `yoursite.com/wp-json/mediashield/v1/stream/*` set to Bypass Cache.

For session heartbeats, if you use Cloudflare's Full Page Cache or APO, make sure `/wp-json/` is excluded. Default Cloudflare settings handle this correctly.

### Can users download my videos for offline viewing?

No, in free or Pro. Videos can only be played online -- the player wrapper, watermark, and session tracking all need a live connection.

Earlier documentation described a Pro "Save for Offline" feature using a long-lived DRM licence and cached encrypted segments. **That feature was removed in Pro 1.2.0 and is not coming back.** If you were relying on it, offline playback is no longer available.

### What happens if my Pro license expires?

**Your Pro features keep working.** License status in MediaShield Pro is **updates-only**. When your license lapses, you stop receiving plugin updates, but every Pro feature (watermark, DRM, heatmaps, realtime, etc.) keeps working exactly as before.

Renewal restores update access. You never get locked out of your own content or settings. If you move Pro to a new site, deactivate on the old one first (MediaShield > License) to free the activation slot.

See `docs/pro/license-management.md` for details.

### Does it integrate with BuddyBoss, BuddyPress, MemberPress, Paid Memberships Pro?

Yes. MediaShield doesn't replace these plugins. It works on top of them.

* **BuddyBoss and BuddyPress:** videos on group, activity, or course pages are picked up automatically, provided the video is in your MediaShield library. Role-based access (Pro) respects member roles. Tested with BuddyBoss Platform plus BuddyX theme.
* **MemberPress:** use the `mediashield_can_watch` filter to check MemberPress access rules:
  ```php
  add_filter( 'mediashield_can_watch', function( $result, $video_id, $user_id ) {
      if ( function_exists( 'mepr_get_user' ) && ! mepr_get_user( $user_id )->is_active() ) {
          return array( 'allowed' => false, 'reason' => 'Active membership required.' );
      }
      return $result;
  }, 10, 3 );
  ```
* **Paid Memberships Pro:** same pattern via `pmpro_hasMembershipLevel()`.
* **Restrict Content Pro:** same pattern via `rcp_user_has_active_membership()`.
* **LearnDash, Tutor LMS, LifterLMS:** milestone actions fire completion hooks automatically (Pro). Free users wire via the `mediashield_milestone_reached` action.

For custom membership logic, the `mediashield_can_watch` filter is the single extension point. See `docs/developer/hooks-filters-free.md`.

### How do I migrate from Presto Player, VdoCipher, or another video plugin?

See `docs/free/migration-guide.md` for step-by-step migration from the common alternatives. Short version: MediaShield doesn't require re-uploading videos. You keep your existing hosting (YouTube, Vimeo, Bunny) and MediaShield adds the protection layer on top. Migration is typically a 30-minute job.

### Can I white-label the watermark?

**Free:** the watermark shows username plus IP. You can't change which fields are shown in free. Only opacity, color, and swap interval are configurable.

**Pro:** full white-label. Choose any combination of 7 fields (username, email, IP, user ID, timestamp, site name, custom text). Add your own branding via the custom text field. The "Protected by MediaShield" badge can be hidden globally in Settings, Watermark.

### How do I remove the "Protected by MediaShield" badge?

Settings, Watermark, **Show MediaShield Badge**, toggle off. This works in both free and Pro. The badge is on by default in free to give a visual cue that the video is protected. Many Pro users turn it off for a cleaner look.

### Refund policy

14-day money-back guarantee on MediaShield Pro. No questions asked. Email `support@wbcomdesigns.com` with your license key and we'll process the refund within 48 hours. The free plugin is GPL-licensed and always free.

---

## Video Protection

### How does the watermark work?

MediaShield renders a dynamic canvas overlay on top of the video player showing the viewer's display name and IP address. The watermark:

* Swaps position at configurable intervals (default 30 seconds).
* Stays visible in fullscreen mode, because MediaShield puts its own container into fullscreen rather than the bare video.
* Is watched for tampering. If the overlay is deleted or hidden from browser developer tools, MediaShield does not redraw it -- it pauses the video and hides the player instead, so there is no watermark-free playback to record.
* Is purely client-side. No video re-encoding required.

The watermark is drawn for Standard and Strict videos only. Basic and None have no watermark.

Pro extends the watermark to include email, user ID, timestamp, site name, and custom text.

### Can users still screen-record my videos?

MediaShield makes screen recording traceable, not impossible. The dynamic watermark with the viewer's identity (name, IP, email in Pro) means any leaked recording can be traced back to the source. Combined with DRM (Pro), content protection is significantly stronger.

### What does "developer-tools detection" do?

When a user opens browser developer tools while watching a video, MediaShield:

1. Detects the panel opening (via timing and size heuristics). Skipped on touch and small-screen devices, where it produces too many false alarms.
2. Pauses video playback and shows an overlay, if you have turned "Pause Video When Detected" on. That is off by default; detection is recorded either way.
3. Records the event. In free, it goes to your WordPress error log -- there is no admin screen listing these. Pro turns each one into a suspicious-activity alert you can see and act on.

Repeat detections from the same viewer are rate-limited to one recorded event per hour, so a viewer who leaves developer tools open doesn't flood the log.

This deters casual attempts to inspect video source URLs.

### Can I disable protection for specific videos?

Yes. Each video has a per-video protection level override. Set it to "None" in the video editor to disable all protection for that video.

---

## Sessions and Access

### How do concurrent stream limits work?

Each logged-in account is allowed a configurable number of simultaneous video streams. Default is 2. The system tracks active sessions via heartbeat pings every 30 seconds. When someone tries to start a new session beyond the limit, they see an error message and must close another video first.

The limit counts per account, so it does not apply to logged-out viewers on a public video -- there are no credentials to share, and every guest would otherwise count against one shared total.

### What happens when a user closes their browser tab?

MediaShield uses `sendBeacon` on page unload to end the session. If the beacon fails (browser crash, for example), an hourly cleanup job closes any session that has gone more than 10 minutes without a heartbeat. A stuck session therefore frees up its concurrent-stream slot within the hour, not instantly.

### Can I revoke a user's access?

Partly. MediaShield can end every active session a user holds in one action, and their streams stop on the next heartbeat -- but **there is currently no button for it in the admin**. It is available to developers and site tooling through the plugin's API, not from the Viewers page. If you need to cut someone off right now without writing code, remove their role or deactivate their account: every video request re-checks permission, so access stops immediately.

---

## GDPR and Privacy

### Is MediaShield GDPR compliant?

Yes. MediaShield registers with WordPress's built-in privacy tools:

* **Personal Data Export:** exports all watch sessions, milestones, and tags associated with a user.
* **Personal Data Erasure:** anonymizes personal information (IP address, user agent) in watch sessions while aggregate analytics are retained. Deletes milestones and tag assignments.

### What data does MediaShield collect?

For each watch session:

* User ID, video ID.
* IP address, user agent, device type, browser.
* Session start time, last heartbeat, total watch time.
* Video position and completion percentage.

All data is stored in your own WordPress database. Nothing is sent to external servers.

---

## Troubleshooting

### Videos aren't being detected or wrapped

1. Confirm the video was added to MediaShield. Embeds pasted directly into a post are not protected -- this is by far the most common cause.
2. Check that MediaShield is enabled in Settings.
3. Check that the address stored on the video matches the one on the page exactly. For a self-hosted video the match is on the file address; for a platform video it is on the platform's video ID.
4. Check that automatic detection is not disabled on the page (see the `mediashield_enable_output_buffer` filter in the developer docs), and that the embed doesn't carry a `data-ms-skip` attribute or `ms-skip` class.

### Watermark isn't showing

1. Make sure the protection level is "Standard" or "Strict" (global or per-video). Basic and None draw no watermark.
2. Check watermark opacity is above 0.
3. Check the browser console for JavaScript errors.

The watermark does not require a logged-in user. For a logged-out viewer on a public video it reads "Guest" plus their IP address.

### Session tracking isn't working

1. Verify the REST API is accessible. Visit `/wp-json/mediashield/v1/` in your browser -- you should see a JSON response.
2. Check that your caching plugin isn't caching REST API responses.
3. Make sure your site's session tokens are not being cached and served to multiple users (common with full-page caching plugins).

### Admin dashboard shows no data

Make sure you have real watch sessions recorded. The dashboard only shows actual data. There are no demo or sample numbers. Create a video, watch it in a logged-in browser, and refresh the dashboard.
