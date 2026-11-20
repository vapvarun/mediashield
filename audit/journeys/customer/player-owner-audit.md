---
journey: player-owner-audit
plugin: mediashield
priority: high
roles: [subscriber, guest]
covers: [player-wrapper, watermark, card:10530930409, card:10530930523, card:10530930729]
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
2. card:10530930523 - Resume Playback on. Give the subscriber a last session on SELF stopped at the end (max_position = length), open SELF: **Expect** no resume offer. Stopped at 3 s: **Expect** no offer. Stopped at 2:00: **Expect** a small "Resume from 2:00" chip whose bottom edge sits above the control bar, with the bar fully visible at 390px; it disappears after about 8 seconds, or at once on play; clicking it plays from 2:00. **On fail**: `assets/js/player-wrapper.js` (`resumeWorthOffering()`, `showResumePrompt()`).
3. card:10530930729 - Set Settings > Lesson Completion > Require this % watched to 80. As the enrolled subscriber open LESSON with no watch history: **Expect** a "Watch 80% to complete" requirement and Complete disabled; `POST /wp-json/learnomy/v1/lessons/{id}/complete` (nonce, `video_pct: 100`) answers 422 `completion_rules_not_met`. Give the subscriber a session at 85% for that video and keep the page open: **Expect** Complete turns on within about 10 seconds without a reload. With Pro and LearnDash, a lesson linked to a video: **Expect** Mark Complete disabled reading "Watch 80% to complete", and a submitted form leaves the lesson incomplete. Set 0: **Expect** both behave as before. **On fail**: `includes/Integrations/Learnomy.php::require_watch()`, `includes/Access/SessionManager.php::watched_percent()`, Pro `includes/LMS/LearnDashAdapter.php`.
