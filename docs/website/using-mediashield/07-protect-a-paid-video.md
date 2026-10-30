# Protect a Paid Video

**Included in Free.**

This guide takes one video from "anyone can see it" to "only signed-in members can watch it, and a leaked recording shows who it came from". It covers the whole job in order.

## Why use it

Paid video stays for paying members, and a leaked copy can be traced to an account. You set it up once for the video, not page by page.

## What you get, and what you do not

MediaShield puts a protected player around videos in your library. It checks who may watch, overlays the viewer's name and IP address, stops casual saving, and records who watched how much. It does not stop a determined person recording their screen, and it is not forensic watermarking. The overlay is drawn on top of the picture, not into the file.

## Before you start

- The video must be added to MediaShield. Only videos in your library are protected, wherever they appear. An embed you paste into a post without adding it first is left alone.
- Decide who may watch: any signed-in member, or one role.

## Steps

1. Go to **MediaShield > Videos > Add New Video**.
2. Give it a title and paste the video URL, or upload a file. See [Video Hosts](10-video-hosts.md).
3. Under **Video Settings**, set **Protection Level** to **Standard** (watermark, tracking, milestones) or **Strict** (adds developer-tools detection and source hiding).
4. Optional: set **Restrict to Role** if only one role may watch. Leave it on "Any logged-in user" otherwise.
5. Click **Publish**.
6. Copy the shortcode from the **Embed This Video** box, or insert the **MediaShield Video** block in the page editor.
7. Make sure **MediaShield > Settings > Require Login** is on (it is by default).
8. Open the page in a private window while logged out. You should see the login message. Log in and the video plays with your name on it.

Your own administrator account always passes the access checks, so test as a different user or logged out.

## Choosing a level

| Level | Login | Watermark | Tracking and milestones | Detection and source hiding |
|-------|-------|-----------|-------------------------|-----------------------------|
| None | No | No | No | No |
| Basic | Yes | No | No | Right-click blocked |
| Standard | Yes | Yes | Yes | Per your Protection settings |
| Strict | Yes | Yes | Yes | Forced on |

If you want analytics, use Standard or Strict. Basic protects the player but records nothing.

## Extra layers you can add

- Turn on **Block Save Shortcut** in Settings > Protection.
- Limit shared logins with **Max Concurrent Streams**. See [Limit Streams and Stop Sharing](09-limit-streams-and-stop-sharing.md).
- Restrict where videos can be embedded with **Allowed Domains**. See [Restrict Who Can Watch](08-restrict-who-can-watch.md).
- If you host files yourself on nginx, add the server rule from **Tools > Site Health**. See [Upload a Video File](11-upload-a-video-file.md).

## What viewers see

Signed-in members see the player with their name and IP address over it. Visitors who are signed out see the login prompt, not the video.

## Check it worked

1. Watch for at least 30 seconds as a member.
2. Open **MediaShield > Dashboard** and look for the session, or **Viewers** for the member.

## Next steps

- [Watermarks](01-watermarks.md)
- [Restrict Who Can Watch](08-restrict-who-can-watch.md)
- [Troubleshooting](../troubleshooting/01-common-problems.md)
