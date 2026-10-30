# Video Ads

**Free + Pro.** The placement settings are in Free. Per-video ad overrides are added by Pro.

MediaShield can play ad breaks inside the protected player. It does not supply ads. The ads come from video creatives you make in the WB Ad Manager plugin. Without that plugin and at least one video creative, no break plays and these settings have no effect.

## Before you start

- Install and activate WB Ad Manager and create at least one video ad creative.
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

## How many breaks you get

MediaShield plans one slot for the pre-roll and one for each mid-roll. If you have fewer creatives than slots, the remaining slots stay empty. There is no post-roll control in the screen.

## What viewers see

"Advertisement", an "Ad - Ns" countdown and a Skip Ad button (or "Skip in Ns") on the player. Ads count through WB Ad Manager's own tracking.

## Per-video overrides

Pro adds a "Video Ads" box on the video edit screen. It is described in the Pro documentation.

## Check it worked

Watch a video as a member. A pre-roll should play before the video, and markers should appear on the seek bar.

## Next steps

- [Customise the Player](12-customise-the-player.md)
- [Settings Overview](../configuration/01-settings-overview.md)
