# Watermarks

The watermark is the core forensic tool in MediaShield. It renders the viewer's identity as a text overlay on top of the video while it plays.

## What the watermark shows

In the free plugin, the watermark displays:
- The viewer's WordPress display name
- The viewer's IP address

Two details worth knowing:

- **On narrow players the IP is dropped.** When the player is under 640 px wide - phones, and small columns on desktop - the overlay shows the display name only, so the text stays readable. The IP is still recorded against the watch session either way.
- **Guests are labelled "Guest".** If you have turned Require Login off, an anonymous viewer's watermark reads "Guest" plus their IP. Only a logged-in viewer can be named.

MediaShield Pro extends the watermark to 7 configurable fields: display name, email, IP address, user ID, timestamp, site name, and custom text.

## How to configure the watermark

Go to **MediaShield > Settings > Watermark**:

**Opacity** - How visible the overlay is, on a 0 to 1 scale (not a percentage). Default 0.5. Around 0.3 to 0.5 is visible without being distracting during normal viewing.

**Color** - The watermark text color, default white. Choose a color that is readable against your typical video content. If your videos tend to be dark, white works well. If they tend to be light, a dark color is better.

**Position Swap Interval** - How many seconds the watermark stays in one position. Default is 30. Shorter intervals (10-15 seconds) make the watermark harder to crop out of a recording because it covers more of the frame over time.

## How the watermark works technically

The watermark is a canvas element rendered on top of the video player container. It:

- Cycles through five positions - the four corners and the center - at the interval you set
- Redraws when the player is resized, and stays visible in fullscreen
- Watches its own element, and reacts if the page tries to remove or hide it

The watermark is entirely client-side. No video re-encoding is required. It does not modify the source video file.

## The watermark and protection levels

The watermark renders on videos at **Standard** or **Strict** protection level only. It does not appear at None or Basic, because those levels never start a watch session, and the watermark is drawn once the session hands the player the viewer's details.

To see the watermark:
- Settings > General > Default Protection Level set to Standard or higher, OR
- The individual video's Protection Level set to Standard or higher

## Honest limits

The watermark does not prevent screen recording. Any viewer can record their screen. The watermark makes that recording traceable - not impossible to make.

The watermark can be cropped if a viewer is determined to remove it. Because it moves position every N seconds and covers different areas of the frame, a crop that removes every trace also removes a lot of the video. Most casual leakers don't bother.

If somebody removes the canvas element in browser developer tools, or hides it with CSS, MediaShield notices: it pauses any self-hosted video and hides platform iframes on the page. Playback stops rather than continuing unmarked.

The IP shown in the overlay is the address your web server reports for the request. Behind a reverse proxy or CDN that can be the proxy's own address unless your host forwards the real one. Developers can adjust which headers are trusted for the session record with the `mediashield_trusted_ip_headers` filter.

For the wider picture of what protection can and cannot do, see "What MediaShield does not promise" in the [Introduction](../getting-started/01-introduction.md).

## Revoking access when you catch a leak

If you identify a leak from watermark forensics, MediaShield can terminate every active stream for that account. There is no button for it in the admin in this release; it is an administrator REST call:

```
POST /wp-json/mediashield/v1/session/revoke-user
{ "user_id": 42 }
```

Their current streams stop, and the `mediashield_user_access_revoked` action fires so you can log the event. To stop them starting new ones, change what they are allowed to watch - remove the role the video requires, or deactivate the account - then take any further action outside MediaShield.
