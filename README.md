# MediaShield -- Video Protection for WordPress

Protect your video content with dynamic watermarking, session tracking, DRM encryption, and multi-platform support. Host videos on Bunny Stream, YouTube, Vimeo, or Wistia -- MediaShield wraps the player with protection while videos stream from their CDN. No video files stored on your server.

## Free vs Pro Comparison

| Feature | Free | Pro |
|---------|:----:|:---:|
| **Video Protection** | | |
| Dynamic watermark (username + IP) | Yes | Yes |
| Configurable watermark (7 fields: email, timestamp, user ID, custom text...) | -- | Yes |
| Right-click blocking | Yes | Yes |
| DevTools detection | Yes | Yes |
| Source URL hiding | Yes | Yes |
| Concurrent stream limiting | Yes | Yes |
| Domain whitelisting | Yes | Yes |
| | | |
| **Player** | | |
| YouTube, Vimeo, Wistia, Bunny, self-hosted | Yes | Yes |
| Speed control (self-hosted/Bunny) | Yes | Yes |
| Keyboard shortcuts (Space, arrows, M, F) | Yes | Yes |
| Sticky/floating player on scroll | Yes | Yes |
| End screen CTA (per-video customizable) | Yes | Yes |
| Per-video player overrides | Yes | Yes |
| Gutenberg blocks (Video, Playlist, My Videos) | Yes | Yes |
| `[mediashield]` shortcode | Yes | Yes |
| | | |
| **Platform Integration** | | |
| Embed from URL (paste & auto-detect) | Yes | Yes |
| Browse & bulk import from Bunny library | -- | Yes |
| Browse & bulk import from YouTube channel | -- | Yes |
| Browse & bulk import from Vimeo account | -- | Yes |
| Browse & bulk import from Wistia projects | -- | Yes |
| Multiple library connections per platform | -- | Yes |
| Upload videos directly to platforms | -- | Yes |
| Bunny webhook (encoding status updates) | -- | Yes |
| | | |
| **Analytics** | | |
| Dashboard (views, sessions, completion) | Yes | Yes |
| Per-video stats | Yes | Yes |
| Per-user engagement | Yes | Yes |
| Playback heatmaps (10s position buckets) | -- | Yes |
| Realtime viewer dashboard (15s refresh) | -- | Yes |
| Playlist funnel (drop-off analysis) | -- | Yes |
| Device/browser breakdown | -- | Yes |
| CSV export | -- | Yes |
| Async PDF reports | -- | Yes |
| Weekly digest email | -- | Yes |
| | | |
| **Access Control** | | |
| Require login | Yes | Yes |
| Role-based restriction (per-video) | Yes | Yes |
| Email gate (capture email before access) | -- | Yes |
| Email gate webhook (Zapier, CRM integration) | -- | Yes |
| LMS enrollment check (LearnDash, Tutor, Lifter) | -- | Yes |
| | | |
| **LMS Integration** | | |
| Embed in any LMS lesson (shortcode/block) | Yes | Yes |
| Per-video milestone tags (10/25/50/75/100%) | Yes | Yes |
| Link video to LMS lesson | -- | Yes |
| Auto-mark lesson complete on video completion | -- | Yes |
| Configurable completion threshold (50-100%) | -- | Yes |
| Enrollment-based video access | -- | Yes |
| LearnDash adapter (verified v5.0.4) | -- | Yes |
| Tutor LMS adapter | -- | Yes |
| LifterLMS adapter | -- | Yes |
| Third-party LMS adapter API | -- | Yes |
| | | |
| **Security** | | |
| HMAC session tokens (no DB lookup) | Yes | Yes |
| Suspicious activity detection (multi-IP, VPN) | -- | Yes |
| Suspicious activity alerts + dashboard | -- | Yes |
| User whitelisting (mark as safe) | -- | Yes |
| Widevine ClearKey DRM encryption | -- | Yes |
| Bunny Stream DRM | -- | Yes |
| Local Shaka Packager DRM | -- | Yes |
| PWA offline playback (service worker) | -- | Yes |
| Signed/token-authenticated CDN URLs | -- | Yes |
| | | |
| **Milestone Actions** | | |
| Track completion (25/50/75/100%) | Yes | Yes |
| Per-video tag assignments to users | Yes | Yes |
| Fire webhook on milestone | -- | Yes |
| Send email on milestone | -- | Yes |
| Tag user on milestone (CRM-ready) | -- | Yes |
| | | |
| **Admin** | | |
| React admin SPA (7 pages) | Yes | -- |
| Pro admin pages (+8 pages) | -- | Yes |
| Video preview lightbox | Yes | Yes |
| Shortcode/block/PHP embed copy box | Yes | Yes |
| Auto-save settings with debounce | Yes | Yes |
| Setup wizard (4 steps) | Yes | Yes |
| Pro upsell indicators (locked nav items) | Yes | -- |
| | | |
| **Developer** | | |
| 8+ actions, 8+ filters | Yes | Yes |
| Template override from theme | Yes | Yes |
| Conditional asset loading | Yes | Yes |
| Error boundary (no white-screen crashes) | Yes | Yes |
| REST API (23 free + 22 pro endpoints) | Yes | Yes |
| Third-party LMS adapter registration | -- | Yes |
| `mediashield_lms_adapters_loaded` hook | -- | Yes |
| | | |
| **Compliance** | | |
| GDPR privacy exporter | Yes | Yes |
| GDPR privacy eraser | Yes | Yes |
| Pro table export/erase (email captures, DRM) | -- | Yes |
| Clean uninstall (tables, options, caps) | Yes | Yes |
| Frontend upload (`[mediashield_upload]`) | -- | Yes |

## Requirements

- WordPress 6.5+
- PHP 8.1+

## Installation

1. Upload `mediashield` to `/wp-content/plugins/`
2. Activate via Plugins menu
3. Go to MediaShield > Dashboard
4. Connect a video platform (Pro) or add videos manually

## Documentation

- [Installation & Setup](docs/free/installation.md)
- [Configuration](docs/free/configuration.md)
- [Shortcodes & Blocks](docs/free/shortcodes-blocks.md)
- [Hooks & Filters](docs/free/hooks-filters.md)
- [FAQ](docs/free/faq.md)
- [Pro: Getting Started](docs/pro/getting-started.md)
- [Pro: Platform Connections](docs/pro/platform-connections.md)
- [Pro: DRM Setup](docs/pro/drm-setup.md)
- [Pro: Analytics](docs/pro/analytics.md)
- [Pro: Email Gate](docs/pro/email-gate.md)
- [Pro: Hooks & Filters](docs/pro/hooks-filters-pro.md)

## For Developers

### Hooks & Filters

```php
// Control video access
add_filter( 'mediashield_can_watch', function( $allowed, $video_id, $user_id ) {
    // Custom access logic
    return $allowed;
}, 10, 3 );

// Customize player HTML
add_filter( 'mediashield_player_html', function( $html, $video_id, $atts ) {
    return $html;
}, 10, 3 );

// Disable output buffer on specific pages
add_filter( 'mediashield_enable_output_buffer', function( $enabled ) {
    if ( is_checkout() ) {
        return false;
    }
    return $enabled;
});

// Per-video milestone tag assigned
add_action( 'mediashield_milestone_reached', function( $user_id, $video_id, $pct, $session_id ) {
    // Integrate with LMS, CRM, etc.
}, 10, 4 );
```

See [Full Hooks Reference](docs/free/hooks-filters.md) for all available hooks.

## License

GPL v2 or later
