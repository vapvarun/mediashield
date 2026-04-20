# Migrating to MediaShield

Switching to MediaShield is usually a 30-minute job. You keep your existing video hosting (YouTube, Vimeo, Bunny, Wistia, or self-hosted). MediaShield wraps the players with protection. No re-uploading required.

This guide covers migration from the common alternatives.

---

## From Presto Player (free or Pro)

**What you keep:** Your videos on YouTube, Vimeo, Bunny, or self-hosted. Your existing Presto shortcodes in draft posts.

**What changes:**

* Presto's player UI is replaced by MediaShield's wrapped player with watermark overlay.
* Presto's analytics (views, chapters) are replaced by MediaShield's session and milestone tracking.
* Presto's email gating is replaced by MediaShield Pro's email gate if you use it.

**Migration steps:**

1. Install MediaShield (free) alongside Presto. They can coexist during migration.
2. Run the MediaShield setup wizard. This configures defaults.
3. For each video:
   * Copy the source URL from Presto's video record.
   * Create a new MediaShield Video (WP Admin, MediaShield, Videos, Add).
   * Paste the URL. MediaShield auto-detects the platform.
   * Copy the `[mediashield id=X]` shortcode.
   * Replace `[presto_player id=X]` with `[mediashield id=X]` in your content.
4. Test each page. The player now shows MediaShield branding and watermark.
5. Deactivate Presto once all videos are migrated.

**Bulk migration for 50+ videos:** use WP-CLI or a custom script:

```php
// Pseudo-code. Adapt to Presto's post meta keys.
$presto_videos = get_posts( array( 'post_type' => 'pp_video_block', 'numberposts' => -1 ) );
foreach ( $presto_videos as $pv ) {
    $url = get_post_meta( $pv->ID, '_presto_source', true );
    $ms_id = wp_insert_post( array(
        'post_type'   => 'mediashield_video',
        'post_title'  => $pv->post_title,
        'post_status' => 'publish',
    ) );
    update_post_meta( $ms_id, '_ms_source_url', $url );
    // Detect platform using MediaShield's detection helper.
}
```

**What Presto does that we don't:**

* Gutenberg-native chapter markers and custom player skinning beyond our basic overrides.
* Transcript overlays (planned for MediaShield 1.1).

If those are critical, keep Presto for specific videos and run them without MediaShield protection.

---

## From VdoCipher

**What you keep:** Videos on Bunny, YouTube, Vimeo, or self-hosted if you also host outside VdoCipher. Videos only on VdoCipher's hosted CDN require a transfer.

**What changes:**

* VdoCipher's Widevine L1 hardware DRM is replaced by MediaShield Pro's ClearKey DRM (software-based). This is a downgrade in cryptographic strength. It's usually a 90 percent cost reduction ($350+ per year down to $99 per year).
* If your threat model strictly requires Widevine L1, do not migrate. Use VdoCipher or Bunny Stream MediaCage Enterprise instead.
* VdoCipher's player is replaced by MediaShield's Shaka Player wrapper.

**Migration steps:**

1. Be honest about your DRM need. Read `docs/pro/drm-types-explained.md`. If ClearKey is enough for your threat model, continue. If not, stay with VdoCipher.
2. Export your videos from VdoCipher. If your plan allows, download originals from the VdoCipher dashboard. Alternative: move the video files to Bunny Stream, which is cheaper and works with MediaShield Pro DRM.
3. Connect Bunny Stream to MediaShield Pro. Admin, Platforms, Add, Bunny Stream.
4. Upload videos to Bunny (MediaShield Pro admin, Platforms, Bunny, Browse, Upload).
5. Enable DRM. Admin, DRM, Method, Bunny Stream cloud DRM.
6. Replace embed codes in your content with `[mediashield id=X]` shortcodes.
7. Cancel VdoCipher after 30 days of clean MediaShield operation. Keep it as fallback during cutover.

**Downloading your content:** VdoCipher typically holds encrypted copies. You need to request originals from them or re-upload from your source files.

---

## From CopySafe Video or DRM-X

