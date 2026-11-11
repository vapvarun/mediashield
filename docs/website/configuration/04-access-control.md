# Access Control

MediaShield decides who can watch a video with a single check that runs when a watch session starts, and again on every request for a self-hosted file. The checks run in this order:

1. **Administrators pass.** Anyone with `manage_options` is never blocked.
2. **Login gate** - the Require Login setting. An integration can let guests through for free-preview lessons with the `mediashield_guest_can_watch` filter.
3. **Per-video role** - the video's Restrict to Role setting.
4. **Domain whitelist** - the Allowed Domains setting.
5. **Custom rules** - the `mediashield_can_watch` filter, where membership plugins, Pro's LMS adapters, and your own code plug in.

## Login requirement

**Require Login** (Settings > General) is on by default. While it is on, visitors who are not logged in see a login overlay instead of the player.

Turn it off and guests can watch: the player asks the server, the server allows it, and the watch session is recorded like any other.

### Free-preview lessons while Require Login is on

*Since 1.4.1.* With Require Login on, a guest is stopped at the login check before any other rule runs, so a course's free-preview lesson would ask for a login even though the lesson page is public. An LMS can answer that check for MediaShield through the `mediashield_guest_can_watch` filter. It returns `true` only for videos that sit in a lesson the visitor may already open. Learnomy 2.2.0 does this for its free-preview lessons.

You do not need to turn Require Login off for this. Every other video still asks guests to log in. The per-video role and Allowed Domains checks still apply to a guest the filter lets through. Developers: see [Hooks and Filters](../developer-guide/02-hooks-and-filters.md).

Customise the overlay under Settings > Login & Access Messages:

- **Login Overlay Text** - the message shown to visitors. Default: "Please log in to watch this video"
- **Login Button Text** - the button label. Default: "Log In"

## Per-video role restriction

Each video has an optional **Restrict to Role** setting. When set, only users who actually hold that role can watch.

The match is exact, not hierarchical. A video restricted to `subscriber` refuses an `editor`, because an editor does not hold the subscriber role. Administrators are the one exception and always pass. Logged-out visitors are refused with the login overlay text, since a guest cannot hold any role.

Users who are signed in but don't hold the role see the **Access Denied Text** (Settings > Login & Access Messages). Default: "You do not have access to this video".

Leave the setting on "Any logged-in user" to allow every signed-in viewer.

## Concurrent stream limits

**Max Concurrent Streams** (Settings > Concurrent Streams) - how many devices one account can actively watch on at the same time. Default is 2, the lowest you can set is 1. It is stored as the `ms_max_concurrent_streams` option.

To change it: open **MediaShield > Settings**, find Max Concurrent Streams, enter a whole number of 1 or more and save. The new limit applies to the next stream a viewer starts. Streams already playing are not cut off.

The player sends a heartbeat every 30 seconds. A session that has not sent one for 5 minutes stops counting toward the limit, so a viewer who closes their laptop is not locked out for long. An hourly background job then marks sessions with no heartbeat for 10 minutes as finished, which is what clears them out of the Active Viewers count.

When a viewer tries to start a stream beyond the limit, the request is refused with "Too many active streams. Please close another video first." The `mediashield_concurrent_limit_reached` action fires at the same moment, so you can hook logging or alerting.

When a viewer closes a browser tab, MediaShield uses the browser's `sendBeacon` API to end the session immediately. If the beacon never arrives (a browser crash, for example) the 5-minute rule above covers it.

Guests are never counted. With no account there is nothing to share, and counting them would make every anonymous visitor on the site compete for the same two slots.

### Revoking a user's sessions

Go to **MediaShield > Viewers**, click **Revoke session** on the member's row, then **Revoke** to confirm. It ends every active watch session for that account: they stop counting toward the stream limit, the player stops recording their progress, and `mediashield_user_access_revoked` fires. A video already playing on their screen is not stopped, and they can start watching again. To stop them watching, change what they are allowed to watch or deactivate the account.

The same action is an authenticated REST call for administrators:

```
POST /wp-json/mediashield/v1/session/revoke-user
{ "user_id": 42 }
```

Anything that can make an authenticated WordPress REST request as an administrator can trigger it - WP-CLI, a small admin snippet, or your own tooling.

## Domain whitelist

**Allowed Domains** (Settings > Access, stored as `ms_allowed_domains`) - the domains that may embed your videos. Put one per line, or separate them with commas or spaces - any mix works. Pasting a full web address is fine too; only the domain part is used. Leave empty to allow embeds from any domain.

To set it: open **MediaShield > Settings > Access**, type each domain (for example `partner.example`), and save. Then open a page on a listed site and on an unlisted site to check that only the listed one plays the video.

Your own site is never blocked by this list. A student who opens a lesson directly, from a bookmark, an email link or the page they land on after logging in, plays the video even though the browser sends no referring address. A page on another site that frames your lesson is still checked, also when it hides its address. Browsers older than Safari 16.4 do not say whether a page is framed, so on those a framed page with no address counts as a direct visit.

When a list is set:
- Requests from your own site's domain are always allowed
- Requests from a listed domain, or any of its subdomains, are allowed
- Requests from any other domain are denied with the Access Denied text
- Requests with a missing Referer header are denied by default (change this with the `mediashield_allow_empty_referer` filter)

This prevents your video embeds from being placed on external sites without permission. It is a Referer check, so treat it as a courtesy fence rather than a hard boundary - a Referer header can be forged.

## Membership and LMS integrations

MediaShield works alongside membership plugins and LMS platforms. The free plugin does not ship built-in integrations, but you can wire them via the `mediashield_can_watch` PHP filter.

The filter receives the current access decision, the video ID, and the user ID. Return a modified decision to allow or deny based on your own logic.

Examples of what you can gate on:
- Active subscription status (MemberPress, Paid Memberships Pro, Restrict Content Pro)
- LearnDash or LifterLMS course enrollment
- Any custom membership or entitlement check

MediaShield Pro ships pre-built adapters for LearnDash, LifterLMS, and TutorLMS. Free users wire via the filter manually.

A ready-made snippet and the list of everything MediaShield connects to are in [Integrations](../using-mediashield/18-integrations.md). For the filter signature and examples, see the [Developer Guide - Hooks and Filters](../developer-guide/02-hooks-and-filters.md).
