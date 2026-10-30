# Integrations

**Free + Pro.** Each integration below says which edition it needs. Free plays and protects videos from every host and works with Learnomy and WB Ad Manager. Pro adds account connections, course-plugin links, actions and exports.

MediaShield sits between your video and your viewer. This page lists everything it connects to, what works, what does not, and where to find the setup steps. If something is not on this page, MediaShield does not have a connector for it.

## Why use it

You keep the tools you already use. Your videos stay on the host you pay for, your lessons stay in your course plugin, your sponsors stay in your ad plugin, and MediaShield adds the protected player, access rules and watch data on top. You do not move anything to start.

## All integrations at a glance

| Integration | What it is for | Edition | Setup guide |
|-------------|----------------|---------|-------------|
| YouTube | Play and protect YouTube videos. Browse, import and upload from your channel | Play: Free. Connect, browse, import, upload: Pro | [Video Hosts](10-video-hosts.md), [Connect a Video Platform](https://wpmediashield.com/docs/pro/features/01-platform-connections/) |
| Vimeo | Same as YouTube | Play: Free. Connect, browse, import, upload: Pro | [Video Hosts](10-video-hosts.md), [Connect a Video Platform](https://wpmediashield.com/docs/pro/features/01-platform-connections/) |
| Wistia | Same as YouTube | Play: Free. Connect, browse, import, upload: Pro | [Video Hosts](10-video-hosts.md), [Connect a Video Platform](https://wpmediashield.com/docs/pro/features/01-platform-connections/) |
| Bunny Stream | Play in the MediaShield player, with the host's own encoding and delivery | Play: Free. Connect, browse, import, upload, direct playback, encoding webhook: Pro | [Video Hosts](10-video-hosts.md), [Bunny playback](https://wpmediashield.com/docs/pro/features/12-bunny-playback/), [Bunny webhook](https://wpmediashield.com/docs/pro/features/14-bunny-webhook/) |
| Your own server | Upload an `.mp4`, `.webm`, `.mov` or `.m4v` file, or paste a direct file or HLS (`.m3u8`) address | Free | [Upload a Video File](11-upload-a-video-file.md) |
| Learnomy | Put a protected video in a Learnomy lesson | Free | [Courses with Learnomy](15-courses-with-learnomy.md) |
| LearnDash, Tutor LMS, LifterLMS | Link a video to a lesson, complete the lesson, require enrolment | Pro | [LMS Integration](https://wpmediashield.com/docs/pro/features/05-lms/) |
| WB Ad Manager | Sponsor video ads before, during and after a video | Engine and placement: Free. Choose ads per video: Pro | [Video Ads](13-video-ads.md), [Video Ads (Pro)](https://wpmediashield.com/docs/pro/features/11-video-ads/) |
| Membership plugins | Allow a video only to paying members | No built-in connector. A short snippet works in Free | [Membership plugins](#membership-plugins) |
| Page builders | Put a video on a page built with Elementor, Divi and similar | Free, through the shortcode or block | [Page builders](#page-builders) |
| Zapier, Make and any web address | Tell another system when a viewer reaches 25, 50, 75 or 100 percent | Pro | [Milestone Actions](https://wpmediashield.com/docs/pro/features/06-milestone-actions/) |
| Email | Send an email at a milestone, and a weekly summary | Pro | [Milestone Actions](https://wpmediashield.com/docs/pro/features/06-milestone-actions/), [Weekly Digest](https://wpmediashield.com/docs/pro/features/10-weekly-digest/) |
| ip-api.com | Optional VPN and proxy lookup for suspicious-activity flags | Pro | [Suspicious Activity](https://wpmediashield.com/docs/pro/features/09-suspicious-activity/) |
| Mobile apps | Show a protected video inside a native app | Free, for developers | [Extension Architecture](../developer-guide/05-extension-architecture.md) |
| WordPress privacy tools | Export or erase one person's watch data | Free, and Pro adds its own data to the same tools | [Privacy, Retention and Uninstall](17-privacy-retention-and-uninstall.md) |

## Video hosts

**Free plays, Pro connects.**

What it does for you: viewers watch your YouTube, Vimeo, Wistia or Bunny video inside the MediaShield player wrapper, with the login gate, watermark and watch tracking.

To use a host in Free:

1. Go to **MediaShield > Videos > Add New Video**.
2. Paste the video's address into **Video URL**.
3. Check that **Detected Platform** names your host.
4. Add a title and click **Publish**.

To use a host in Pro, connect the account once under **MediaShield > Platforms**, then browse and import in bulk. The fields each host asks for are listed in [Connect a Video Platform](https://wpmediashield.com/docs/pro/features/01-platform-connections/).

What works and what does not:

- YouTube, Vimeo and Wistia play in the host's own player, so the speed selector, volume keys and source hiding belong to the host.
- Source hiding works for self-hosted files only.
- Bunny videos play in Bunny's own embed unless Pro direct playback is on. Direct playback brings the watermark, speed control, accurate progress and in-video ads to Bunny.
- MediaShield does not convert video, add captions or add a quality selector. It does not encrypt: if a stream is encrypted, the host did that.

## Learnomy

**Free.** Needs the Learnomy plugin.

What it does for you: a lesson in Learnomy plays its video in the protected player, so the same rules and watermark apply as everywhere else.

1. In Learnomy, edit the lesson.
2. Set **Content type** to **Protected video (MediaShield)**.
3. In **Protected video**, pick the video.
4. Save the lesson.

Limit: Learnomy's minimum watch percentage rule does not apply to these lessons. MediaShield still records the session and milestones. The full guide is [Courses with Learnomy](15-courses-with-learnomy.md).

## LearnDash, Tutor LMS and LifterLMS

**Pro.** Needs the course plugin and MediaShield Pro.

What it does for you: finishing the video marks the lesson complete, and you can keep a video for enrolled students only. MediaShield detects the course plugin by itself.

1. Open the video under **Videos**.
2. In **LMS Integration**, choose the lesson and set **Complete at %**.
3. Optionally tick **Require enrollment to watch**.
4. Click **Update**.

Limits: lessons only (plus topics in LearnDash), up to 200 per list, and completion happens at the 25, 50, 75 or 100 percent milestone. The full guide is [LMS Integration](https://wpmediashield.com/docs/pro/features/05-lms/).

## WB Ad Manager

**Free plays the ads. Pro chooses them per video.** Needs WB Ad Manager with at least one ad of type Video Ad.

What it does for you: sponsor ads play inside the protected player, and the plays count in WB Ad Manager's own statistics.

1. Create a video ad in WB Ad Manager.
2. Go to **MediaShield > Settings > Video Ads** and check **Enable In-Video Ads**.
3. Choose pre-roll, mid-rolls and skip rules.
4. Watch a video as a member to check.

Limits: a video in a platform's own embedded player (YouTube, or Bunny without direct playback) cannot carry in-video ads. MediaShield supplies no ads of its own. The full guide is [Video Ads](13-video-ads.md).

## Membership plugins

**No built-in connector.** MediaShield has no ready-made link to MemberPress, Paid Memberships Pro, Restrict Content Pro or similar plugins.

What you can do: add a small snippet that tells MediaShield whether a person may watch, using your membership plugin's own check. It works in Free.

```php
add_filter( 'mediashield_can_watch', function ( $result, $video_id, $user_id ) {
    if ( ! user_has_active_subscription( $user_id ) ) { // your plugin's own check
        return array( 'allowed' => false, 'reason' => 'An active subscription is required.' );
    }
    return $result;
}, 10, 3 );
```

Replace `user_has_active_subscription` with your membership plugin's function. Administrators are never blocked, and MediaShield's own login, role and domain checks run first. Ask a developer to add it if you are not comfortable editing code. The filter is described in [Hooks and Filters](../developer-guide/02-hooks-and-filters.md).

## Page builders

**Free.** There is no Elementor or Divi widget, so nothing appears in a builder's widget panel.

What works: put the shortcode `[mediashield id=X]` in a builder's shortcode, text or HTML widget, or use the MediaShield Video block in the block editor. A builder that prints an ordinary video or a YouTube, Vimeo, Bunny or Wistia embed for a video that is already in your library is also picked up and wrapped. A video that was never added to the library is not protected. See [Shortcodes and Blocks](../configuration/05-shortcodes-and-blocks.md).

## Zapier, Make and other web addresses

**Pro.** What it does for you: when a viewer reaches a milestone, MediaShield sends the details to any service that accepts a web address call. That starts a Zapier or Make scenario, a CRM entry or a follow-up sequence.

1. In your tool, create a catch-hook or webhook trigger and copy its address.
2. In MediaShield, go to **MediaShield > Milestone Actions**.
3. On a milestone card, set the **Action Type** to **Fire Webhook** and paste the address into **Webhook URL**.
4. Click **Save Milestone Actions**, then **Test Fire**.

Limits: viewers must be logged in, the call is not retried when it fails, and the setting applies to every video. See [Milestone Actions](https://wpmediashield.com/docs/pro/features/06-milestone-actions/).

Pro also accepts a call in the other direction: Bunny Stream tells your site when encoding finishes. See [Bunny webhook](https://wpmediashield.com/docs/pro/features/14-bunny-webhook/).

## Email and the weekly digest

**Pro.** Milestone Actions can send one email to you or to the viewer when a milestone is reached, and the Weekly Digest emails a summary once a week. Both use your site's normal WordPress email. If your host blocks email, install an SMTP plugin first.

## Mobile apps

**Free, for developers.** A developer can ask MediaShield for a signed address that opens one protected video in an app's web view. The address works for 15 minutes and is checked against the viewer's access again each time it loads. See [Extension Architecture](../developer-guide/05-extension-architecture.md).

## Check it worked

For each integration you set up, open the page or lesson as an ordinary member, not as an administrator, because administrators skip the access checks. The video should play with your watermark, and a test action or ad should show.

## Next steps

- [Video Hosts](10-video-hosts.md)
- [Restrict Who Can Watch](08-restrict-who-can-watch.md)
- [Troubleshooting](../troubleshooting/01-common-problems.md)
