# Video Hosts

**Free + Pro.** Free plays videos from every host listed here. Connecting a host account to browse, import and upload is Pro.

MediaShield does not host your videos by default. You keep the host you already use, and MediaShield wraps the player in its protection layer. You can also store files on your own server.

## Before you start

- Open the video's own page on the host, not your account dashboard, to copy its address.
- Decide how much protection you need. The host decides what happens inside its own player.

## Add a video from a host

1. Go to **MediaShield > Videos > Add New Video**.
2. Paste the address into **Video URL**.
3. Look at **Detected Platform** under the field. It must name your host.
4. Add a title and click **Publish**.

## What each host gives you

| Host | Address to paste | Speed selector and volume keys | Source hiding | Notes |
|------|------------------|-------------------------------|---------------|-------|
| YouTube | A watch or share link | Host player's own | No | Plays in the YouTube iframe. The address carries the video ID. |
| Vimeo | `https://vimeo.com/...` | Host player's own | No | Same as YouTube. |
| Wistia | Your Wistia embed address | Host player's own | No | Same as YouTube. |
| Bunny Stream | The video's embed address, or its address from the Bunny video page | Yes | No | See below. |
| Self-hosted | A direct `.mp4`, `.webm`, `.mov` or `.m4v` address, or an uploaded file | Yes | Yes | Served through a permission-checked address. |
| HLS stream | A direct `.m3u8` address | Yes | n/a | Plays in every browser. DASH is not supported. |

"Source hiding" is the Hide Video Source URL setting. It works for self-hosted video only, because embeds from other hosts must carry their own video ID to play.

## Bunny Stream

- Paste the video's embed address (`iframe.mediadelivery.net/embed/...`) for the most reliable result.
- A Bunny **collection** address is a folder of videos, not a video. MediaShield tells you and does not save it.
- A Bunny dashboard address cannot be read. Open the video in Bunny and copy the video's own address, or its embed address.
- A Bunny stream file from a library your site is not connected to is saved as a direct stream. That plays only when token authentication is off for that library.
- To repair videos saved earlier from a wrong Bunny address, run `wp mediashield repair bunny-urls --dry-run` first, then `--execute`.

## What the host controls

- **Encryption.** Encryption is done by the video host or source, such as Bunny Stream. MediaShield does not encrypt your videos. With MediaShield Pro, a Bunny stream the host has encrypted can be played through the encrypted player. That feature is experimental.
- **Quality and bitrate.** MediaShield does not convert video. If the host offers several qualities, viewers get what the host's player offers.
- **Captions.** MediaShield cannot show captions from a file you supply. Use a host that provides captions in its own player.

## With MediaShield Pro

Pro connects your Bunny, YouTube, Vimeo and Wistia accounts so you can browse their libraries and bulk-import videos, and send uploads to a connected platform. See the Pro documentation.

## Check it worked

Open the page that holds the video. It should play with the "Protected by MediaShield" badge if the badge setting is on.

## Next steps

- [Upload a Video File](11-upload-a-video-file.md)
- [Troubleshooting](../troubleshooting/01-common-problems.md)
