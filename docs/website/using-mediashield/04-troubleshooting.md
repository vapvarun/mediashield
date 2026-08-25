# Troubleshooting

Start with this checklist before investigating further. It covers the most common reports.

## Quick diagnostic checklist

- MediaShield is activated on the Plugins page
- Settings > Enable MediaShield is ON
- Protection level is not "None" or "Basic" (globally or per-video) - Basic deliberately skips the watermark and records no sessions
- You're viewing a page that contains a video shortcode, block, or embed
- Browser cache cleared (Ctrl+Shift+R or Cmd+Shift+R)
- No JavaScript errors in the browser console (press F12, open Console tab)
- No MediaShield errors in `wp-content/debug.log`

If all seven pass and you still have an issue, the sections below cover specific symptoms.

## Videos aren't being detected or wrapped

Your video shows on the page but has no watermark, no badge, and no protection layer.

1. **Use a shortcode or block.** If you pasted a raw YouTube URL into a post, MediaShield's output buffer wraps it - but only if output buffering isn't disabled by another plugin. Use `[mediashield id=X]` to guarantee wrapping.

2. **Page builder JavaScript timing.** Some builders render videos via JavaScript after the output buffer runs. In that case, use the MediaShield Video block or `[mediashield id=X]` shortcode in the builder, not a raw iframe or URL.

3. **The video is not in MediaShield.** Only videos added to MediaShield are protected. An embed pasted straight into a post is left alone by design. Add it under MediaShield first, then place it with the block or `[mediashield id=X]`.

4. **Output buffer disabled.** Rare, but some performance plugins disable output buffering. Check your plugin list for anything that modifies output buffering.

5. **Enable MediaShield is off.** Check Settings > General > Enable MediaShield.

6. **The embed is opted out.** Markup carrying `data-ms-skip` or the class `ms-skip` is skipped on purpose.

## The player never starts, and shows no error

The player sits there loading forever, or shows "This video could not be loaded".

That message is new in 1.3.0. Before then, a video whose source URL served something other than a video file simply hung with no explanation.

The usual cause is a source URL that is not the video. The classic case is a **Bunny Stream dashboard URL** - the address in your browser's URL bar while looking at a video in Bunny. Older versions did not recognise those, silently saved them as "self-hosted", and pointed a `<video>` element at a page of HTML.

1. Open the video and check the Detected Platform line under the URL field. MediaShield now tells you when it could not read a platform out of a URL, and refuses to pretend a dashboard link is a video file.

2. Fix an affected video by pasting its embed URL, or the URL of the video itself from Bunny Stream.

3. If several videos were saved this way, repair them in bulk with WP-CLI:

   ```
   wp mediashield repair bunny-urls --dry-run
   wp mediashield repair bunny-urls --execute
   ```

   The dry run reports what it found and changes nothing. Collection URLs (a folder of videos rather than a video) are reported but never rewritten, because a collection ID is not a video ID.

## Watermark isn't showing

Video plays but no username or IP overlay appears.

1. **Protection level is Basic or None.** Both skip the watermark. Set the video, or the site default, to Standard or Strict.

2. **Opacity is at or near 0.** Go to Settings > Watermark > Opacity. It is a 0 to 1 slider, not a percentage - set it to 0.3 or higher.

3. **Narrow player.** Under 640 px wide the overlay shows the display name only and drops the IP. That is intentional, so the text stays readable on phones.

4. **User is not logged in.** With Require Login off, a guest's watermark reads "Guest" plus their IP. If you expected a name, confirm the viewer is logged in.

5. **Theme CSS conflict.** In browser DevTools, look for an element with class `ms-watermark-canvas` inside the video container. If it exists but is invisible, your theme may be hiding canvas elements. Add this CSS to your theme:

```css
.ms-protected-player .ms-watermark-canvas {
    display: block !important;
    position: absolute !important;
}
```

Note that if the canvas is hidden by CSS, MediaShield treats it as tampering and pauses playback - so a theme conflict here shows up as a video that will not play, not just a missing overlay.

## Session tracking isn't working

Videos play but the Dashboard shows zero sessions and milestones never fire.

