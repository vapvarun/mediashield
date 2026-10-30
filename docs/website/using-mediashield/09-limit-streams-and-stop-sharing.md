# Limit Streams and Stop Sharing

**Included in Free.**

Account sharing is the commonest leak. MediaShield limits how many videos one account can play at once, and can cut off an account's active streams.

## Before you start

- The limit applies to logged-in accounts on Standard or Strict videos, because those start watch sessions.
- Guests are never counted. With no account there is nothing to share.

## Set the limit

1. Go to **MediaShield > Settings > Access**.
2. Set **Max Concurrent Streams**. The default is 2 and the lowest allowed is 1.

A viewer who starts one more stream than allowed sees "Too many active streams. Please close another video first." The `mediashield_concurrent_limit_reached` action fires so you can log it.

## How a stream stops counting

- The player reports every 30 seconds.
- A session with no report for 5 minutes stops counting toward the limit.
- Closing the tab ends the session straight away.
- An hourly job marks sessions with no report for 10 minutes as finished.

## Cut off one account

There is no button for this yet. Send an authenticated request as an administrator:

```
POST /wp-json/mediashield/v1/session/revoke-user
{ "user_id": 42 }
```

This ends every active session for that user and fires `mediashield_user_access_revoked`. It stops current streams. To stop future viewing, also remove their role or access in your membership plugin.

## Find the sharing

- **Viewers** shows each account's videos watched and last active time.
- With MediaShield Pro, suspicious-activity detection flags accounts seen from several locations. It records rows for you to review and does not email you.

## Next steps

- [Restrict Who Can Watch](08-restrict-who-can-watch.md)
- [Watermarks](01-watermarks.md)
