# Migrating to MediaShield — From Presto Player, VdoCipher, CopySafe, WP Protect Video

Switching to MediaShield is usually a 30-minute job. You **keep your existing video hosting** (YouTube, Vimeo, Bunny, Wistia, or self-hosted) — MediaShield wraps the players with protection. No re-uploading required.

This guide covers migration from the common alternatives.

---

## From Presto Player / Presto Player Pro

**What you keep:** Your videos on YouTube/Vimeo/Bunny/self-hosted, your existing Presto shortcodes in draft posts.

**What changes:**
- Presto's player UI is replaced by MediaShield's wrapped player with watermark overlay.
- Presto's analytics (views, chapters) are replaced by MediaShield's session/milestone tracking.
- Presto's gating (email capture) is replaced by MediaShield Pro's email gate if you use it.

**Migration steps:**

1. **Install MediaShield (free)** alongside Presto — they can coexist during migration.
2. **Run MediaShield setup wizard.** Configures defaults.
3. **For each video:**
   - Copy the source URL (YouTube/Vimeo/Bunny/file) from Presto's video record.
   - Create a new MediaShield Video (WP Admin → MediaShield → Videos → Add).
   - Paste the URL — MediaShield auto-detects the platform.
   - Copy the `[mediashield id=X]` shortcode.
   - Replace `[presto_player id=X]` with `[mediashield id=X]` in your content.
4. **Test each page** — the player now shows MediaShield branding and watermark.
5. **Deactivate Presto** once all videos are migrated.

**Bulk migration:** For 50+ videos, use WP-CLI or a custom script:
```php
// Pseudo-code — adapt to Presto's post meta keys
$presto_videos = get_posts( array( 'post_type' => 'pp_video_block', 'numberposts' => -1 ) );
foreach ( $presto_videos as $pv ) {
    $url = get_post_meta( $pv->ID, '_presto_source', true );
    // Create matching MediaShield video
    $ms_id = wp_insert_post( array(
        'post_type'   => 'mediashield_video',
        'post_title'  => $pv->post_title,
        'post_status' => 'publish',
    ) );
    update_post_meta( $ms_id, '_ms_source_url', $url );
    // Detect platform (youtube/vimeo/etc) — use MediaShield's detection helper
}
```

**What Presto does that we don't:**
- Gutenberg-native chapter markers and custom player skinning beyond our basic overrides.
- Transcript overlays (planned for MediaShield 1.1).

If those are critical, keep Presto for specific videos and run them without MediaShield protection.

---

## From VdoCipher

**What you keep:** Your videos on Bunny / YouTube / Vimeo / self-hosted if you also host outside VdoCipher. Videos that are exclusively on VdoCipher's hosted CDN require a transfer.

**What changes:**
- VdoCipher's **Widevine L1 hardware DRM** is replaced by MediaShield Pro's **ClearKey DRM** (software-based). This is a downgrade in cryptographic strength but typically a 90% cost reduction ($350+/yr → $99/yr).
- If your threat model strictly requires Widevine L1, **do not migrate** — use VdoCipher or Bunny Stream MediaCage Enterprise instead.
- VdoCipher's player is replaced by MediaShield's Shaka Player wrapper.

**Migration steps:**

1. **Be honest about your DRM need.** Read `docs/pro/drm-types-explained.md`. If ClearKey is sufficient for your threat model, continue. If not, stay with VdoCipher.
2. **Export your videos from VdoCipher:**
   - If your plan allows, download originals from VdoCipher dashboard.
   - Alternative: move video files to Bunny Stream (cheaper, works with MediaShield Pro DRM).
3. **Connect Bunny Stream to MediaShield Pro** (Admin → Platforms → Add → Bunny Stream).
4. **Upload videos to Bunny** (MediaShield Pro admin → Platforms → Bunny → Browse → Upload).
5. **Enable DRM** (Admin → DRM → Method → Bunny Stream cloud DRM).
6. **Replace embed codes** in your content with `[mediashield id=X]` shortcodes.
7. **Cancel VdoCipher** after 30 days of clean MediaShield operation — keep it as fallback during cutover.

**Downloading your content:** VdoCipher typically holds encrypted copies; you need to request originals from them or re-upload from your source files.

