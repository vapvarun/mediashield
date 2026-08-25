# MediaShield Troubleshooting

Top issues and fixes. Start here before filing a support ticket.

## Diagnostic checklist

Run these first. They cover 70 percent of reports.

- [ ] MediaShield is activated on the Plugins page.
- [ ] Settings, MediaShield, Enable MediaShield is ON.
- [ ] Protection level is not set to "None" (page or per-video).
- [ ] You're viewing a page that contains a video shortcode, block, or embed.
- [ ] Browser cache cleared (Cmd or Ctrl + Shift + R).
- [ ] No JS errors in the browser DevTools console (browser developer tools, accessible with F12 or right-click > Inspect).
- [ ] WordPress debug log at `wp-content/debug.log` has no MediaShield-tagged errors.

If all 7 pass and you still have an issue, the sections below probably cover it.

## Videos aren't being detected or wrapped

Your YouTube, Vimeo, or Bunny video shows on the page but has no watermark, no badge, and no protection.

Fixes in order of likelihood:

1. **The video is not in MediaShield.** This is the usual answer. MediaShield only takes over an embed it can match to a video in your library -- everything else is left exactly as the theme or another plugin wrote it, on purpose. Add the video under **MediaShield > Videos**, then either place it with the block / `[mediashield id=X]`, or leave the existing embed where it is for MediaShield to pick up.

2. **The video is in MediaShield, but the addresses don't match.** Matching is exact. For YouTube / Vimeo / Bunny / Wistia it is on the platform's video ID; for a self-hosted `<video>` it is on the file address, character for character. A trailing query string, a CDN-rewritten host, or `www.` on one side and not the other is enough to miss. Open the video's edit screen and compare what is stored with what the page actually outputs.

3. **Embed rendered by JavaScript after the page loads.** Some page builders and lazy-load scripts insert the player too late for MediaShield to see it. Use the MediaShield Video block or `[mediashield id=X]` shortcode in the builder instead of a raw iframe or URL.

4. **The embed is opted out.** An embed carrying a `data-ms-skip` attribute or an `ms-skip` class is deliberately ignored.

5. **Automatic detection disabled by a theme or plugin.** Rare, but some performance plugins clear the output buffer early. Filter override:
   ```php
   add_filter( 'mediashield_enable_output_buffer', '__return_true' );
   ```

6. **Settings, Enable MediaShield is off.** Check the master toggle.

Also worth knowing: an automatically detected embed uses your **site-wide** default protection level, not that video's per-video level. If the per-video setting matters, place the video with the block or shortcode.

## Watermark isn't showing

Video plays but no username or IP overlay.

1. **Protection level is "Basic" or "None".** Basic deliberately skips the watermark, and skips session tracking and milestones with it, so a Basic video never appears in analytics either. Set the level to "Standard" or "Strict" globally (Settings, General) or per-video.

2. **Opacity is 0.** Settings, Watermark, Opacity. Set to 0.3 or higher.

3. **User is anonymous.** Without a logged-in user the watermark shows "Guest" plus IP. That is working as designed -- being logged out does not remove the watermark. If you want a name on it, leave `Require Login` on.

4. **Canvas blocked by theme CSS.** Inspect the video container in DevTools (browser developer tools, opened with F12) for a `.ms-watermark-canvas` element. If it's there but invisible, your theme has `canvas { display: none }` or an overflow clip. Add:
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

2. **Caching is caching nonces (the one-time security tokens in the page).** This is the number one cause. Full-page caching serves the same token to every visitor. Each new visitor can't start a session.
   * **LiteSpeed Cache:** Settings, Cache, Do Not Cache URIs. Add `/wp-json/mediashield/`.
   * **WP Rocket:** already excludes REST API by default. Verify in Advanced Rules.
   * **W3 Total Cache:** Performance, Page Cache, Reject URIs. Add `/wp-json/`.
   * **WP Super Cache:** Advanced, Rejected URL Strings. Add `/wp-json/`.
   * **Cloudflare APO or Full Page Cache:** add a Page Rule `*yoursite.com/wp-json/*` set to Cache Level: Bypass.

3. **Nonce expired in cached pages.** If your page cache lifetime is longer than 12 hours, the security tokens baked into those pages expire before the visitor uses them. Reduce the page cache lifetime to 12 hours or less, or exclude pages containing videos from full-page caching. (Pro does not change this -- both editions use the same tokens.)

4. **Ad blocker blocking `/wp-json/` on the user's browser.** Rare but happens with strict privacy-focused browsers. Advise users to whitelist your domain.

## Admin dashboard shows no data

Stat cards all read 0. Chart is empty.

1. **No actual watch sessions yet.** The dashboard shows real data only. It never shows demo numbers. Create a video, watch it in a logged-in browser for 30+ seconds, and refresh the dashboard.

2. **The videos you tested are set to Basic or None.** Only Standard and Strict videos start a session, so nothing else ever reaches the dashboard. Check the level on the video you watched.