1. **Protection level is Basic or None.** Neither starts a watch session, so there is nothing to record. Check this first - it is the most common answer.

2. **REST API is blocked.** Visit `/wp-json/mediashield/v1/` in your browser. You should see a JSON response. If you see a 404, a security plugin (such as iThemes Security or Wordfence) is blocking `/wp-json/`. Whitelist that path.

3. **Caching plugin is caching security tokens.** Full-page caching serves the same nonce (one-time security token) to every visitor, so each new viewer's session start fails.

   Quick fixes by caching plugin:
   - **LiteSpeed Cache:** Settings > Cache > Do Not Cache URIs. Add `/wp-json/mediashield/`.
   - **WP Rocket:** REST API is excluded by default. Verify under Advanced Rules.
   - **W3 Total Cache:** Performance > Page Cache > Reject URIs. Add `/wp-json/`.
   - **WP Super Cache:** Advanced > Rejected URL Strings. Add `/wp-json/`.
   - **Cloudflare APO:** Add a Page Rule for `*yoursite.com/wp-json/*` set to Cache Level: Bypass.

4. **Ad blocker on the viewer's browser.** Some strict privacy browsers block `/wp-json/` requests. Advise viewers to whitelist your domain.

## Dashboard shows no data

Stat cards all read 0. Chart is empty.

1. **No sessions yet.** The dashboard shows real data only. Watch a video as a logged-in user for at least 30 seconds, then refresh.

2. **Date range filter.** Check the period selector at the top of the dashboard. Make sure your test activity falls within the selected range. Total Videos and Active Viewers ignore the range; everything else follows it.

3. **Older history looks missing.** Versions before 1.3.0 archived sessions older than 24 months into a table no report reads. Updating to 1.3.0 queues a background job that moves those rows back; give it a few minutes on a large site. Archiving is now off unless you set a retention window under Settings > Analytics Retention.

## "Log in to watch" overlay on public pages

Visitors see the login gate even on pages you intended to be public.

1. **Require Login is on globally.** Settings > General > Require Login. Turn it off to let guests watch. In 1.3.0 this works properly: guests play the video and their sessions are recorded. In earlier versions the setting was inert, so if you turned it off before upgrading and nothing changed, try again.

2. **The video restricts a role.** Open the video edit screen and check **Restrict to Role**. A video with a role set can never be watched by a logged-out visitor, whatever the global login setting says. Set it back to "Any logged-in user" for public videos.

3. **A membership or LMS rule is denying it.** Anything hooked to `mediashield_can_watch` - Pro's LMS adapters, or your own code - can refuse a guest after the built-in checks have passed.

## Self-hosted video returns 403

1. **The viewer is not allowed to watch it.** The streaming endpoint runs the same access check as the player, on every request including every seek. A 403 here means the login gate, the video's role restriction, the domain whitelist, or a custom rule refused that viewer. Check the video's Restrict to Role setting first.

2. **A stale player page.** The signed token in the stream URL is minted when the page renders and is good for six hours. A tab left open overnight can come back to a 403; reloading the page mints a fresh one.

3. **Nginx configuration.** `.htaccess` is an Apache feature and Nginx ignores it, so the deny rule MediaShield writes has no effect there. Check **Tools > Site Health** -- MediaShield tests this by requesting one of your own video files and tells you the result, along with the rule to add:
   ```nginx
   location ^~ /wp-content/uploads/mediashield/ {
       deny all;
       return 403;
   }
   ```
   Playback is unaffected: the player streams through MediaShield, never that address.

4. **CDN caching the stream endpoint.** The self-hosted stream endpoint should not be cached. If you use Cloudflare or another CDN, add a bypass rule for your stream URL.

## Still stuck?

1. Enable WordPress debug logging. Add to `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

2. Reproduce the issue.

3. Open `wp-content/debug.log` and look for lines tagged with `MediaShield` or `mediashield`.

4. Email `support@wbcomdesigns.com` with the relevant log lines and your environment: WordPress version, PHP version, active theme, active plugins.

For Pro customers, SLA is 48 business hours. For free users, the WordPress.org plugin forum is monitored.