**What you keep:** None of CopySafe's proprietary `.cspv` files. CopySafe uses a custom codec that's incompatible with standards-based HLS and DASH.

**What changes:**

* You must re-encode videos to MP4 or HLS. Use ffmpeg or HandBrake:
  ```bash
  ffmpeg -i source.cspv -c:v libx264 -c:a aac -f mp4 output.mp4
  ```
  CopySafe's `.cspv` can't be decoded by ffmpeg directly. You need the original source files. Contact CopySafe support for "uncrypted exports" if they exist.
* You lose CopySafe's proprietary offline download protection. MediaShield Pro offers PWA offline with ClearKey as a replacement (not identical; read `docs/pro/drm-types-explained.md`).

**Migration steps:**

1. Gather the original video files (pre-CopySafe encryption).
2. Upload to a MediaShield-supported host (self-hosted, YouTube unlisted, Vimeo, or Bunny).
3. Create MediaShield videos pointing to the new URLs.
4. Replace CopySafe shortcodes with `[mediashield id=X]`.
5. Deactivate and uninstall CopySafe.

**Honest note:** CopySafe claims screen-recording prevention via proprietary codec plus browser extensions. These claims don't verify. Screen recording works against CopySafe just like everything else. Don't pay extra expecting a protection tier that doesn't exist.

---

## From "WP Protect Video" or generic free plugins

Most free WP video protection plugins do three things: wrap video in an iframe, disable right-click, and add a basic watermark (sometimes static).

MediaShield does all three, plus dynamic watermark, session tracking, access control, milestone tracking, analytics, and protection detection.

**Migration:** usually drop-in. Install MediaShield, create videos, replace shortcodes, deactivate the old plugin. No data migration needed because these plugins rarely store structured analytics.

---

## From no protection (naive YouTube, Vimeo, or self-hosted embeds)

The easiest migration. You've been embedding videos with raw iframe code or Gutenberg's YouTube / Vimeo blocks.

1. Install MediaShield (free).
2. Run the setup wizard.
3. **Option A, manual:** replace your embed blocks with MediaShield Video blocks or `[mediashield id=X]` shortcodes.
4. **Option B, automatic:** leave existing embeds alone. MediaShield's output buffer auto-detects and wraps them. Slower than explicit shortcodes but requires zero content changes.

Option B is often enough. You lose the per-video protection-level override (since there's no MediaShield video record), but all other protection applies.

---

## Switching from Teachable, Thinkific, or Kajabi

These are hosted LMS platforms, not WordPress plugins. Migration is a platform-level move, not a plugin swap.

**If you're moving to WordPress:**

1. Set up WordPress with LearnDash, Tutor LMS, or LifterLMS.
2. Install MediaShield (free or Pro).
3. Upload your course videos to YouTube unlisted, Vimeo, or Bunny (pick a host).
4. Create MediaShield videos pointing to these URLs.
5. Embed in LMS lessons via shortcode.
6. Use MediaShield Pro's LMS integration to auto-complete lessons on video completion.

**Why this works:** you get the LMS flexibility of WordPress plus industry-grade video protection without paying Teachable's $300+ per month plan for "advanced security" (which is just URL signing plus DRM in their pricier tiers).

---

## Post-migration checklist

- [ ] All videos play on frontend.
- [ ] Watermark shows viewer identity.
- [ ] Session tracking works (Dashboard shows views).
- [ ] Milestone tracking fires (tested with a full playthrough).
- [ ] LMS integration, if applicable, marks lessons complete.
- [ ] Old plugin deactivated.
- [ ] Old plugin uninstalled after 14 days (grace period in case you need to roll back).
- [ ] Documentation and help articles on your site updated with new shortcodes.
- [ ] Team trained on the new admin UI.

## Need help with migration?

We'll help you plan the cutover, especially for VdoCipher or Bunny transitions where there's real money on the line.

Email `support@wbcomdesigns.com` with:

* Current plugin plus number of videos.
* Target host (YouTube, Bunny, self-hosted, etc.).
* Timeline.

We respond within 48 hours with a migration plan. If useful, we offer a brief free consultation call (Pro customers only).
