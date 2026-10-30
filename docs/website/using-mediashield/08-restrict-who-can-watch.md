# Restrict Who Can Watch

**Included in Free.** Role restriction is a Free feature. Pro does not add to it.

Use this guide to limit a video to members, to one role, or to your own websites. The full order of checks is in [Access Control](../configuration/04-access-control.md).

## Why use it

The right people watch and nobody else, without creating a separate page or site for each group. It also stops other websites embedding your videos.

## Before you start

- Administrators are never blocked. Test with another account or a private window.
- If a membership or LMS plugin decides who has paid, tell MediaShield through the `mediashield_can_watch` filter. See [Hooks and Filters](../developer-guide/02-hooks-and-filters.md).

## Let only signed-in members watch

1. Go to **MediaShield > Settings > General**.
2. Turn **Require Login** on (the default).
3. Optional: change **Login Overlay Text** and **Login Button Text** under Login and Access Messages.

Visitors who are not logged in see your message and a login button instead of the player. The button returns them to the same page after login.

## Let guests watch

Turn **Require Login** off. Guests then get a real session, so their views and watch time are recorded. They are not counted against the stream limit, and milestones do not fire for guests.

## Restrict one video to one role

1. Open the video.
2. In **Video Settings**, choose a role under **Restrict to Role**.
3. Click **Update**.

The match is exact. A video for `subscriber` is not watchable by an editor. Logged-in people without the role see **Access Denied Text**. Logged-out visitors see the login message.

## Allow only your own websites to embed

1. In **Settings > Access**, fill **Allowed Domains**.
2. Enter one domain per line, or separate them with commas. A pasted web address works too, only the domain is used.

With a list set, your own domain always passes, listed domains and their subdomains pass, and everything else is refused. A request with no Referer header is refused by default.

This is a Referer check. A Referer can be forged, so treat it as a fence, not a lock.

## Gate by membership level or course enrolment

Free has no built-in membership connector. Use the `mediashield_can_watch` filter. MediaShield Pro includes adapters for LearnDash, Tutor LMS and LifterLMS. Learnomy places MediaShield videos in lessons itself: see [Courses with Learnomy](15-courses-with-learnomy.md).

## What viewers see

A viewer who fails a check sees your **Access Denied Text** or the login prompt instead of the video. On another website that is not on your list, the video does not play.

## Check it worked

Log in as a user without the role and open the page. You should see the Access Denied Text and no video.

## Next steps

- [Limit Streams and Stop Sharing](09-limit-streams-and-stop-sharing.md)
- [Roles and Permissions](16-roles-and-permissions.md)
