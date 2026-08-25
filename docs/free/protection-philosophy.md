# Protection Philosophy: What MediaShield Actually Protects

Before you configure MediaShield, please read this. It's the most important thing we can tell you about video protection.

## The short version

No WordPress video plugin can stop someone from screen-recording your video or pointing a camera at their monitor. Anyone who tells you otherwise is lying.

MediaShield does something different, and still valuable:

* Deters casual sharing. Most piracy is lazy, not determined.
* Traces leaked content back to the viewer with watermark forensics.
* Enforces access rules: login, roles, concurrent streams, domain whitelist.
* Measures engagement with sessions, milestones, and heatmaps.

If you need "no one can ever record this video," you need a $30,000/year broadcast-grade setup, not MediaShield. Even that setup can be defeated by a phone camera.

## The threat model MediaShield is built for

You're a course creator. Your $500 course sits on WordPress. A student wants to share it on a private Telegram group so their friends don't pay.

MediaShield's answer:

* Their name and IP appear on every frame as a watermark. They think twice before sharing. (This needs the video set to Standard or Strict -- the Basic tier has no watermark.)
* Their login streams on only 2 devices at once. They can't hand out credentials. The limit counts per account, so it applies to logged-in viewers, not to guests on a public video.
* If they share credentials anyway, Pro raises an alert -- by default when one account is seen from 5 different IP addresses within 12 hours, tightenable to 3 within 6. Free records sessions with their IPs but does not raise alerts.
* If they screen-record and post it, the watermark tells you exactly who leaked it. You deactivate their account and chase the leak.

This stops 90 percent of casual piracy. Not because recording became impossible, but because leakers are now identifiable and accountable.

## What MediaShield does not stop

Be honest with yourself and with your customers.

| Attack | MediaShield's response |
|---|---|
| Screen recording (OBS, macOS screen capture, phone camera) | Cannot be blocked. The watermark makes the recording traceable. |
| Saving the video URL from browser developer tools | For self-hosted video, free keeps the file address out of the page entirely and plays through a permission-checked address instead. Pro additionally encrypts the file with ClearKey. YouTube, Vimeo, Wistia and Bunny embeds keep their URLs, because a provider iframe cannot load without carrying the provider's video ID. |
| Stream Recorder or Video DownloadHelper browser extensions | Effective against unencrypted streams. Pro's ClearKey blocks them for encrypted self-hosted video. |
| Professional piracy (yt-dlp -- a command-line tool for downloading videos from web platforms -- plus ffmpeg plus key extraction) | No software DRM stops a determined attacker. Only hardware Widevine L1 does that, and MediaShield does not ship it. |
| Requesting a self-hosted video file directly by its web address | **Depends on your web server, and by default it is not blocked on nginx.** See below. |

### The one you have to check yourself: direct file access

If you self-host video (rather than using YouTube, Vimeo, Bunny or Wistia), the
files live in `wp-content/uploads/mediashield/`. MediaShield writes an
`.htaccess` file there telling the server to refuse direct requests.

**`.htaccess` is an Apache feature. nginx does not read it.** A large share of
WordPress hosting runs nginx, and on those servers the file is ignored -- so
anyone who knows or guesses a file's address can download the video without
logging in, and none of your access rules apply. Not the login requirement, not
per-video role restrictions, not the allowed-domain list. The player itself is
unaffected either way, because it never uses that address; it streams through
MediaShield, which checks permissions on every request. That last part depends on
**Hide Video Source URL** being on, which it is by default -- turn it off and the
player goes back to using the plain file address, putting it in your page markup
for anyone to copy.

Two things reduce the risk, and neither is a fix:

* Stored filenames include a random string, so an address cannot be worked out
  from a video's title. That makes guessing impractical. It does nothing about
  an address someone already has.
* **Tools > Site Health** runs a check that asks your own server for one of
  your video files and tells you what happened. If the server hands it over,
  the check reports a problem and gives you the exact nginx rule to fix it.

**Run that check if you self-host.** It is the only way to know which situation
you are in, because the presence of the `.htaccess` file tells you nothing about
whether your server honours it.

If you cannot change your server configuration, use a platform (YouTube, Vimeo,
Bunny, Wistia) rather than self-hosting. Those files never sit in your uploads
folder in the first place.

