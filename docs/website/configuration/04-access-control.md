# Access Control

MediaShield provides several layers of access control to determine who can watch your videos and under what conditions.

## Login requirement

**Require Login** (Settings > General) forces all viewers to be logged in before any video plays. When a visitor hits a protected video without being logged in, they see a login overlay with configurable text and a button.

Customize the overlay at Settings > Access Control:

- **Login Overlay Text** - the message shown to visitors. Default: "Please log in to watch this video."
- **Login Button Text** - the button label. Default: "Log In."

## Per-video role restriction

Each video in your library has an optional **Access Role** field. When set, only users with that WordPress role (or higher) can watch the video. Logged-in users who don't qualify see the Access Denied text.

**Access Denied Text** (Settings > Access Control) - the message shown when a logged-in user doesn't have the required role. Default: "You do not have access to this video."

Leave the Access Role field blank to allow all logged-in users.

## Concurrent stream limits

**Max Concurrent Streams** - how many devices one account can actively watch on at the same time. Default is 2.

MediaShield tracks active sessions via heartbeat pings every 30 seconds. When a viewer tries to start a new session beyond the limit, the request is denied. The `mediashield_concurrent_limit_reached` action fires so you can hook additional logic (such as logging or alerting).

When a viewer closes a browser tab, MediaShield uses the browser's `sendBeacon` API to end the session immediately. If the beacon fails (browser crash, for example), the session is automatically expired after 5 minutes with no heartbeat.

Admins can revoke all active sessions for a specific user from **MediaShield > Students**. This immediately terminates every active stream for that account.

## Domain whitelist

**Allowed Domains** - a comma-separated list of domains that may embed your videos. Leave empty to allow embeds from any domain.

When a domain list is set:
- Requests from whitelisted domains are allowed
- Requests from non-listed domains are denied
- Requests with a missing Referer header are denied by default (can be changed with the `mediashield_allow_empty_referer` developer filter)

This prevents your video embeds from being placed on external sites without permission.

## Membership and LMS integrations

MediaShield works alongside membership plugins and LMS platforms. The free plugin does not ship built-in integrations, but you can wire them via the `mediashield_can_watch` PHP filter.

The filter receives the current access decision, the video ID, and the user ID. Return a modified decision to allow or deny based on your own logic.

Examples of what you can gate on:
- Active subscription status (MemberPress, Paid Memberships Pro, Restrict Content Pro)
- LearnDash or LifterLMS course enrollment
- Any custom membership or entitlement check

MediaShield Pro ships pre-built adapters for LearnDash, LifterLMS, and TutorLMS. Free users wire via the filter manually.

For the filter signature and examples, see the [Developer Guide - Hooks and Filters](../developer-guide/02-hooks-and-filters.md).
