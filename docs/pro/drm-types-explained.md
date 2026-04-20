# DRM Types Explained — What MediaShield Pro Protects

Before configuring DRM, it helps to understand what different DRM systems can and cannot do. This page is written to help you set accurate expectations with your own customers.

## Short version

MediaShield Pro ships with **ClearKey DRM** — software-based AES-128 encryption. It stops casual downloads, provides a watermark trail, and makes URL-based scraping harder. It does **not** block screen recording.

If you need hardware-backed DRM that can block screen recording on supported devices, you need **Widevine L1** (Google) or **FairPlay** (Apple) — these require licensed partners and are an order of magnitude more expensive.

## DRM tiers compared

| Feature | ClearKey (MediaShield Pro) | Widevine L3 | Widevine L1 | FairPlay |
|---|---|---|---|---|
| Encryption | AES-128 | AES-128 | AES-128 | AES-128 |
| Key storage | Browser JavaScript | Browser CDM (software) | Hardware TEE | Hardware TEE |
| Key extractable via DevTools | Yes (with effort) | Harder | No | No |
| Blocks screen recording | No | No | Yes (on supported devices) | Yes (on supported devices) |
| Works on Safari / iOS | Yes (HLS) | No | No | Yes |
| Works on Chrome / Android | Yes | Yes | Yes (on supported devices) | No |
| Certification required | No | Yes (free) | Yes (paid, vetted) | Yes (Apple Developer) |
| Typical pricing | Included | $0.005/playback via service | $$$ | Apple Developer ($99/yr) |
| Who provides it | Shaka Player (open source) | Google Widevine | Google Widevine | Apple |

## What ClearKey actually does

1. Your video file is encrypted with an AES-128 key before delivery.
2. The encrypted video is delivered as DASH or HLS manifest + segments.
3. The player (Shaka Player) requests the decryption key from our license endpoint.
4. Our endpoint verifies the user's session (login + access rules) before serving the key.
5. The player decrypts and plays the video in the browser.

**What this stops:**
- Direct download of the `.mp4` / segment files (they're encrypted — useless without the key)
- URL scraping tools like `yt-dlp` that grab unencrypted HLS streams
- Sharing a download link with a non-authorized user
- Casual "View Source → Save As"

**What this does NOT stop:**
- Screen recording (OBS, ScreenFlow, macOS/Windows/iOS built-in)
- A determined developer using browser DevTools to extract the key from memory
- Someone pointing a phone camera at their monitor
- Re-encoding the decrypted playback stream via browser extensions

## When ClearKey is enough

- Course creators protecting educational content where casual sharing is the risk
- Membership sites where videos are part of the paid benefit
- Internal training videos within an organization
- Any scenario where **forensic traceability** (watermark + session logs) matters more than absolute prevention

## When you need more than ClearKey

If you're distributing content worth thousands of dollars per leak (film premieres, live sports, high-value IP), you need hardware DRM. MediaShield Pro does not ship Widevine L1 or FairPlay out of the box. Options:

- **Bunny Stream MediaCage Enterprise** (~$99/mo minimum) — adds Widevine + FairPlay on top of Bunny hosting. MediaShield Pro can work alongside it (we handle access control + watermarks; Bunny handles the hardware key exchange).
- **VdoCipher** (~$350+/yr) — hosted service with Widevine L1 + FairPlay.
- **Custom Widevine partnership** — requires signing agreements with Google + a CDN that supports it.

## Configuring ClearKey DRM

1. Go to **MediaShield → Settings → DRM**
2. Choose your method:
   - **Bunny Stream (cloud)** — easiest; Bunny handles packaging
   - **Local Shaka Packager** — you run the Shaka Packager CLI on your server
3. Set license duration (streaming vs persistent)
4. Enable on a per-video basis via the video edit screen

The license endpoint automatically verifies:
- User is logged in (if required)
- User has the role required for the video
- Concurrent stream limit not exceeded
- Domain is whitelisted (if configured)

## Honest positioning for your customers

When explaining video protection to your own customers, use:

> "We encrypt the video so it can't be downloaded directly, and we watermark every playback with the viewer's identity. If the video leaks on Telegram or a forum, the watermark tells us who did it. We can't stop someone from pointing a camera at their screen — no technology can — but we can make ripping impractical and make leakers traceable."

This sets expectations honestly and positions the product correctly.

## FAQ

**Q: Why not just use Widevine?**
A: True Widevine L1 requires a license from Google and hardware-level access on the user's device. It costs real money and only works on specific Chromecast / Android / Windows devices in secure modes. For WordPress-based course sites, ClearKey is the right tradeoff between protection and cost.

**Q: Can I add Widevine later?**
A: Yes. MediaShield Pro's player abstraction supports swapping DRM backends. If you move to Bunny MediaCage Enterprise or integrate a Widevine service, our watermark + session + access control layer continues to apply.

**Q: What happens on iOS / Safari?**
A: Shaka Player falls back to native HLS with AES-128 encryption. Your video is still encrypted in transit; the key exchange flows through our license endpoint with session validation. FairPlay (Apple's DRM) is not used — that would require an Apple certificate + separate packaging.

**Q: Is the license key visible in DevTools?**
A: Yes — with effort. ClearKey's keys are visible in the JS debugger if the user knows where to look. This is the tradeoff of software DRM. For most course/membership use cases, this threat model is acceptable because the forensic watermark + user-level access revocation are the primary deterrents.
