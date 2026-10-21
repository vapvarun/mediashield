# MediaShield smoke runbook

Contract for the pre-release walk of MediaShield (Free) and MediaShield Pro. Each section says what must be true. Check it by looking at the page or the database, never by "no errors".

Site: `http://lms.local` (Reign + Learnomy). `?autologin=1` for admin, `?autologin=member` for a member. Fixtures and rules are in `docs/qa/qa-config.json` and the QA handoff. Never delete or edit media at bunny.net.

Modes: `free` (Pro deactivated) and `combo` (Free + Pro).

## A. Fresh install
Install each built zip on a clean site, activate.
- Plugin list shows the version, front page returns 200, `debug.log` is empty.
- The bundled update SDK loads (`EasyDigitalDownloads\Updater\Versions` exists) and its script and stylesheet return 200. Pro ships no second SDK copy.
- Remove the installed SDK `src` folder: the site stays up with no fatal. Restore it.

## B. Upgrade
Install the previous release, then the new zip over it. Settings keep their values and existing videos still play.

## C. Core flows (combo and free)
- C1 Admin: open every MediaShield route at 1280 and 390. Each renders content, no horizontal overflow, no console error. In free mode the Pro-only routes fall back to the dashboard.
- C2 Single video: self-hosted, Vimeo, YouTube and Bunny each play inside the protected player with the badge, and the watermark shows the viewer's name and IP (taken from the session, not the page HTML).
- C3 Playlist: every item plays in the protected player, switching items closes the old session and opens a new one, autoplay waits through the countdown and moves on.
- C4 Embed block: a core YouTube or Vimeo block for a video in the library is wrapped at full size at 1280 and 390. An embed with no matching video is left alone.
- C5 Access: a logged-out visitor is refused when Require Login is on. A member plays. Stream limits count other devices, not players on one page.
- C6 Stale page: a page open longer than the token life resumes playback after asking for a fresh address.
- C7 Privacy: a logged-out `curl` of a page with a protected player shows no visitor IP or name in the HTML.
- C8 Heartbeat: one real token gets 4 beats a minute, two real tokens have separate budgets, a loop of made-up tokens is refused and adds at most one rate row.
- C9 Pro: YouTube library browse and import, playlist funnel lists the playlist's videos, rapid-seek alert fires on a video with no stored duration.

## D. Regression guards
Every closed Bugs card adds its check here. Current guards run in `tests/unit` (HeartbeatRateLimitTest, GuestSessionTest and the rest of the suite) and Pro `tests/unit/PlaylistFunnelTest`.

## E. Settings
Every setting saved in the admin is read back and changes behaviour. The REST round trip over all registered settings must save and return each value.

## F. Release gates
PHPUnit (Free and Pro), PHPStan level 5, WPCS, contract audit, functional cert, security sweep, Plugin Check on the built Free zip, docs lint at 0 errors.

## Failure protocol
Classify each failure as from our code or for another plugin or theme. Hard failures (fatal, a save that does not persist, a gate that does not hold, a privacy leak, data loss) block the release. Soft ones (layout, wording) are filed and argued on the card.
