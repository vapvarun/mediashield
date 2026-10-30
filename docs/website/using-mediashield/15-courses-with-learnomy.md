# Courses with Learnomy

**Included in Free.** Needs the Learnomy plugin. Learnomy is not included in MediaShield.

Learnomy can place a MediaShield video inside a lesson, so the lesson plays in the protected player.

## Why use it

Lessons in Learnomy play in the protected player, so the same rules, watermark and watch data apply to course content without extra setup per lesson.

## Before you start

- Learnomy and MediaShield are both active.
- The video exists in MediaShield and is published. See [Add Your First Video](../getting-started/04-add-your-first-video.md).

## Steps

1. In Learnomy, edit the lesson.
2. Set **Content type** to **Protected video (MediaShield)**. This choice appears only while MediaShield is active.
3. In **Protected video**, pick the video from the list.
4. Save the lesson.

Learnomy stores the video's ID in the lesson and shows it with the MediaShield player. The student sees the watermark, the login and role checks, and the player settings you chose.

## What to know

- The Learnomy lesson editor lists a limited number of videos in a dropdown. If your library is very large and the video is missing, check that it is published.
- Learnomy's "minimum video watch percentage" rule does not apply to MediaShield lessons. The MediaShield player does not report watch percentage to Learnomy, so the lesson completes through Learnomy's other rules or the student's mark-complete action.
- MediaShield still records the session, completion percentage and milestones. See them under **MediaShield > Viewers** and **Milestones**.
- If the video cannot play, the lesson shows a message that it uses a protected video that cannot be played right now. Check the video is published.
- The LMS adapters in MediaShield Pro cover LearnDash, Tutor LMS and LifterLMS. Learnomy is not one of them.
- For the mobile or app side, Learnomy's lesson data includes a signed MediaShield embed address that lasts 15 minutes. This is for developers; see [Developer Overview](../developer-guide/01-overview.md).

## Check it worked

Open the lesson as an enrolled student. The protected player appears with your watermark.

## Next steps

- [Integrations](18-integrations.md)
- [Restrict Who Can Watch](08-restrict-who-can-watch.md)
- [Analytics and Milestones](02-analytics-and-milestones.md)
