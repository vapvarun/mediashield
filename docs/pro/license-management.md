# MediaShield Pro License Management

How to activate your license, what happens when it expires, how to move between sites, and what the license actually does.

## The short version

Your license key unlocks **plugin updates and email support**. It does not gate feature availability. Every Pro feature (watermarks, email gate, DRM, heatmaps, realtime dashboard, platform imports, exports, digest emails) keeps working forever on any site where Pro was once activated. An expired license means no more automatic updates. Nothing else changes.

This is deliberate. Your content protection should not break because we didn't get paid this year.

## Activating your license

1. Purchase MediaShield Pro from [wbcomdesigns.com](https://wbcomdesigns.com/downloads/mediashield-pro/). You'll receive the license key by email.
2. Install and activate MediaShield Pro on your WordPress site. The free MediaShield plugin must also be active.
3. Enter the key in WP Admin, MediaShield, Settings, License tab. Paste key, click Activate.
4. You'll see "License active" status. Updates are now available.

## What "active" unlocks

| Feature | Requires active license? |
|---|---|
| All Pro features (watermark, email gate, DRM, analytics, etc.) | No. These work forever after activation. |
| Plugin updates (bug fixes, security, new features) | Yes. |
| Priority email support | Yes. |
| Access to documentation and video tutorials | No. Always public. |

## License expiry

When your license expires (typically annual renewal):

* All Pro features keep working. Zero functional impact.
* Your site keeps serving protected videos.
* Your existing admin data stays accessible.
* You stop receiving plugin updates.
* Email support requires an active license to respond.

You'll get renewal reminders by email at 30, 14, 7, and 1 day before expiry. If you miss the window, you can renew at any time. The license reactivates immediately.

## Moving Pro to a new site

Your license allows activation on a specific number of sites (single site, 5 sites, or unlimited).

To move:

1. **Deactivate on the old site first** to free the slot. WP Admin, MediaShield, Settings, License, Deactivate button.
2. **Install Pro on the new site.** Paste the same key, click Activate.

The activation slot frees within seconds. You don't need to contact support.

**If you lost access to the old site** (hacked, server died, forgot admin password):

* Email `support@wbcomdesigns.com` with your license key and purchase email.
* We'll reset activation slots within 24 hours.

## Using Pro on staging or development sites

The EDD Software Licensing layer detects these patterns and treats them as non-counting activations:

* Hostnames containing `staging`, `dev`, `test`, or `local`.
* Hostnames ending in `.local`, `.test`, or `.localhost`.
* IP addresses (for example, `127.0.0.1`).

You can run Pro on unlimited staging sites without using production slots. Staging activations are labeled clearly in the License tab.

## Renewing early

To renew before expiry:

1. Log in at [wbcomdesigns.com/account](https://wbcomdesigns.com/account).
2. Click Renew next to your license.
3. The renewal extends from your current expiry date, not from today. You don't lose days by renewing early.

Early renewals often include a discount. Watch for emails from us.

## Refund and cancellation

**14-day money-back guarantee.** Email `support@wbcomdesigns.com` with:

* Your license key.
* The email you purchased with.

We process refunds within 48 hours. After refund, your Pro features keep working on sites where Pro was activated. We trust you to uninstall. Most customers ask for refunds because MediaShield didn't fit their use case. We'd rather you walk away happy and tell someone else.

**Cancel auto-renewal:** log into your account, go to License, toggle off auto-renew. This stops future charges. Your current license runs to its expiry date normally.

## Troubleshooting license issues

### "Invalid license key" error

* Typo? Paste the key from the original email. Don't type it.
* Trailing whitespace? Check for a space at the end.
* Already activated too many sites? Free a slot by deactivating on another site, or contact support.

### "License activation failed" without an error message

* Check that `allow_url_fopen` is enabled (PHP setting).
* Check that your host allows outbound HTTPS to `wbcomdesigns.com`.
* Disable any "Block outgoing API calls" security plugins temporarily.

### "Your license has expired" but you renewed

* Click Deactivate then Activate again in the License tab to re-check upstream status.
* If still stuck, email support with your order number. We can force-refresh from our end.

### Can't find the License tab in admin

* Confirm MediaShield Pro is activated on the Plugins page.
* Confirm the free MediaShield plugin is also active.
* Clear any object cache (Redis, Memcached). License status is cached for 12 hours.

## What we can and can't see about your license

For trust:

* **We see:** your license key, your site URL (domain only), activation count, last activation date.
* **We don't see:** your site's data, users, videos, analytics, or content.

License validation is a simple HTTPS POST to our server with the key plus domain. No other telemetry is sent.

## FAQ

**Can I use one license on multiple client sites as an agency?**
Yes, if you purchase a multi-site license (5-site or unlimited tier). A single-site license covers only one domain.

**Does the license work on WordPress multisite (network)?**
Each subsite counts as one activation unless you purchase the network license option. Contact sales if you need a custom multisite arrangement.

**What happens if I deactivate the plugin (not the license)?**
Features pause. Data stays in the database. Re-activate to resume. The license slot stays consumed until you explicitly deactivate the license itself.

**What happens if I delete the plugin entirely?**
All Pro database tables and options are removed (see `uninstall.php`). The free plugin's data is preserved. The license slot frees automatically.

**I run the free plugin and I'm thinking about upgrading. Will I lose my free data?**
No. Pro extends the free plugin. All your videos, sessions, milestones, and tags stay the same. Pro adds new features and new tables alongside the existing free data.

## Support contact

* **Email:** support@wbcomdesigns.com
* **Response SLA:** 48 business hours (Monday to Friday, IST).
* **Active license required** for new support tickets.
* **Community support:** WordPress.org plugin forum (free plugin only).

Last updated: 2026-04
