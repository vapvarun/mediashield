# Protect Videos You Already Host

**Free + Pro.** Pasting a video address is in Free. Browsing and bulk-importing a whole library is Pro.

You already have videos on YouTube, Vimeo, Wistia or Bunny Stream. Turn each one into a protected MediaShield video without moving the file.

## Why use it

You keep the host you pay for. MediaShield adds the login gate, role rules, watermark and watch data around the host's player.

## Before you start

- Open the video's own page on the host and copy its address from the browser. A dashboard or library address will not work.
- Check the video's privacy setting on the host. A private video that the host will not embed cannot play on your site.
- Pick the protection level. Standard adds the watermark and watch sessions. See [Protection Settings](../configuration/02-protection-settings.md).

## Steps

1. Go to **MediaShield > Videos > Add New Video**.
2. Type a **Title**. It appears in your library, playlists and the My Videos block.
3. Paste the address into **Video URL**.
4. Read the line under the field. It names the platform it detected and the video ID. If it says Self-hosted for a YouTube or Vimeo address, the address is wrong. Copy it again from the video's page.
5. Choose the **Protection Level**. Leave **Restrict to Role** on Any logged-in user unless only one role may watch.
6. Click **Publish**.
7. Add the video to a page with the **MediaShield Video** block, or with `[mediashield id=42]`. Replace 42 with the video's ID.

If the platform has a poster image and you did not set a Featured Image, MediaShield fetches it for you.

## Which address to paste

| Host | Paste | Notes |
|------|-------|-------|
| YouTube | A watch or share link | Plays in YouTube's player |
| Vimeo | `https://vimeo.com/...` | Plays in Vimeo's player |
| Wistia | Your Wistia embed address | Plays in Wistia's player |
| Bunny Stream | The embed address, `iframe.mediadelivery.net/embed/...` | A collection address or a dashboard address is refused with a message |

## What protection covers on someone else's player

MediaShield wraps the host's player. The login gate, role rule, watermark, session tracking and domain rule apply. The host's own player keeps its controls, quality choices and captions.

The host's player carries its own video ID, so a viewer who has access can still find it. Source hiding works for self-hosted files only. If a video must never be reachable outside your site, set it to private or domain-restricted on the host as well. MediaShield controls who can start playback. Encryption and DRM, if you need them, come from your video host.

## What viewers see

Visitors without access see your login prompt or Access Denied text in place of the player. Members see the host's player with the watermark on top.

## Check it worked

Open the page in a private window. You should see the login prompt. Log in and the video plays with the "Protected by MediaShield" badge.

## Import many videos at once

With MediaShield Pro, connect the account under **MediaShield > Platforms** and import from the library browser. See [Connect a Video Platform](https://wpmediashield.com/docs/pro/features/01-platform-connections/).

## Next steps

- [Video Hosts](10-video-hosts.md)
- [Restrict Who Can Watch](08-restrict-who-can-watch.md)
- [Troubleshooting](../troubleshooting/01-common-problems.md)
