# Troubleshooting

Start with this checklist before investigating further. It covers the most common reports.

## Quick diagnostic checklist

- MediaShield is activated on the Plugins page
- Settings > Enable MediaShield is ON
- Protection level is not set to "None" (globally or per-video)
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

## Watermark isn't showing

Video plays but no username or IP overlay appears.

1. **Protection level is Basic or None.** Basic skips the watermark. Set to Standard or Strict globally (Settings > General) or on the individual video.

2. **Opacity is 0.** Go to Settings > Watermark > Opacity. Set it to 30 or higher.

3. **User is not logged in.** Without a logged-in user, the watermark shows "Guest" plus IP. If you expected a named watermark, confirm the viewer is logged in.

4. **Theme CSS conflict.** In browser DevTools, look for an element with class `ms-watermark-canvas` inside the video container. If it exists but is invisible, your theme may be hiding canvas elements. Add this CSS to your theme:

```css
.ms-protected-player .ms-watermark-canvas {
    display: block !important;
    position: absolute !important;
}
```

## Session tracking isn't working

Videos play but the Dashboard shows zero sessions and milestones never fire.

1. **REST API is blocked.** Visit `/wp-json/mediashield/v1/` in your browser. You should see a JSON response. If you see a 404, a security plugin (such as iThemes Security or Wordfence) is blocking `/wp-json/`. Whitelist that path.

2. **Caching plugin is caching security tokens.** This is the most common cause. Full-page caching serves the same nonce (one-time security token) to every visitor, so each new viewer's session start fails.

   Quick fixes by caching plugin:
   - **LiteSpeed Cache:** Settings > Cache > Do Not Cache URIs. Add `/wp-json/mediashield/`.
   - **WP Rocket:** REST API is excluded by default. Verify under Advanced Rules.
   - **W3 Total Cache:** Performance > Page Cache > Reject URIs. Add `/wp-json/`.
   - **WP Super Cache:** Advanced > Rejected URL Strings. Add `/wp-json/`.
   - **Cloudflare APO:** Add a Page Rule for `*yoursite.com/wp-json/*` set to Cache Level: Bypass.

3. **Ad blocker on the viewer's browser.** Some strict privacy browsers block `/wp-json/` requests. Advise viewers to whitelist your domain.

## Dashboard shows no data

Stat cards all read 0. Chart is empty.

1. **No sessions yet.** The dashboard shows real data only. Watch a video as a logged-in user for at least 30 seconds, then refresh.

2. **Date range filter.** Check the period selector at the top of the dashboard. Make sure your test activity falls within the selected range.

## "Log in to watch" overlay on public pages

Visitors see the login gate even on pages you intended to be public.

1. **Require Login is on globally.** Settings > General > Require Login. Turn off if you want public viewing.

2. **Per-video override.** Open the video edit screen and check the Access settings. Make sure it is not set to require login.

## Self-hosted video returns 403

1. **Session not started.** Self-hosted streaming requires an active session token. Check the Network tab in browser DevTools to confirm the session start request succeeded.

2. **Nginx configuration.** If you use Nginx (not Apache), add a rule to deny direct access to the protected upload directory and let only the PHP proxy serve files. See the server configuration section in the developer guide.

3. **CDN caching the stream endpoint.** The self-hosted stream endpoint should not be cached. If you use Cloudflare or another CDN, add a bypass rule for your stream URL.

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
