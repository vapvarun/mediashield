# Analytics and Milestones

Analytics only exist for videos at **Standard** or **Strict** protection level. Those are the levels that start a watch session. Videos set to None or Basic play without recording anything, and never appear in any of the screens below.

## Why use it

You see which videos people start, how far they get and who finished, without a separate analytics tool. Milestones record progress points you can act on, for example by tagging members who finished.

## The Dashboard

Go to **MediaShield > Dashboard** to see your analytics overview.

The date range selector at the top offers Today, Last 7 days (the default), Last 30 days, and Last 90 days. "Today" means the last 24 hours. Daily bars in the chart are grouped by your WordPress site timezone, so late-evening sessions land on the right calendar day.

### Stat cards

- **Total Videos** - published videos in your library. Not affected by the date range.
- **Views** - one per member per video in the selected period, however many times they play it (a guest counts once per playback session; a page load that never played is not counted).
- **Avg Completion** - the average, over viewers and videos, of each viewer's furthest completion of each video in the period. A viewer who watched a video twice, to 40% and then to the end, counts once at 100%. A page load that never played is not counted.
- **Watching now** - distinct viewers playing a video in the last 5 minutes. A page left open that never played is not a viewer. This is live, and ignores the date range.

### Activity chart

A daily chart of views over the selected period. Each view sits on the day of its first play, so the days add up to the Views card.

### Top Videos

The five most-viewed videos for the active date range (views, one per member), with each one's average completion (each viewer's furthest point, averaged).

### Recent Milestones

The most recent milestones reached across all videos, within the same date range as the rest of the dashboard.

## Per-viewer analytics

Go to **MediaShield > Viewers** to see per-user watch activity. (This section was called Students in earlier versions; old links still resolve.)

The list shows every user who has watched at least one video, with videos watched, average completion (the average of their furthest completion on each video, the same per-video figure the member sees in My Videos), and last active time, and a search box for name or email.

Click a viewer to see their watch history: every video they have started, their progress, and when they last watched it. The detail view covers their 100 most recent sessions.

This is the page to open when a learner says their progress did not register. If their video is set to Basic or None, nothing was ever recorded, and that is the answer.

## Milestones

**MediaShield > Milestones** is a log, not a settings screen. It lists each milestone that has been reached - viewer, video, percentage, and how long ago - newest first, 20 per page.

By default MediaShield records four thresholds per video: 25%, 50%, 75%, and 100%. Each one is recorded once per viewer per video, so re-watching does not double-count. Developers can change the set with the `mediashield_milestone_thresholds` filter.

### Milestone tags

Milestone tags are configured **on the video**, not on the Milestones page. Open a video and use the Milestone Tags box: it offers 10%, 25%, 50%, 75%, and 100%, each with a tag name and an Active checkbox. Enabling a threshold here also makes that threshold tracked for the video, which is how 10% becomes available.

When a viewer crosses an enabled threshold:

1. The tag is created in the Tags library if it does not exist yet
2. The tag is linked to the video
3. An earn record - video, percentage, tag, and timestamp - is written to that viewer's user profile

Pro can act on the same moment to send email, fire webhooks, or apply CRM tags.

### Milestone hooks for custom integrations

The free plugin fires PHP actions when milestones are reached, so you can wire your own logic. For example, mark a LearnDash lesson complete when a video reaches 100%:

```php
add_action( 'mediashield_milestone_100', function( $user_id, $video_id ) {
    // Your LMS integration code here
}, 10, 2 );
```

The `mediashield_milestone_reached` action fires for all percentages and passes the percentage as a parameter. There are also percentage-specific actions: `mediashield_milestone_25`, `mediashield_milestone_50`, `mediashield_milestone_75`, `mediashield_milestone_100`.

For the full action signatures, see the [Developer Guide - Hooks and Filters](../developer-guide/02-hooks-and-filters.md).

## Tags

MediaShield has two kinds of tags, kept apart:

- **Video tags** organise your videos. Add them on this screen or in the **Tags** box on a video. **MediaShield > Tags** lists each one with its slug and how many videos carry it. Click the count to open the Videos list filtered to that tag, or pick a tag in the Videos list's **Tag** filter. **Rename** changes a tag everywhere it is used; **Delete** also removes it from every video. Deleting a video removes its tag links, and any tag left attached to no videos is removed with it.
- **Milestone tags** go on a member when they reach a milestone you set on a video (see above), for your CRM, LMS or automation rules. They show on the member's page under **MediaShield > Viewers** and are not video tags.

Before 1.4.4 a milestone tag was also put on the video and listed here. After updating to 1.4.4 a background job takes those links off (a large site may take a few minutes); the member keeps every tag they earned, and tags you created or put on videos yourself stay.

## What to expect on a fresh install

The dashboard shows real data only. There are no demo numbers. After installing, watch a video as a logged-in user for at least 30 seconds, then refresh the dashboard to see your first session counted. If nothing appears, check the video's protection level before anything else.

## One way to count, on every screen

Every count uses the same two rules, in Free and Pro (Dashboard, Viewers, a video's stats, My Videos, and Pro's funnel, device breakdown and weekly digest): a view is a session that played (a page load alone is not), and a viewer's completion of a video is the furthest point of any of their playback sessions. So the same member shows the same numbers wherever you look.
