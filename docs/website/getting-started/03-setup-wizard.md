# Setup Wizard

On first activation, MediaShield redirects you to a four-step setup wizard. The wizard lets you configure the essentials before adding any videos.

Each step auto-saves as you go. If you close the tab mid-wizard, your progress is kept. When you return, you'll land on the dashboard and can access the wizard from there until you click **Finish**.

## Step 1 - General Settings

Configure the site-wide defaults:

**Enable Protection** - The master on/off switch for all MediaShield features. Leave this on.

**Default Protection Level** - The baseline for all videos that don't have a per-video override. Options:

- **None** - No protection. Videos play as normal embeds.
- **Basic** - Right-click disabled, source URL hidden.
- **Standard** (recommended) - Everything in Basic, plus the dynamic watermark and developer-tools detection.
- **Strict** - Everything in Standard, plus keyboard shortcut blocking and a fullscreen watermark.

Standard is the right starting point for most sites. You can override this per video.

**Require Login** - Forces viewers to log in before any video plays. Turn this off only if you want specific videos to be publicly viewable without an account.

## Step 2 - Platform Selection

Tell MediaShield which video platforms you use. Options: Self-hosted, YouTube, Vimeo, Bunny Stream, Wistia.

The wizard just records your intended platforms. It does not connect to any external APIs at this step. Direct API connections to Bunny, YouTube, Vimeo, and Wistia (for browsing and importing video libraries) are available in Pro.

In the free plugin, MediaShield detects and wraps embeds from all five platforms automatically via output buffering, regardless of what you select here.

## Step 3 - Watermark Settings

Configure the identity overlay that appears on top of every video for logged-in viewers:

**Opacity** - How visible the watermark is, as a percentage. 0% is invisible. 100% is fully solid. A value in the 30-50% range is visible enough to deter sharing without being distracting.

**Color** - The watermark text color. Use a color that contrasts with your typical video content.

**Swap Interval** - How often the watermark moves to a new position, in seconds. Shorter intervals make it harder to crop out of a recording.

The free watermark shows the viewer's display name and IP address. Pro adds 7 configurable fields including email, user ID, timestamp, and custom text.

## Step 4 - First Video (Optional)

Optionally create your first video right in the wizard by pasting a URL. MediaShield detects the platform from the URL and creates a library entry automatically.

Supported URL formats:
- YouTube: `https://www.youtube.com/watch?v=...`
- Vimeo: `https://vimeo.com/...`
- Self-hosted: any direct `.mp4`, `.webm`, or `.m4v` URL
- Bunny Stream: your Bunny CDN embed URL
- Wistia: your Wistia embed URL

If you skip this step, you can add videos any time from **MediaShield > Videos**.

## After the wizard

Click **Finish** to complete setup and go to the dashboard. From here you can:

- Add and manage videos from **MediaShield > Videos**
- Fine-tune all settings from **MediaShield > Settings**
- Watch analytics appear in **MediaShield > Dashboard** as viewers watch your content

Next: [Add Your First Video](04-add-your-first-video.md)