If any of those attacks are your main concern, you need:

* **Bunny Stream MediaCage Enterprise** at roughly $99 per month, which adds Widevine L1 and FairPlay.
* **VdoCipher** at roughly $350 per year, a hosted service with Widevine plus FairPlay.
* **Muvi OTT**, an enterprise OTT platform.

MediaShield works alongside those services: we add watermark, access control, and analytics on top. We do not replace them for hardware DRM.

## Why watermarking matters more than encryption at this tier

Think about how piracy actually works:

1. Someone with legitimate access downloads or records the video.
2. They share it somewhere (Telegram, Reddit, their own site).
3. Others consume it for free.

Encryption stops step 1 only for unsophisticated users. A determined user bypasses it.

Watermarking doesn't stop any step. It makes step 1 risky. The leaker knows their name and IP will be on every frame of the copy they share. When you catch the leak, you:

* Know exactly who did it.
* Deactivate their account.
* Optionally pursue them legally.
* Use it as a case study to deter others.

**Forensic deterrence beats cryptographic prevention** for almost every WordPress course or membership use case.

## How to set honest expectations with your customers

Don't claim "piracy-proof" or "unhackable." You'll lose trust the first time a student screen-records. Say this instead:

> Your purchase is tied to your account. Videos are watermarked with your identity. Sharing or recording is traceable. Please don't do it. If we detect sharing, the account is terminated without refund.

That's honest, enforceable, and legally clean. It deters 90 percent of casual sharing without making a claim you can't back up.

## How MediaShield supports this philosophy technically

* **Dynamic watermark.** User identity rendered on a canvas over every frame. Swaps position every X seconds. Survives fullscreen, because the whole player goes fullscreen rather than the bare video. Watched for tampering: delete or hide the overlay from developer tools and the video is paused and the player hidden -- MediaShield stops playback rather than redrawing, so there is no watermark-free moment to capture.
* **HMAC session tokens.** Cryptographically validated without a database lookup per heartbeat.
* **Concurrent stream limit.** Server-enforced with row locking so race conditions can't slip through. Applies to logged-in accounts.
* **Suspicious activity detection (Pro).** Multi-IP, rapid seek, developer-tools open, VPN or proxy. Admin sees alerts in real time.
* **Revoke all sessions.** Ends every active session a user holds in one action. **This has no button in the admin yet** -- it is reachable to developers through the plugin's API. Without code, the equivalent is removing the user's role or deactivating the account, which takes effect on their next request because every request re-checks permission.
* **Audit trail.** Every session logs IP, user agent, device, duration, and completion. Export it via WordPress privacy tools.

You get enough forensic data to act decisively when something leaks. That's what WordPress-native video protection at this price point should do.

## FAQ

**A competitor claims their plugin blocks screen recording. Are they lying?**
Either lying or using a proprietary codec that locks you into their ecosystem. Standards-based video (HLS and DASH, which everyone actually uses) cannot block screen recording without hardware DRM. The browser's own video rendering is accessible to screen-capture APIs.

**Can I use MediaShield plus a hardware DRM service together?**
Yes. Bunny Stream MediaCage Enterprise gives you Widevine L1 plus FairPlay. MediaShield adds watermarks, access control, analytics, and audit trail on top. Use Bunny for hosting and DRM, MediaShield for the WordPress layer.

**Is software DRM (ClearKey) worth paying for?**
For the $99 per year Pro price, yes. It stops casual download tools. For high-value IP like films or live sports, no. Use Widevine L1.

**What about the watermark. Can't someone just crop it out?**
In theory, yes. In practice, the watermark moves position every 30 seconds by default (and you can set that lower), survives fullscreen, covers multiple areas over time, and anti-tamper pauses the video if someone removes the overlay. Cropping would also crop the video content. Most leakers don't bother. They just don't share rather than risk it.

## Bottom line

MediaShield is for real WordPress businesses with real threat models:

* Online courses
* Membership sites
* Internal training
* Agency client work

If that's you, we help you stop 90 percent of casual sharing and trace the other 10 percent. If you need enterprise broadcast DRM, please use a service built for that, and consider pairing it with MediaShield for the WordPress admin layer.

Honest tools, honest customers, honest deals. That's how we sell MediaShield.
