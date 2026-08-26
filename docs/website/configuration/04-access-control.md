# Access Control

MediaShield decides who can watch a video with a single check that runs when a watch session starts, and again on every request for a self-hosted file. The checks run in this order:

1. **Administrators pass.** Anyone with `manage_options` is never blocked.
2. **Login gate** - the Require Login setting.
3. **Per-video role** - the video's Restrict to Role setting.
4. **Domain whitelist** - the Allowed Domains setting.
5. **Custom rules** - the `mediashield_can_watch` filter, where membership plugins, Pro's LMS adapters, and your own code plug in.

## Login requirement

**Require Login** (Settings > General) is on by default. While it is on, visitors who are not logged in see a login overlay instead of the player.

Turn it off and guests can watch: the player asks the server, the server allows it, and the watch session is recorded like any other. Before 1.3.0 this setting did nothing when off - the player refused guests locally before the server was ever consulted - so if you tried it in an earlier version and nothing changed, that was the bug, not your configuration.

Customise the overlay under Settings > Login & Access Messages:

- **Login Overlay Text** - the message shown to visitors. Default: "Please log in to watch this video"
- **Login Button Text** - the button label. Default: "Log In"

## Per-video role restriction

Each video has an optional **Restrict to Role** setting. When set, only users who actually hold that role can watch.

The match is exact, not hierarchical. A video restricted to `subscriber` refuses an `editor`, because an editor does not hold the subscriber role. Administrators are the one exception and always pass. Logged-out visitors are refused with the login overlay text, since a guest cannot hold any role.

Users who are signed in but don't hold the role see the **Access Denied Text** (Settings > Login & Access Messages). Default: "You do not have access to this video".

Leave the setting on "Any logged-in user" to allow every signed-in viewer.

## Concurrent stream limits

**Max Concurrent Streams** (Settings > Concurrent Streams) - how many devices one account can actively watch on at the same time. Default is 2.

The player sends a heartbeat every 30 seconds. A session that has not sent one for 5 minutes stops counting toward the limit, so a viewer who closes their laptop is not locked out for long. An hourly background job then marks sessions with no heartbeat for 10 minutes as finished, which is what clears them out of the Active Viewers count.

When a viewer tries to start a stream beyond the limit, the request is refused with "Too many active streams. Please close another video first." The `mediashield_concurrent_limit_reached` action fires at the same moment, so you can hook logging or alerting.

When a viewer closes a browser tab, MediaShield uses the browser's `sendBeacon` API to end the session immediately. If the beacon never arrives (a browser crash, for example) the 5-minute rule above covers it.

Guests are never counted. With no account there is nothing to share, and counting them would make every anonymous visitor on the site compete for the same two slots.

### Revoking a user's sessions

MediaShield can terminate every active session for one account, which fires `mediashield_user_access_revoked` and immediately breaks their streams.

There is no button for it in the admin in this release. It is available as an authenticated REST call for administrators:

```
POST /wp-json/mediashield/v1/session/revoke-user
{ "user_id": 42 }
```

Anything that can make an authenticated WordPress REST request as an administrator can trigger it - WP-CLI, a small admin snippet, or your own tooling.

## Domain whitelist

**Allowed Domains** - the domains that may embed your videos. Put one per line, or separate them with commas - both work. Pasting a full web address is fine too; only the domain part is used. Leave empty to allow embeds from any domain.

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

For the filter signature and examples, see the [Developer Guide - Hooks and Filters](../developer-guide/02-hooks-and-filters.md).
