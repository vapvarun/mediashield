# Upload a Video File

**Included in Free.** Uploading to a connected cloud platform instead needs MediaShield Pro.

Use this when you want the video file on your own server.

## Before you start

- Your user needs the `upload_mediashield` capability. Administrators have it.
- Accepted types: `.mp4`, `.webm`, `.mov` and `.m4v`. A file that is not really a video is refused.
- There is no chunked upload. Your server's upload limit is the ceiling. Typical shared hosting allows 64 to 128 MB. If your lessons are bigger, host them on a video platform instead.

## Steps

1. Go to **MediaShield > Videos > Add New Video**.
2. Under **Video Source**, find **Or upload a file** and choose your file.
3. Click **Upload**. A progress bar shows. "Uploaded. Save the video to keep this file." appears when done.
4. Add a title, set a **Featured Image** for the poster (MediaShield does not make one), and set **Duration** if you know it.
5. Click **Publish**.

## Where the file goes

The file is stored in `wp-content/uploads/mediashield/`, outside the media library. The file name carries a random token so addresses cannot be guessed. Visitors watch it through a permission-checked address, so the real file path is never printed in the page while Hide Video Source URL is on.

## Check your server blocks direct downloads

On Apache, MediaShield writes an `.htaccess` rule that blocks direct access. nginx ignores `.htaccess`, so on nginx the file can be downloaded by anyone who has its address until you add a rule.

1. Go to **Tools > Site Health**.
2. Find the check named "MediaShield video files are not publicly downloadable".
3. If it says files can be downloaded directly, copy the `location` rule it shows into your nginx server block, or send it to your host, then run the check again.

## If the upload fails

- "Upload failed: the connection dropped or the file is larger than this server accepts." Raise `upload_max_filesize` and `post_max_size`, or use a video platform.
- The Upload button stays grey until a file is chosen.
- See [Error Messages](../troubleshooting/02-error-messages.md).

## Next steps

- [Protect a Paid Video](07-protect-a-paid-video.md)
- [Video Hosts](10-video-hosts.md)
