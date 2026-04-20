# MediaShield Troubleshooting

Top issues and fixes. Start here before filing a support ticket.

## Diagnostic checklist

Run these first. They cover 70 percent of reports.

- [ ] MediaShield is activated on the Plugins page.
- [ ] Settings, MediaShield, Enable MediaShield is ON.
- [ ] Protection level is not set to "None" (page or per-video).
- [ ] You're viewing a page that contains a video shortcode, block, or embed.
- [ ] Browser cache cleared (Cmd or Ctrl + Shift + R).
- [ ] No JS errors in the browser DevTools console.
- [ ] WordPress debug log at `wp-content/debug.log` has no MediaShield-tagged errors.

If all 7 pass and you still have an issue, the sections below probably cover it.

## Videos aren't being detected or wrapped

Your YouTube, Vimeo, or Bunny video shows on the page but has no watermark, no badge, and no protection.

Fixes in order of likelihood:

1. **Shortcode vs. auto-detect.** If you pasted a raw YouTube URL into a post, MediaShield's output buffer wraps it, but only if output buffering isn't disabled by another plugin. Use `[mediashield id=X]` to guarantee wrapping.

2. **Embed in an element the output buffer can't scan.** Some page builders render videos via JavaScript after the output buffer runs. Use the MediaShield Video block or `[mediashield id=X]` shortcode in the builder. Not a raw iframe or URL.

3. **Custom embed format.** Settings, Auto-Detection, Custom URL Patterns. Add a regex that matches your iframe src.

4. **Output buffer disabled by a theme or plugin.** Rare, but some performance plugins call `ob_end_clean()` early. Filter override:
   ```php
   add_filter( 'mediashield_enable_output_buffer', '__return_true' );
   ```

5. **Settings, Enable MediaShield is off.** Check the master toggle.

## Watermark isn't showing

Video plays but no username or IP overlay.

1. **Protection level is "Basic" or "None".** Basic skips the watermark. Set to "Standard" or "Strict" globally (Settings, General) or per-video.

2. **Opacity is 0.** Settings, Watermark, Opacity. Set to 0.3 or higher.

3. **User is anonymous.** Without a logged-in user, the watermark shows "Guest" plus IP. If `Require Login` is off and you expected "Guest," this is working as designed. If you want the watermark to show only for logged-in users, that's the default behavior in login-required mode.

4. **Canvas blocked by theme CSS.** Inspect the video container in DevTools for a `.ms-watermark-canvas` element. If it's there but invisible, your theme has `canvas { display: none }` or an overflow clip. Add:
   ```css
   .ms-protected-player .ms-watermark-canvas {
       display: block !important;
       position: absolute !important;
   }
   ```

5. **Fullscreen breaks watermark.** The watermark uses the Fullscreen API's target element. Some themes break this with `position: relative`. Report the theme to support and we'll add it to our compatibility list.

## Session tracking isn't working

Videos play but the Dashboard shows zero sessions, and milestones never fire.

1. **REST API is blocked.** Test by visiting `/wp-json/mediashield/v1/` in your browser. You should see a JSON route list. If you see a 404, a security plugin (iThemes Security, Wordfence) is blocking `/wp-json/`. Whitelist it.

2. **Caching is caching nonces.** This is the number one cause. Full-page caching serves the same nonce to every visitor. Each new visitor can't start a session.
   * **LiteSpeed Cache:** Settings, Cache, Do Not Cache URIs. Add `/wp-json/mediashield/`.
   * **WP Rocket:** already excludes REST API by default. Verify in Advanced Rules.
   * **W3 Total Cache:** Performance, Page Cache, Reject URIs. Add `/wp-json/`.
   * **WP Super Cache:** Advanced, Rejected URL Strings. Add `/wp-json/`.
   * **Cloudflare APO or Full Page Cache:** add a Page Rule `*yoursite.com/wp-json/*` set to Cache Level: Bypass.

3. **Nonce expired in cached pages.** If your page cache TTL is longer than 12 hours, nonces in those pages expire. Two fixes:
   * Reduce page cache TTL to 12 hours or less.
   * Use MediaShield Pro, which re-issues nonces via JS on page load.

4. **Ad blocker blocking `/wp-json/` on the user's browser.** Rare but happens with strict privacy-focused browsers. Advise users to whitelist your domain.

## Admin dashboard shows no data

Stat cards all read 0. Chart is empty.

