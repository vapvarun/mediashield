# Protection Philosophy — What MediaShield Actually Protects

Before you configure MediaShield, read this page. It's the most important thing we can tell you about video protection.

## The one-sentence truth

**No video protection plugin for WordPress can stop someone from screen-recording your video or pointing a camera at their monitor. Anyone who tells you otherwise is lying.**

MediaShield's job is different — and genuinely valuable:

- **Deter casual sharing** (most piracy is lazy, not determined)
- **Trace leaked content back to the viewer** (watermark forensics)
- **Enforce access rules** (login, roles, concurrent streams, domain whitelist)
- **Measure engagement** (sessions, milestones, heatmaps)
- **Capture leads** (email gate, Pro)

If you need "no one can ever record this video no matter what," you need a $30,000/year Widevine L1 + HDCP 2.2 broadcast-grade setup, not MediaShield — and even that can be defeated by a phone camera.

## The threat model MediaShield is built for

**Scenario:** You're a course creator. Your $500 course is on WordPress. A student wants to share it on a private Telegram group so their friends don't have to pay.

What MediaShield does about this:
- Their name + IP appears on every frame as a watermark → they're scared to share
- Their login can only stream on 2 devices at once → they can't hand out credentials
- If they share credentials anyway, we detect 3 IPs in 6 hours → you get an alert
- If they screen-record and post it → the watermark tells you *exactly* who leaked it → you deactivate their account and optionally chase down the leak

**This stops 90% of casual piracy.** Not because we made recording impossible — we didn't — but because we made leakers *identifiable* and *accountable*.

## What MediaShield does NOT stop

Be honest with yourself and your customers:

| Attack | MediaShield's response |
|---|---|
| Screen recording (OBS, macOS screen capture, phone camera) | Cannot be blocked. Watermark makes the recording traceable. |
| Saving the video URL from browser DevTools | Free: URL is hidden from View Source. Pro: self-hosted video is encrypted (ClearKey). YouTube/Vimeo iframe URLs are always visible — the platforms publish them. |
| Stream Recorder / Video DownloadHelper browser extensions | Works against unencrypted streams. Pro ClearKey blocks for encrypted self-hosted video. |
| Professional piracy (yt-dlp + ffmpeg + key extraction) | No software DRM can stop a determined technical attacker. Only hardware Widevine L1 can — and MediaShield does not ship it. |

If any of those attacks are your primary concern, you need:
- **Bunny Stream MediaCage Enterprise** (~$99/mo) — adds Widevine L1 + FairPlay
- **VdoCipher** (~$350+/yr) — hosted Widevine + FairPlay
- **Muvi OTT** — enterprise OTT platform

MediaShield can work *alongside* those services (we add watermark + access control + analytics), but we do not replace them for hardware DRM needs.

## Why watermarking matters more than encryption at this tier

Think about how piracy actually works:

1. Someone with legitimate access downloads or records the video.
2. They share it somewhere (Telegram, Reddit, their own site).
3. Others consume it for free.

Encryption stops step 1 *only for unsophisticated users*. A determined user bypasses it.

Watermarking doesn't stop any step — but it makes step 1 risky. The leaker knows their name and IP will be on every frame of the leaked copy. When you catch the leak, you:
- Know exactly who did it
- Deactivate their account
- Optionally pursue them legally
- Use it as a case study to deter others

**Forensic deterrence > cryptographic prevention** for almost all WordPress course/membership use cases.

## How to set honest expectations with your own customers

When selling your course or membership to buyers, don't claim "piracy-proof" or "unhackable." You'll lose trust the first time someone screen-records. Instead:

> "Your purchase is tied to your account. Videos are watermarked with your identity. Sharing or recording is traceable — please don't do it. If we detect sharing, the account is terminated without refund."

That's honest, enforceable, and legally clean. It deters 90% of casual sharing without making a claim you can't back up.

## How MediaShield supports this philosophy technically

- **Dynamic watermark** — user identity rendered on-canvas over every frame, swaps position every X seconds, survives fullscreen, re-renders if DOM-tampered (`MutationObserver` pauses video on tamper)
- **HMAC session tokens** — cryptographically validated without DB lookup per heartbeat
- **Concurrent stream limit** — server-enforced with row locking to prevent race conditions
- **Suspicious activity detection (Pro)** — multi-IP, rapid-seek, DevTools open, VPN/proxy — admin sees alerts in real time
- **Revoke-all-sessions** — one REST call to kill every active session for a user who's caught leaking
- **Audit trail** — every session logged with IP, user agent, device, duration, completion — GDPR-exportable

You get enough forensic data to act decisively when something leaks. That is what WordPress-native video protection at this price point should do.

## FAQ

**Q: A competitor claims their plugin blocks screen recording. Are they lying?**
A: Either lying or using a proprietary codec that locks you into their ecosystem. Standards-based video (HLS/DASH — what everyone actually uses) cannot block screen recording without hardware DRM. The browser's own video rendering is accessible to screen-capture APIs.

**Q: Can I use MediaShield + a hardware DRM service together?**
A: Yes. Bunny Stream MediaCage Enterprise gives you Widevine L1 + FairPlay; MediaShield adds watermarks, access control, analytics, and audit trail on top. Use Bunny for hosting/DRM, MediaShield for the WordPress layer.

**Q: Is software DRM (ClearKey) worth paying for?**
A: For the $99/yr Pro price, yes — it stops yt-dlp and casual download tools. For high-value IP (films, live sports), no — use Widevine L1.

**Q: What about the watermark — can't someone just crop it out?**
A: In theory, yes. In practice: the watermark moves position every 20 seconds by default, survives fullscreen, covers multiple areas over time, and anti-tamper pauses the video if someone tries to remove the canvas. Cropping would also crop the video content. Most leakers don't bother — they just don't share rather than risk it.

## Bottom line

MediaShield is for real WordPress businesses with real threat models:
- Online courses
- Membership sites
- Internal training
- Agency client work

If that's you, we'll help you stop 90% of casual sharing and trace the other 10%. If you need enterprise broadcast DRM, please use a service built for that — and consider pairing it with MediaShield for the WordPress admin layer.

Honest tools, honest customers, honest deals. That's how we sell MediaShield.
