# MediaShield Frequently Asked Questions

## General

### Which video platforms does MediaShield support?

MediaShield supports five platforms out of the box:

* **Self-hosted:** MP4, WebM, MOV, M4V files uploaded to your WordPress site.
* **YouTube:** any public or unlisted YouTube video.
* **Vimeo:** standard and Pro Vimeo embeds.
* **Bunny Stream:** Bunny.net video hosting (API connection requires Pro).
* **Wistia:** Wistia inline embeds.

The free plugin detects and protects embeds from all platforms. Pro adds direct API connections for browsing, importing, and uploading.

### Does MediaShield work with page builders?

Yes. MediaShield uses output buffering to detect and wrap video embeds automatically, regardless of how they're inserted. It works with Elementor, Beaver Builder, Divi, WPBakery, and any builder that outputs standard `<video>` or `<iframe>` elements.

### Does it work with LMS plugins?

Yes. MediaShield works alongside LearnDash, LifterLMS, Tutor LMS, Sensei, and other LMS plugins. Use the `mediashield_milestone_reached` action to integrate with LMS completion tracking:

```php
add_action( 'mediashield_milestone_100', function( $user_id, $video_id ) {
    // Mark lesson complete when video reaches 100%.
}, 10, 2 );
```

### Will MediaShield slow down my site?

No. MediaShield only loads its CSS and JavaScript on pages that contain video content. Pages without videos have zero performance impact. Session validation uses HMAC cryptography with no database lookup per heartbeat, so even high-traffic sites scale cleanly.

### Does it work on mobile?

Yes. The watermark overlay, player wrapping, session tracking, and playback all work on iOS Safari, Android Chrome, and every modern mobile browser. Notes:

* **DevTools detection is intentionally disabled on touch or small-screen devices** (under 1024 px wide) to avoid false positives from on-screen keyboards and orientation changes.
* **iOS Safari** falls back to native HLS for encrypted Pro DRM content. Playback works. The key exchange goes through our license endpoint.
* **Mobile screen recording** (iOS and Android built-in) cannot be prevented by any web plugin. The watermark stays visible in any recording, which is the forensic deterrent.

### Does it work with page caching plugins (WP Rocket, LiteSpeed, W3TC, WP Super Cache)?

Yes, with one configuration step: **exclude REST API endpoints from cache**. Most caching plugins do this by default. If you use full-page caching, make sure the nonce in your page isn't being served to multiple users from the same cache. That breaks session start for anyone after the first viewer.

Quick checklist:

* Don't cache `/wp-json/mediashield/v1/*` responses.
* Don't cache pages with a session-started nonce for anonymous visitors if you require login.
* **LiteSpeed:** add `/wp-json/mediashield/` to "Do Not Cache URIs".
* **WP Rocket:** covered by default. REST API is never cached.
* **W3TC:** "Reject URIs" should already include `/wp-json/`.

See `docs/free/troubleshooting.md` for caching-specific debug steps.

### Does it work with Cloudflare or a CDN?

Yes. MediaShield is CDN-friendly by default:

* Admin SPA and REST endpoints use standard WordPress nonces. Cloudflare does not cache these.
* Frontend JS and CSS assets are versioned and cacheable.
* Video playback for YouTube, Vimeo, Wistia, and Bunny uses the platform's own CDN. Cloudflare doesn't touch it.
* Self-hosted video streaming uses a PHP proxy endpoint that should NOT be cached. Add a Cloudflare Page Rule if needed: `yoursite.com/wp-json/mediashield/v1/stream/*` set to Bypass Cache.

For session heartbeats, if you use Cloudflare's Full Page Cache or APO, make sure `/wp-json/` is excluded. Default Cloudflare settings handle this correctly.

### Can users download my videos for offline viewing?

**Free:** no. Videos can only be played online. The player wrapper and watermark require a live network connection.

**Pro:** optionally yes, via PWA offline playback with DRM. Admins enable this per-video. Viewers click "Save for Offline" and a persistent DRM license is issued (default 30 days). The browser caches encrypted segments via Service Worker. Licenses can be revoked by admin at any time. This is opt-in. By default, offline is off.