3. **Administrators are tracked like anyone else** -- they bypass the access checks, but their sessions are still recorded. So watching as an admin is a valid test unless you've filtered admins out with a custom integration.

4. **Date range filter.** Dashboard, period selector. Default is "Last 7 days". Make sure your test activity is within that range.

## "Login to watch" overlay appears on public pages

Anonymous visitors see the login gate even when the page is public.

1. **Require Login is globally on.** Settings, General, Require Login. Turn off if you want public viewing.

2. **Per-video protection level.** Open the video edit screen. Every level except "None" applies a login gate when Require Login is on. If you want this one video public while the rest of the site is gated, set it to "None" -- with the trade-off that None also turns off the watermark and tracking for it.

3. **Per-video role restriction.** Same screen, **Restrict to Role**. A video with a role set can never be watched logged out, whatever the global Require Login setting says.

4. **Custom `mediashield_can_watch` filter denying anonymous users.** Grep your theme and plugins for `mediashield_can_watch`. If you have a custom filter, make sure it returns `allowed: true` for anonymous users when appropriate.

## Right-click still works or keyboard shortcuts aren't blocked

1. **Settings, Protection, Block Right-Click is off.** Turn it on. This is a single global toggle -- it applies at every protection level except None, so raising a video from Basic to Strict will not switch it on for you.
2. **Protection level is "None".** None disables the whole protection layer for that video. Any other level honours your Protection settings.
3. **Right-click works OUTSIDE the video container.** By design. We block right-click only within the player container. Right-clicking on the page background still works.
4. **You expected more than Ctrl+S to be blocked.** "Block Save Shortcut" intercepts Ctrl+S / Cmd+S only, and only while keyboard focus is inside the player. View Source, Print, and the rest are not blocked, by any protection level.

## Self-hosted video returns 403 or won't play

Uploaded MP4 plays in some browsers but 403s in others.

1. **The viewer genuinely isn't allowed.** A 403 from the streaming address is a permission answer, not a plumbing problem: the same checks that gate the player run again on every request for the file. Work through them in order -- Require Login with a logged-out viewer, a per-video **Restrict to Role** the account doesn't hold, or an **Allowed Domains** list turning the request away (which also happens when a privacy-hardened browser sends no referring page at all).

2. **The page has been cached for more than six hours.** The address the player is given carries a signed viewer token that lasts six hours. If your page cache serves markup older than that, the token has expired by the time the visitor presses play and the file request is refused. Lower the page cache lifetime, or exclude pages containing videos from full-page caching.

3. **`.htaccess` missing.** The upload directory gets its `.htaccess` deny rule when MediaShield first writes a file there -- not on activation, so re-activating the plugin will not regenerate it. If you moved or emptied the uploads directory, the rule comes back with the next upload. Note it only matters on Apache -- see the next point.

4. **Nginx ignores `.htaccess` entirely.** This does not break playback (the player streams through MediaShield either way), but it does mean the opposite problem: your video files are served directly to anyone with the address, with no login check and no access rules applied.

   **Go to Tools > Site Health.** MediaShield runs a check there that asks your own server for one of your video files and reports what happened. It gives you the exact rule if the server is handing them over:
   ```nginx
   location ^~ /wp-content/uploads/mediashield/ {
       deny all;
       return 403;
   }
   ```
   Adding it does not affect playback -- the player never requests that address.

5. **Range header dropped by CDN.** Cloudflare plus self-hosted video is a bad combo without config. Disable Cloudflare proxying for the proxy URL, or use Bunny Stream hosting (Pro) instead.

## Pro admin pages are empty (Platforms, Alerts, Heatmap, etc.)

Pro features activate, Pro menu items appear, but clicking one shows a blank white area.

1. **Pro JS bundle not built.** Most common right after upgrading from source. Re-install Pro from the zip we emailed you. The zip includes the built admin bundle.

2. **The free plugin isn't active.** Pro is an add-on and does nothing on its own -- it needs MediaShield (free) installed and active. Keep the two on matching versions; they are released together.

3. **Browser cache.** Hard reload (Cmd or Ctrl + Shift + R). The Pro admin bundle is versioned, but a stale cache can leave old code running.

4. **Console errors.** Open browser DevTools (F12), Console. Any red errors? Send them to support.

## License activation problems

See `docs/pro/license-management.md` for the full section.

## GDPR export returns empty file

Tools, Export Personal Data. MediaShield section is empty.

1. **The user has no sessions or milestones.** The exporter only returns what exists. Test with an account that has activity.

2. **The export only covers MediaShield's own records.** It returns that user's watch sessions (live and archived), their milestone records, and the milestone tags they have earned. Videos, playlists, and settings are site data, not personal data, and are not included.

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
* Full-page JS-caching plugins that freeze security tokens break session start. Use a less aggressive caching level.

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
