# MediaShield -- Frequently Asked Questions

## General

### Which video platforms does MediaShield support?

MediaShield supports five platforms out of the box:

- **Self-hosted** -- MP4, WebM, MOV, M4V files uploaded to your WordPress site
- **YouTube** -- Any public or unlisted YouTube video
- **Vimeo** -- Standard and Pro Vimeo embeds
- **Bunny Stream** -- Bunny.net video hosting (API connection requires Pro)
- **Wistia** -- Wistia inline embeds

The free plugin detects and protects embeds from all platforms. Pro adds direct API connections for browsing, importing, and uploading.

### Does MediaShield work with page builders?

Yes. MediaShield uses output buffering to detect and wrap video embeds automatically, regardless of how they're inserted. It works with Elementor, Beaver Builder, Divi, WPBakery, and any builder that outputs standard `<video>` or `<iframe>` elements.

### Does it work with LMS plugins?

Yes. MediaShield works alongside LearnDash, LifterLMS, Tutor LMS, Sensei, and other LMS plugins. Use the `mediashield_milestone_reached` action to integrate with LMS completion tracking:

```php
add_action( 'mediashield_milestone_100', function( $user_id, $video_id ) {
    // Mark lesson complete when video reaches 100%
}, 10, 2 );
```

### Will MediaShield slow down my site?

No. MediaShield only loads its CSS and JavaScript on pages that contain video content. Pages without videos have zero performance impact.

---

## Video Protection

### How does the watermark work?

MediaShield renders a dynamic canvas overlay on top of the video player showing the viewer's display name and IP address. The watermark:

- Swaps position at configurable intervals (default: 30 seconds)
- Stays visible in fullscreen mode
- Cannot be removed via browser dev tools (re-renders on DOM change)
- Is purely client-side -- no video re-encoding required

Pro extends the watermark to include email, user ID, timestamp, site name, and custom text.

### Can users still screen-record my videos?

MediaShield makes screen recording traceable, not impossible. The dynamic watermark with the viewer's identity (name, IP, email in Pro) means any leaked recording can be traced back to the source. Combined with DRM (Pro), content protection is significantly stronger.

### What does "devtools detection" do?

When a user opens browser developer tools while watching a video, MediaShield:

1. Detects the devtools panel opening (via timing/size heuristics)
2. Pauses video playback
3. Logs the event as a suspicious activity alert (Pro)

This deters casual attempts to inspect video source URLs.

### Can I disable protection for specific videos?

Yes. Each video has a per-video protection level override. Set it to "None" in the video editor to disable all protection for that video.

---

## Sessions & Access

### How do concurrent stream limits work?

Each user is allowed a configurable number of simultaneous video streams (default: 2). The system tracks active sessions via heartbeat pings every 30 seconds. When a user tries to start a new session beyond the limit, they receive an error message and must close another video first.

### What happens when a user closes their browser tab?

MediaShield uses `sendBeacon` on page unload to end the session. If the beacon fails (e.g., browser crash), the session is automatically expired after 5 minutes without a heartbeat.

### Can I revoke a user's access?

Yes. Admins can revoke all active sessions for a user via the REST API endpoint `POST /mediashield/v1/session/revoke-user`. This immediately terminates all their video streams.

---

## GDPR & Privacy

### Is MediaShield GDPR compliant?

Yes. MediaShield registers with WordPress's built-in privacy tools:

- **Personal Data Export** -- Exports all watch sessions, milestones, and tags associated with a user.
- **Personal Data Erasure** -- Anonymizes PII (IP address, user agent) in watch sessions while retaining aggregate analytics. Deletes milestones and tag assignments.

### What data does MediaShield collect?

For each watch session:
- User ID, video ID
- IP address, user agent, device type, browser
- Session start time, last heartbeat, total watch time
- Video position and completion percentage

All data is stored in your own WordPress database, never sent to external servers.

---

## Troubleshooting

### Videos aren't being detected/wrapped

1. Check that MediaShield is enabled in Settings.
2. Verify the video URL matches a supported platform pattern.
3. If using a custom embed format, add the URL pattern to Settings > Custom URL Patterns.
4. Check that output buffering is not disabled on the page (see `mediashield_enable_output_buffer` filter).

### Watermark isn't showing

1. Ensure protection level is set to "Standard" or higher (global or per-video).
2. Check watermark opacity is above 0.
3. Verify the user is logged in (watermark requires user identity data).
4. Check browser console for JavaScript errors.

### Session tracking isn't working

1. Verify the REST API is accessible (`/wp-json/mediashield/v1/session/start`).
2. Check that your caching plugin isn't caching REST API responses.
3. Ensure nonces are not being cached (common with full-page caching plugins).

### Admin dashboard shows no data

Make sure you have real watch sessions recorded. The dashboard only shows actual data -- there are no demo/sample numbers. Create a video, watch it in a logged-in browser, and refresh the dashboard.
