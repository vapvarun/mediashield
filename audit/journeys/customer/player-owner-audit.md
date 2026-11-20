---
journey: player-owner-audit
plugin: mediashield
priority: high
roles: [subscriber, guest]
covers: [player-wrapper, watermark, card:10530930409]
prerequisites:
  - "Site reachable at $SITE_URL with the Reign theme (light and dark mode)"
  - "SELF: a published self-hosted mediashield_video at least 3 minutes long, Standard protection, on a page"
  - "LESSON: the same kind of video in a Learnomy lesson (content type Protected video) the subscriber is enrolled in"
  - "A subscriber with a display name and email (autologin)"
estimated_runtime_minutes: 10
---

# The player a member sees (1.4.3 owner and member audit)

What a member sees on a protected video: one set of controls, a resume
offer that never covers them, a watermark that names them without their IP,
and a denied state that says where to go. Walk each line at 1440px and 390px,
in the theme's light and dark mode.

## Check lines

1. card:10530930409 - As a subscriber open SELF. **Expect** one control bar (play, scrubber, time, mute, speed, one fullscreen button) and a round play button in the middle; no browser control bar, no second fullscreen icon, no "⋮" menu, and right-clicking the video offers no Picture in picture. Focus the player and press Space: **Expect** it plays; ArrowRight and ArrowLeft move 5 seconds; Tab reaches the play button, scrubber, mute, speed and fullscreen, and Enter on the play button pauses. Repeat in LESSON. **On fail**: `assets/js/player-wrapper.js` (`NativeAdapter` must leave `video.controls` false; `buildControlBar()`).
