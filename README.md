# MediaShield -- Video Protection for WordPress

Protect your video content with dynamic watermarking, session tracking, DRM encryption, and multi-platform support.

## Features (Free)

- **Video Protection** -- Watermark, right-click block, devtools detection, source hiding
- **Session Tracking** -- HMAC token-based watch sessions with concurrent stream limiting
- **Milestone Tracking** -- 25/50/75/100% completion with per-video tag assignments
- **Analytics Dashboard** -- Views, sessions, completion rates, user engagement
- **Gutenberg Blocks** -- Video, Playlist, My Videos blocks + `[mediashield]` shortcode
- **Multi-Platform** -- Embed YouTube, Vimeo, Wistia, Bunny Stream with protection overlay
- **Player Controls** -- Speed, keyboard shortcuts, sticky player, end screen CTA (admin-toggleable)
- **Access Control** -- Login required, role-based restriction, domain whitelisting
- **GDPR Compliant** -- Privacy exporter + eraser for all user data

## Features (Pro)

- **Platform Browsers** -- Browse & bulk import from Bunny, YouTube, Vimeo, Wistia
- **DRM Encryption** -- Widevine ClearKey via Bunny Stream or Shaka Packager
- **Advanced Watermark** -- 7 configurable fields (username, email, IP, timestamp, etc.)
- **Heatmap Analytics** -- Per-video playback heatmaps with position buckets
- **Realtime Dashboard** -- Live viewer count with 15-second auto-refresh
- **Suspicious Activity** -- Multi-IP, devtools, rapid seek, VPN detection with alerts
- **Email Gate** -- Capture emails before video access with webhook integration
- **Milestone Actions** -- Tag user, send email, fire webhook at completion milestones
- **Weekly Digest** -- Automated analytics summary email
- **CSV/PDF Export** -- Export watch data as CSV or async PDF reports
- **Role-Based Access** -- Restrict videos to specific WordPress roles
- **Frontend Upload** -- `[mediashield_upload]` shortcode for user video submissions

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