### What happens if my Pro license expires?

**Your Pro features keep working.** License status in MediaShield Pro is **updates-only**. When your license lapses, you stop receiving plugin updates, but every Pro feature (watermark, email gate, DRM, heatmaps, realtime, etc.) keeps working exactly as before.

Renewal restores update access. You never get locked out of your own content or settings. If you move Pro to a new site, deactivate on the old one first (Dashboard, MediaShield, License) to free the activation slot.

See `docs/pro/license-management.md` for details.

### Does it integrate with BuddyBoss, BuddyPress, MemberPress, Paid Memberships Pro?

Yes. MediaShield doesn't replace these plugins. It works on top of them.

* **BuddyBoss and BuddyPress:** videos on group, activity, or course pages are auto-wrapped. Role-based access (Pro) respects member roles. Tested with BuddyBoss Platform plus BuddyX theme.
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

For custom membership logic, the `mediashield_can_watch` filter is the single extension point. See `docs/free/hooks-filters.md`.

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
* Stays visible in fullscreen mode.
* Cannot be removed via browser dev tools. It re-renders on DOM change.
* Is purely client-side. No video re-encoding required.

Pro extends the watermark to include email, user ID, timestamp, site name, and custom text.

### Can users still screen-record my videos?

MediaShield makes screen recording traceable, not impossible. The dynamic watermark with the viewer's identity (name, IP, email in Pro) means any leaked recording can be traced back to the source. Combined with DRM (Pro), content protection is significantly stronger.

### What does "devtools detection" do?

When a user opens browser developer tools while watching a video, MediaShield:

1. Detects the devtools panel opening (via timing and size heuristics).
2. Pauses video playback if configured to do so.
3. Logs the event as a suspicious activity alert (Pro).

This deters casual attempts to inspect video source URLs.

### Can I disable protection for specific videos?

Yes. Each video has a per-video protection level override. Set it to "None" in the video editor to disable all protection for that video.

---

## Sessions and Access

### How do concurrent stream limits work?

Each user is allowed a configurable number of simultaneous video streams. Default is 2. The system tracks active sessions via heartbeat pings every 30 seconds. When a user tries to start a new session beyond the limit, they see an error message and must close another video first.

### What happens when a user closes their browser tab?

MediaShield uses `sendBeacon` on page unload to end the session. If the beacon fails (browser crash, for example), the session is automatically expired after 5 minutes without a heartbeat.

### Can I revoke a user's access?

Yes. Admins can revoke all active sessions for a user via the REST API endpoint `POST /mediashield/v1/session/revoke-user`. This immediately terminates all their video streams.

---

## GDPR and Privacy

### Is MediaShield GDPR compliant?

Yes. MediaShield registers with WordPress's built-in privacy tools:

* **Personal Data Export:** exports all watch sessions, milestones, and tags associated with a user.
* **Personal Data Erasure:** anonymizes PII (IP address, user agent) in watch sessions while aggregate analytics are retained. Deletes milestones and tag assignments.

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

1. Check that MediaShield is enabled in Settings.
2. Verify the video URL matches a supported platform pattern.
3. If using a custom embed format, add the URL pattern to Settings, Custom URL Patterns.
4. Check that output buffering is not disabled on the page (see the `mediashield_enable_output_buffer` filter).

### Watermark isn't showing

1. Make sure protection level is set to "Standard" or higher (global or per-video).
2. Check watermark opacity is above 0.
3. Verify the user is logged in. The watermark requires user identity data.
4. Check the browser console for JavaScript errors.

### Session tracking isn't working

1. Verify the REST API is accessible (`/wp-json/mediashield/v1/session/start`).
2. Check that your caching plugin isn't caching REST API responses.
3. Make sure nonces are not being cached (common with full-page caching plugins).

### Admin dashboard shows no data

Make sure you have real watch sessions recorded. The dashboard only shows actual data. There are no demo or sample numbers. Create a video, watch it in a logged-in browser, and refresh the dashboard.
