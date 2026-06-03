# Watermarks

The watermark is the core forensic tool in MediaShield. It renders the viewer's identity as a text overlay on top of every frame of the video while it plays.

## What the watermark shows

In the free plugin, the watermark displays:
- The viewer's WordPress display name
- The viewer's IP address

These two fields together make any recording traceable. If a viewer records the video and shares it, you can look at any frame and identify who made the recording.

MediaShield Pro extends the watermark to 7 configurable fields: display name, email, IP address, user ID, timestamp, site name, and custom text.

## How to configure the watermark

Go to **MediaShield > Settings > Watermark**:

**Opacity** - Controls how visible the overlay is. 0% is invisible, 100% is fully solid. A value around 30-50% is visible without being distracting during normal viewing.

**Color** - The watermark text color. Choose a color that is readable against your typical video content. If your videos tend to be dark, white works well. If they tend to be light, a dark color is better.

**Swap Interval** - How often the watermark moves to a new position on screen. Default is 30 seconds. Shorter intervals (10-15 seconds) make the watermark harder to crop out of a recording because it covers multiple areas of the frame over time.

## How the watermark works technically

The watermark is a canvas element rendered on top of the video player container. It:

- Positions itself at configurable intervals so it doesn't stay in one corner
- Stays visible in fullscreen mode
- Re-renders automatically if the DOM is modified (anti-tamper)
- Pauses the video if the canvas element is removed from the DOM

The watermark is entirely client-side. No video re-encoding is required. It does not modify the source video file.

## The watermark and protection levels

The watermark only renders on videos at **Standard** or **Strict** protection level. It does not appear at None or Basic.

To see the watermark:
- Settings > General > Default Protection Level set to Standard or higher, OR
- The individual video's Protection Level set to Standard or higher

## Honest limits

The watermark does not prevent screen recording. Any viewer can record their screen. The watermark makes that recording traceable - not impossible to make.

The watermark can be cropped if a viewer is determined to remove it. However, because the watermark moves position every N seconds and covers different areas of the frame, a full crop that removes all traces would also crop the video content significantly. Most casual leakers don't bother.

If someone removes the canvas element via browser developer tools, the video pauses automatically (anti-tamper behavior). The session continues recording that the video was paused.

For the full reasoning on why forensic deterrence is the right model at this tier, see the Protection Philosophy section.

## Revoking access when you catch a leak

If you identify a leak from watermark forensics:

1. Go to **MediaShield > Students**
2. Find the user
3. Click **Revoke All Sessions** to immediately terminate all their active streams

You can then take further action (deactivate their account, pursue a refund reversal, etc.) outside of MediaShield.
