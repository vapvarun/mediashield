# Setup Wizard

On first activation, MediaShield redirects you to a four-step setup wizard. The wizard lets you configure the essentials before adding any videos.

Every control saves as soon as you change it, so nothing is lost if you close the tab. There is no link back to the wizard from the admin menu, though: if you leave part way through and want to return, open `wp-admin/admin.php?page=mediashield-wizard` directly. Everything the wizard sets is also available under **MediaShield > Settings**, so skipping it costs you nothing.

## Step 1 - General Settings

Three site-wide switches:

**Enable video protection** - The master on/off switch for all MediaShield features. Leave this on. With it off, videos still play, but with no watermark, no protection layer, and no session tracking.

**Require login to watch** - When on, visitors must be logged in before a protected video plays; they see a login overlay instead. When off, guests can watch and their sessions are still recorded. Per-video role restrictions and any custom access rules still apply either way.

**Default protection level** - The baseline for videos that have no per-video setting. The wizard offers the two ends of the range:

- **Standard (Watermark + Tracking)** - recommended for gated content.
- **None (No protection)** - plays as a normal embed.

The full set of four levels (None, Basic, Standard, Strict) is available under **MediaShield > Settings** and on each video's edit screen. See [Protection Settings](../configuration/02-protection-settings.md) for what each level does.

## Step 2 - Connect a Platform

An information screen. It lists the platforms MediaShield works with (YouTube, Vimeo, Bunny Stream, Wistia) and points out that direct API connections for browsing and importing libraries are a Pro feature.

Nothing here is selectable and nothing is saved. You do not have to declare which platforms you use: MediaShield detects the platform from the URL when you add a video, and self-hosted files are supported as well. Skip this step.

## Step 3 - First Video (Optional)

Paste a video URL and click **Protect** to create your first library entry. MediaShield detects the platform from the URL and stores the platform's video ID alongside the original URL.

Supported URL formats:
- YouTube: `https://www.youtube.com/watch?v=...`
- Vimeo: `https://vimeo.com/...`
- Wistia: your Wistia embed URL
- Bunny Stream: the video's embed URL, or its URL from the Bunny video page
- Self-hosted: any direct `.mp4`, `.webm`, `.mov`, or `.m4v` URL

If you skip this step, you can add videos any time from **MediaShield > Videos**.

## Step 4 - Watermark Settings

Configure the identity overlay drawn on top of the video while it plays:

**Opacity** - A slider from 0.1 to 1.0, where 1.0 is fully solid. Values around 0.3 to 0.5 are visible enough to deter sharing without being distracting.

**Text Color** - The watermark text color. Use a color that contrasts with your typical video content.

**Position swap interval** - How many seconds the watermark stays in one position before moving to the next. Shorter intervals make it harder to crop out of a recording.

The preview panel shows the shape of the overlay: display name, then IP address. The free watermark shows exactly those two fields. Pro adds 7 configurable fields including email, user ID, timestamp, site name, and custom text.

## After the wizard

Click **Finish** to mark setup complete and land on the dashboard. From here you can:

- Add and manage videos from **MediaShield > Videos**
- Fine-tune all settings from **MediaShield > Settings**
- Watch analytics appear in **MediaShield > Dashboard** as viewers watch your content

Next: [Add Your First Video](04-add-your-first-video.md)
