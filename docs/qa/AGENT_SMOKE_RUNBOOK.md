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
Journey: `audit/journeys/system/upgrade-keeps-lessons-playing.md`. The owner's bar is that nothing needs their attention and no lesson stops.
- `B1 @admin +upgrade: update 1.3.0 to this build -> db version 6, every self-hosted video plays at once, no notice or action asked of the owner`
- `B2 @admin +upgrade: wait for the claim job -> ms_file_claims_pending cleared, each self-hosted video owns its file, others hold an empty claim`
- `B3 @admin +upgrade !refuse: two videos named one file -> the older keeps it, the other is refused`
- `B4 @admin +many: 2000 self-hosted videos -> job runs in batches, no timeout, resumes if interrupted`
- `B5 @admin +upgrade: settings, playlists, tags and watch history unchanged after the update`

## C. Core flows (combo and free)
- C1 Admin: open every MediaShield route at 1280 and 390. Each renders content, no horizontal overflow, no console error. In free mode the Pro-only routes fall back to the dashboard.
- C2 Single video: self-hosted, Vimeo, YouTube and Bunny each play inside the protected player with the badge, and the watermark shows the viewer's name and IP (taken from the session, not the page HTML).
- C3 Playlist: every item plays in the protected player, switching items closes the old session and opens a new one, autoplay waits through the countdown and moves on.
- C4 Embed block: a core YouTube or Vimeo block for a video in the library is wrapped at full size at 1280 and 390. An embed with no matching video is left alone.
- C5 Access: a logged-out visitor is refused when Require Login is on. A member plays. Stream limits count other devices, not players on one page.
- C6 Stale page: a page open longer than the token life resumes playback after asking for a fresh address.
- C7 Privacy: a logged-out `curl` of a page with a protected player shows no visitor IP or name in the HTML.
- C8 Heartbeat: one real token gets 4 beats a minute, two real tokens have separate budgets, a loop of made-up tokens is refused and adds at most one rate row.
- C10 Ownership (journey `security/file-ownership`): another Author naming a video's stored file, or pasting its URL, cannot play it; deleting that Author's video leaves the file; deleting the real video removes only its own file; pasting an ordinary uploads video still plays. card:10426248216
- C11 Stream address (journey `customer/stream-address-stays-private`): a visitor who may not watch gets no stream address from the page, the playlist player route or the HTML, for Bunny direct play and for a self-hosted video with a CDN address; a playlist password answers 404. card:10426248133
- C12 Owner view: a new site owner reading the Videos list, Settings and Analytics sees no raw ids, no empty cards without a next step, and every count matches the rows (large library: filter, sort and page at 2000 videos).
- C9 Pro: YouTube library browse and import, playlist funnel lists the playlist's videos, rapid-seek alert fires on a video with no stored duration.

## D. Regression guards
Every closed Bugs card adds its check here. Current guards run in `tests/unit` (HeartbeatRateLimitTest, ReleaseSecurityTest for 10426248133 and 10426248216, GuestSessionTest and the rest of the suite) and Pro `tests/unit/PlaylistFunnelTest`.

## E. Settings
Every setting saved in the admin is read back and changes behaviour. The REST round trip over all registered settings must save and return each value.

## F. Release gates
PHPUnit (Free and Pro), PHPStan level 5, WPCS, contract audit, functional cert, security sweep, Plugin Check on the built Free zip, docs lint at 0 errors.

## Failure protocol
Classify each failure as from our code or for another plugin or theme. Hard failures (fatal, a save that does not persist, a gate that does not hold, a privacy leak, data loss) block the release. Soft ones (layout, wording) are filed and argued on the card.
