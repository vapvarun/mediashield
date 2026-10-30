# Error Messages

**Free + Pro.** Messages shown by the Free plugin. Find the words you see, then follow the fix.

## What viewers see

| Message | Meaning | Fix |
|---------|---------|-----|
| Please log in to watch this video | Require Login is on, or the video needs a role, and the visitor is logged out. | Log in, or turn Require Login off. Edit the text under Settings > Login and Access Messages. |
| You do not have access to this video | Logged in but missing the role, or the page's domain is not in Allowed Domains, or the request had no Referer. | Give the user the role, add the domain, or clear Allowed Domains. |
| Too many active streams. Please close another video first. | The account is at Max Concurrent Streams. | Close another video, wait 5 minutes, or raise the limit. |
| This video could not be loaded. If you are the site owner, check the video URL in the admin. | The source address is not a playable video. Often a Bunny dashboard address or a page of HTML. | Open the video, check Detected Platform, paste the right address. See [Video Hosts](../using-mediashield/10-video-hosts.md). |
| This video could not be loaded. Please try again. | A playlist item failed to load. | Reload. If it repeats, check the item is published. |
| Developer Tools Detected | Pause Video When Detected is on and developer tools were opened. | Close developer tools. Edit the text under Settings > Protection. |

## What editors see on the page

Only users who can edit posts see these. Visitors see a blank space and no player files load.

- The video ID is missing, wrong, or points to something that is not a MediaShield video.
- The video is not published.
- The video has no source address yet.
- The playlist is empty or not published.

## Messages in the admin

| Message | Meaning | Fix |
|---------|---------|-----|
| Upload failed: the connection dropped or the file is larger than this server accepts. | The server refused the file. | Raise PHP `upload_max_filesize` and `post_max_size`, or use a video platform. |
| Upload failed: ... | The file was not an accepted video. | Use `.mp4`, `.webm`, `.mov` or `.m4v`. |
| That is a Bunny collection (a folder of videos), not a single video. | The address is a collection. | Open one video in Bunny and paste its address or embed address. |
| That looks like a Bunny dashboard link we cannot read a video ID from. | A dashboard address was pasted. | Copy the video's own address or embed address. |
| This URL was not recognised as YouTube, Vimeo, Wistia or Bunny, and does not look like a direct video file. | Saved as self-hosted. | Fix the address, or confirm it serves the video file itself. |
| MediaShield video files can be downloaded directly, bypassing access checks | Site Health found your server serves files without a check. | Add the nginx rule it shows. See [Upload a Video File](../using-mediashield/11-upload-a-video-file.md). |
| MediaShield could not check whether your video files are downloadable | The check could not request a file. | Upload a video, then run Site Health again. |

## Messages from the interface (REST)

These appear to developers and in the browser's network panel.

| Code | Message | Meaning |
|------|---------|---------|
| `invalid_video_id` | Invalid video ID. | The ID is not a MediaShield video. |
| `not_found` | Video not found. / Playlist not found. / Tag not found. | The item does not exist or was deleted. |
| `unpublished` | Video is not available. | The video is not published. |
| `invalid_token` | Invalid session token. | The watch session expired or was altered. Reload the page. |
| `not_self_hosted` | This endpoint only serves self-hosted videos. | The stream address was used for a platform video. |
| `file_not_found` | Video file not found on disk. | The uploaded file was removed from `wp-content/uploads/mediashield/`. Upload it again. |
| `invalid_driver` | Upload driver not found. | A chosen upload target is not available. Pick Self-hosted or a connected platform. |
| `rate_limited` | (HTTP 429) | A player is reporting more than four times a minute. It clears by itself. |

## Next steps

- [Common Problems](01-common-problems.md)
- [FAQ](../faq/01-faq.md)