---

## From CopySafe Video / DRM-X

**What you keep:** None of CopySafe's proprietary `.cspv` files. CopySafe uses a custom codec — incompatible with standards-based HLS/DASH.

**What changes:**
- **You must re-encode videos to MP4 / HLS.** Use ffmpeg or a service like HandBrake:
  ```bash
  ffmpeg -i source.cspv -c:v libx264 -c:a aac -f mp4 output.mp4
  ```
  (Note: CopySafe's `.cspv` cannot be decoded by ffmpeg directly. You need the original source files — contact CopySafe support for "uncrypted exports" if they exist.)
- **You lose the CopySafe "offline download" proprietary protection.** MediaShield Pro offers PWA offline with ClearKey as a replacement (not identical; read `docs/pro/drm-types-explained.md`).

**Migration steps:**

1. Gather original video files (pre-CopySafe encryption).
2. Upload to MediaShield-supported host (self-hosted, YouTube unlisted, Vimeo, or Bunny).
3. Create MediaShield videos pointing to the new URLs.
4. Replace CopySafe shortcodes with `[mediashield id=X]`.
5. Deactivate and uninstall CopySafe.

**Honest note:** CopySafe claims screen-recording prevention via proprietary codec + browser extensions. These claims don't verify — screen recording works against CopySafe just like everything else. Don't pay extra expecting a protection tier that doesn't exist.

---

## From "WP Protect Video" / generic free plugins

Most free WP video protection plugins do three things:
1. Wrap video in an iframe
2. Disable right-click
3. Add a basic watermark (sometimes static)

MediaShield does all three plus dynamic watermark, session tracking, access control, milestone tracking, analytics, and protection detection.

**Migration:** usually drop-in. Install MediaShield, create videos, replace shortcodes, deactivate the old plugin. No data migration needed because these plugins rarely store structured analytics.

---

## From no protection (naïve YouTube / Vimeo / self-hosted embeds)

The easiest migration. You've been embedding videos with raw iframe code or Gutenberg's YouTube/Vimeo blocks.

1. Install MediaShield (free).
2. Run the setup wizard.
3. **Option A (manual):** Replace your embed blocks with MediaShield Video blocks or `[mediashield id=X]` shortcodes.
4. **Option B (automatic):** Leave your existing embeds alone. MediaShield's output buffer auto-detects and wraps them. Slower than explicit shortcodes but requires zero content changes.

Option B is often enough. You lose the per-video protection-level override (since there's no MediaShield video record), but all other protection applies.

---

## Switching from Teachable / Thinkific / Kajabi

These are hosted LMS platforms, not WordPress plugins. Migration is a platform-level move — not a plugin swap.

**If you're moving to WordPress:**
1. Set up WordPress with LearnDash / Tutor LMS / LifterLMS.
2. Install MediaShield (free or Pro).
3. Upload your course videos to YouTube unlisted, Vimeo, or Bunny (pick a host).
4. Create MediaShield videos pointing to these URLs.
5. Embed in LMS lessons via shortcode.
6. Use MediaShield Pro's LMS integration to auto-complete lessons on video completion.

**Why this works:** You get the LMS flexibility of WordPress + industry-grade video protection without paying Teachable's $300+/mo plan for "advanced security" (which is just URL signing + DRM in their pricier tiers).

---

## Post-migration checklist

- [ ] All videos play on frontend
- [ ] Watermark shows viewer identity
- [ ] Session tracking works (Dashboard shows views)
- [ ] Milestone tracking fires (tested with a full playthrough)
- [ ] LMS integration (if applicable) marks lessons complete
- [ ] Old plugin deactivated
- [ ] Old plugin uninstalled after 14 days (grace period in case you need to roll back)
- [ ] Documentation / help articles on your site updated with new shortcodes
- [ ] Team trained on the new admin UI

## Need help with migration?

We'll help you plan the cutover — especially for VdoCipher / Bunny transitions where there's real money on the line.

Email `support@wbcomdesigns.com` with:
- Current plugin + number of videos
- Target host (YouTube / Bunny / self-hosted / etc.)
- Timeline

We'll respond within 48 hours with a migration plan and, if useful, offer a brief free consultation call (Pro customers only).
