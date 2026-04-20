# Protection Philosophy: What MediaShield Actually Protects

Before you configure MediaShield, please read this. It's the most important thing we can tell you about video protection.

## The short version

No WordPress video plugin can stop someone from screen-recording your video or pointing a camera at their monitor. Anyone who tells you otherwise is lying.

MediaShield does something different, and still valuable:

* Deters casual sharing. Most piracy is lazy, not determined.
* Traces leaked content back to the viewer with watermark forensics.
* Enforces access rules: login, roles, concurrent streams, domain whitelist.
* Measures engagement with sessions, milestones, and heatmaps.
* Captures leads through the email gate (Pro).

If you need "no one can ever record this video," you need a $30,000/year broadcast-grade setup, not MediaShield. Even that setup can be defeated by a phone camera.

## The threat model MediaShield is built for

You're a course creator. Your $500 course sits on WordPress. A student wants to share it on a private Telegram group so their friends don't pay.

MediaShield's answer:

* Their name and IP appear on every frame as a watermark. They think twice before sharing.
* Their login streams on only 2 devices at once. They can't hand out credentials.
* If they share credentials anyway, we detect 3 IPs in 6 hours and send you an alert.
* If they screen-record and post it, the watermark tells you exactly who leaked it. You deactivate their account and chase the leak.

This stops 90 percent of casual piracy. Not because recording became impossible, but because leakers are now identifiable and accountable.

## What MediaShield does not stop

Be honest with yourself and with your customers.

| Attack | MediaShield's response |
|---|---|
| Screen recording (OBS, macOS screen capture, phone camera) | Cannot be blocked. The watermark makes the recording traceable. |
| Saving the video URL from browser DevTools | Free hides the URL from View Source. Pro encrypts the self-hosted video with ClearKey. YouTube and Vimeo iframe URLs stay visible because the platforms publish them. |
| Stream Recorder or Video DownloadHelper browser extensions | Effective against unencrypted streams. Pro's ClearKey blocks them for encrypted self-hosted video. |
| Professional piracy (yt-dlp plus ffmpeg plus key extraction) | No software DRM stops a determined attacker. Only hardware Widevine L1 does that, and MediaShield does not ship it. |

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

* **Dynamic watermark.** User identity rendered on a canvas over every frame. Swaps position every X seconds. Survives fullscreen. Re-renders if the DOM is tampered. A MutationObserver pauses the video on tamper.
* **HMAC session tokens.** Cryptographically validated without a database lookup per heartbeat.
* **Concurrent stream limit.** Server-enforced with row locking so race conditions can't slip through.
* **Suspicious activity detection (Pro).** Multi-IP, rapid seek, DevTools open, VPN or proxy. Admin sees alerts in real time.
* **Revoke all sessions.** One REST call kills every active session for a user caught leaking.
* **Audit trail.** Every session logs IP, user agent, device, duration, and completion. Export it via WordPress privacy tools.

You get enough forensic data to act decisively when something leaks. That's what WordPress-native video protection at this price point should do.

## FAQ

**A competitor claims their plugin blocks screen recording. Are they lying?**
Either lying or using a proprietary codec that locks you into their ecosystem. Standards-based video (HLS and DASH, which everyone actually uses) cannot block screen recording without hardware DRM. The browser's own video rendering is accessible to screen-capture APIs.

**Can I use MediaShield plus a hardware DRM service together?**
Yes. Bunny Stream MediaCage Enterprise gives you Widevine L1 plus FairPlay. MediaShield adds watermarks, access control, analytics, and audit trail on top. Use Bunny for hosting and DRM, MediaShield for the WordPress layer.

**Is software DRM (ClearKey) worth paying for?**
For the $99 per year Pro price, yes. It stops yt-dlp and casual download tools. For high-value IP like films or live sports, no. Use Widevine L1.

**What about the watermark. Can't someone just crop it out?**
In theory, yes. In practice, the watermark moves position every 20 seconds by default, survives fullscreen, covers multiple areas over time, and anti-tamper pauses the video if someone removes the canvas. Cropping would also crop the video content. Most leakers don't bother. They just don't share rather than risk it.

## Bottom line

MediaShield is for real WordPress businesses with real threat models:

* Online courses
* Membership sites
* Internal training
* Agency client work

If that's you, we help you stop 90 percent of casual sharing and trace the other 10 percent. If you need enterprise broadcast DRM, please use a service built for that, and consider pairing it with MediaShield for the WordPress admin layer.

Honest tools, honest customers, honest deals. That's how we sell MediaShield.
