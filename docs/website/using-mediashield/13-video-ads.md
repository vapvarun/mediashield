# Video Ads

**Free + Pro.** Everything on this page is in Free. Pro only adds a choice of which ads play on which video, described in [Video Ads (Pro)](https://wpmediashield.com/docs/pro/features/11-video-ads/).

MediaShield can play ad breaks inside the protected player. It does not supply ads. The ads come from video creatives you make in the WB Ad Manager plugin. Without that plugin and at least one video creative, no break plays and these settings have no effect.

## Why use it

If you sell sponsorship or show house promotions, ads can play inside the same protected player as the video, so they cannot be skipped past by scrolling and they are counted in your ad plugin's statistics. You decide how many breaks there are and whether viewers may skip.

## Before you start

- Install and activate WB Ad Manager and create at least one video ad creative.
- The video must play in the MediaShield player. A video in a platform's own embedded player (YouTube, or Bunny without Pro direct playback) cannot carry in-video ads.
- Mid-roll breaks need a known video length. Set **Duration** on the video, or let the player report it.

## Steps

1. Go to **MediaShield > Settings > Video Ads**.
2. Leave **Enable In-Video Ads** on. Turning it off stops all ads everywhere.
3. Choose **Pre-roll** to play one ad before the video.
4. Set **Mid-roll Count**, 0 to 10. Breaks are spaced evenly across the middle of the video, between the 10 and 90 percent points. The help text shows example times for a 46-minute video.
5. Choose how viewers may leave an ad:
   - **Require Full View** on: no Skip button, the ad must finish. Use it for compliance viewing.
   - **Require Full View** off: set **Skip Delay (seconds)**, 0 to 60. 0 lets viewers skip at once.
6. Leave **Show Break Markers** on to show ad positions on the seek bar.

## Settings and defaults

| Setting | Default | What it does |
|---------|---------|--------------|
| Enable In-Video Ads | On | Master switch. Off means no ads anywhere |
| Pre-roll | On | One ad before the video |
| Mid-roll Count | 3 | Breaks spread across the middle of the video (0 to 10) |
| Require Full View | Off | Viewer must watch each ad to the end. No Skip button |
| Skip Delay (seconds) | 5 | Seconds before Skip appears (0 to 60). Ignored when Require Full View is on |
| Show Break Markers | On | Marks on the seek bar for upcoming ad breaks |

## How many breaks you get

MediaShield plans one slot for the pre-roll and one for each mid-roll. If you have fewer creatives than slots, the remaining slots stay empty. There is no post-roll control in the screen.

## What viewers see

"Advertisement", an "Ad - Ns" countdown and a Skip Ad button (or "Skip in Ns") on the player. Ads count through WB Ad Manager's own tracking.

## Per-video overrides

Pro adds a "Video Ads" box on the video edit screen, so one video can use its own ads, no ads, or its own break pattern. See [Video Ads (Pro)](https://wpmediashield.com/docs/pro/features/11-video-ads/).

## Check it worked

Watch a video as a member. A pre-roll should play before the video, and markers should appear on the seek bar.

## Next steps

- [Customise the Player](12-customise-the-player.md)
- [Settings Overview](../configuration/01-settings-overview.md)
- [Integrations](18-integrations.md)

If no ads appear, see [Troubleshooting](../troubleshooting/01-common-problems.md).