1. **No actual watch sessions yet.** The dashboard shows real data only. It never shows demo numbers. Create a video, watch it in a logged-in browser for 30+ seconds, and refresh the dashboard.

2. **Your user is an admin and admins don't count.** Admin views are tracked by default. If you've filtered them out via a custom integration, you'll see only non-admin activity. Test as a subscriber.

3. **Date range filter.** Dashboard, period selector. Default is "Last 7 days". Make sure your test activity is within that range.

## "Login to watch" overlay appears on public pages

Anonymous visitors see the login gate even when the page is public.

1. **Require Login is globally on.** Settings, General, Require Login. Turn off if you want public viewing.

2. **Per-video override.** Open the video edit screen, Access section. Confirm it's not set to "Login required".

3. **Custom `mediashield_can_watch` filter denying anonymous users.** Grep your theme and plugins for `mediashield_can_watch`. If you have a custom filter, make sure it returns `allowed: true` for anonymous users when appropriate.

## Right-click still works or keyboard shortcuts aren't blocked

1. **Settings, Protection, Block Right-Click is off.** Turn it on.
2. **Protection level is "None" or "Basic".** "Basic" is the minimum that enables right-click blocking. Set to "Basic" or higher per-video or globally.
3. **Right-click works OUTSIDE the video container.** By design. We block right-click only within `.ms-protected-player`. Right-clicking on the page background still works.

## Self-hosted video returns 403 or won't play

Uploaded MP4 plays in some browsers but 403s in others.

1. **Session not started.** Self-hosted streaming requires an active session. Make sure the player-wrapper JS loaded and `POST /session/start` succeeded (check the Network tab for 200).

2. **`.htaccess` misconfigured.** Our upload directory has an `.htaccess` that only allows proxy-served access. If you moved the uploads directory, re-run activation to regenerate `.htaccess`.

3. **Nginx (no `.htaccess`).** Our streaming proxy still works, but your Nginx config may not respect proxy headers. Add:
   ```nginx
   location ~* /mediashield-uploads/ {
       deny all;
   }
   ```
   so only the PHP proxy can serve files.

4. **Range header dropped by CDN.** Cloudflare plus self-hosted video is a bad combo without config. Disable Cloudflare proxying for the proxy URL, or use Bunny Stream hosting (Pro) instead.

## Pro admin pages are empty (Platforms, Alerts, Heatmap, etc.)

Pro features activate, Pro menu items appear, but clicking one shows a blank white area.

1. **Pro JS bundle not built.** Most common right after upgrading from source. Re-install Pro from the zip we emailed you. The zip includes the built admin bundle.

2. **Free plugin version too old.** Pro 1.0.0 requires free 1.0.0 or later. Update the free plugin.

3. **Browser cache.** Hard reload (Cmd or Ctrl + Shift + R). The Pro admin bundle is versioned, but a stale cache can leave old code running.

4. **Console errors.** Open browser DevTools, Console. Any red errors? Send them to support.

## License activation problems

See `docs/pro/license-management.md` for the full section.

## GDPR export returns empty file

Tools, Export Personal Data. MediaShield section is empty.

1. **The user has no sessions or milestones.** The exporter only returns what exists. Test with an account that has activity.

2. **Custom table prefix.** If your site uses a non-standard `wp_` prefix, confirm Settings, MediaShield shows correct table counts.

## Conflicts with other plugins

Known clean-compatibility list:

* BuddyBoss Platform + BuddyX theme
* LearnDash
* WooCommerce (no checkout interference)
* Yoast SEO, Rank Math
* WP Rocket, LiteSpeed Cache (with REST API exclusion)
* Cloudflare (with `/wp-json/` bypass)

Known caveats:

* Some "Disable REST API" security plugins block our endpoints. Whitelist `/wp-json/mediashield/` or switch plugins.
* Full-page JS-caching plugins that freeze nonces break session start. Use a less aggressive caching level.

## Still stuck?

1. Enable WP debug. Add to `wp-config.php`:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   ```
2. Reproduce the issue.
3. Open `wp-content/debug.log`. Look for lines tagged with `MediaShield` or `mediashield`.
4. Copy the relevant lines and your environment (WP version, PHP version, active theme, active plugins) to `support@wbcomdesigns.com`.

For Pro customers, SLA is 48 business hours. For free users, the WordPress.org plugin forum is monitored but slower.
