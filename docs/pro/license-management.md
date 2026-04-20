# MediaShield Pro — License Management

Everything you need to know about your Pro license: activation, expiry, moving between sites, and what keeps working (spoiler: everything).

## The one-paragraph truth

Your license key unlocks **plugin updates and email support**. It does **not** gate feature availability. Every Pro feature — watermarks, email gate, DRM, heatmaps, realtime dashboard, platform imports, exports, digest emails — continues to work forever on any site where Pro was once activated. An expired license means no more automatic updates; nothing else changes.

This is deliberate. Your content protection should not break because we didn't get paid this year.

## Activating your license

1. **Purchase** MediaShield Pro from [wbcomdesigns.com](https://wbcomdesigns.com/downloads/mediashield-pro/) — you'll receive the license key by email.
2. **Install and activate** MediaShield Pro on your WordPress site (the free MediaShield plugin must also be active).
3. **Enter the key:** WP Admin → MediaShield → Settings → License tab → paste key → click **Activate**.
4. **Confirm:** you'll see "License active" status. Updates are now available.

## What "active" unlocks

| Feature | Requires active license? |
|---|---|
| All Pro features (watermark, email gate, DRM, analytics, etc.) | ❌ No — work forever after activation |
| Plugin updates (bug fixes, security, new features) | ✅ Yes |
| Priority email support | ✅ Yes |
| Access to documentation / video tutorials | No (always public) |

## License expiry

When your license expires (typically annual renewal):

- ✅ **All Pro features continue to work.** Zero functional impact.
- ✅ Your site continues to serve protected videos.
- ✅ Your existing admin data remains accessible.
- ❌ You stop receiving plugin updates.
- ❌ Email support requires an active license to respond.

You'll receive renewal reminders by email at 30 / 14 / 7 / 1 day before expiry. If you miss the window, you can renew at any time — the license reactivates immediately.

## Moving Pro to a new site

Your license allows activation on a specific number of sites (determined by your purchase tier — single site, 5 sites, or unlimited). Moving to a new site:

1. **Deactivate on the old site first** to free the slot:
   - WP Admin → MediaShield → Settings → License → **Deactivate** button.
2. **Install Pro on the new site**, paste the same key, click Activate.

The activation slot is freed within seconds. You do NOT need to contact support.

**If you lost access to the old site** (hacked, server died, forgot admin password):
- Email `support@wbcomdesigns.com` with your license key and purchase email.
- We'll reset activation slots within 24 hours.

## Using Pro on staging / development sites

The EDD Software Licensing layer detects these patterns and treats them as **non-counting activations**:

- Hostnames containing `staging`, `dev`, `test`, `local`
- Hostnames ending in `.local`, `.test`, `.localhost`
- IP addresses (e.g., `127.0.0.1`)

You can run Pro on unlimited staging sites without using production activation slots. Check the License tab — staging activations are labeled clearly.

## Renewing early

If you want to renew before expiry:

1. Log in to your account at [wbcomdesigns.com/account](https://wbcomdesigns.com/account).
2. Click **Renew** next to your license.
3. The renewal extends from your current expiry date (not from today) — you don't lose days by renewing early.

Early renewals often include a discount — watch for emails from us.

## Refund and cancellation

**14-day money-back guarantee.** Email `support@wbcomdesigns.com` with:
- Your license key
- The email you purchased with

We'll process refunds within 48 hours. After refund, your Pro features continue to work on sites where Pro was activated — we trust you to uninstall. Most customers ask for refunds because MediaShield didn't fit their use case; we'd rather you walk away happy and tell someone else.

**Cancellation of auto-renewal:** Log into your account → License → toggle off auto-renew. This stops future charges; your current license runs to its expiry date normally.

## Troubleshooting license issues

### "Invalid license key" error
- **Typo?** Paste the key from the original email — don't type it.
- **Trailing whitespace?** Check for a space at the end.
- **Already activated too many sites?** Free a slot by deactivating on another site, or contact support.

### "License activation failed" without error message
- Check that `allow_url_fopen` is enabled (PHP setting).
- Check that your host allows outbound HTTPS to `wbcomdesigns.com`.
- Disable any "Block outgoing API calls" security plugins temporarily.

### "Your license has expired" but you renewed
- Click **Deactivate** then **Activate** again in the License tab to re-check upstream status.
- If still stuck, email support with your order number — we can force-refresh from our end.

### Can't find the License tab in admin
- Confirm MediaShield Pro is activated: Plugins page → look for "MediaShield Pro" with active status.
- Confirm the free MediaShield plugin is also active.
- Clear any object cache (Redis, Memcached) — license status is cached for 12 hours.

## What we can / can't see about your license

For trust:

- **We see:** your license key, your site URL (domain only), activation count, last activation date.
- **We don't see:** your site's data, users, videos, analytics, or content.

License validation is a simple HTTPS POST to our server with the key + domain. No telemetry beyond that is sent.

## Frequently asked

**Q: Can I use one license on multiple client sites as an agency?**
A: Yes, if you purchase a multi-site license (5-site or unlimited tier). A single-site license covers only one domain.

**Q: Does the license work on WordPress multisite (network)?**
A: Each subsite in a multisite network counts as one activation unless you purchase the network license option. Contact sales if you need a custom multisite arrangement.

**Q: What happens if I deactivate the plugin (not the license)?**
A: Features pause. Data remains in the database. Re-activate to resume. License slot remains consumed until you explicitly deactivate the license itself.

**Q: What happens if I delete the plugin entirely?**
A: All Pro database tables and options are removed (see `uninstall.php`). The free plugin's data is preserved. The license slot is freed automatically.

**Q: I run a free plugin and think about upgrading — will I lose my free data?**
A: No. Pro extends the free plugin. All your videos, sessions, milestones, tags — nothing changes. Pro adds new features and new tables alongside the existing free data.

## Support contact

- **Email:** support@wbcomdesigns.com
- **Response SLA:** 48 hours business days (Monday–Friday, IST)
- **Active license required** for new support tickets
- **Community support:** WordPress.org plugin forum (for the free plugin only)

---

Last updated: 2026-04
