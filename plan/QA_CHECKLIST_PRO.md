# MediaShield Pro -- QA Manual Checklist

## Pre-Release Testing

### Activation & Dependencies
- [ ] Pro activates successfully when free plugin is active
- [ ] Pro shows admin notice when free plugin is missing
- [ ] Pro shows admin notice when free plugin version is too old
- [ ] Activation creates all 8 Pro DB tables
- [ ] All 17 Pro default options set correctly
- [ ] EDD license activation works
- [ ] EDD license deactivation works
- [ ] License expiry shows proper admin notice
- [ ] `mediashield_pro_loaded` action fires after free plugin loads

### Platform Connections -- Bunny Stream
- [ ] Connect with API key + library ID
- [ ] Pull zone hostname saved in extra_config
- [ ] CDN token key saved in extra_config
- [ ] Connection validation calls Bunny API on connect
- [ ] Invalid credentials show error message
- [ ] Browse library lists all videos with thumbnails
- [ ] Single video import creates CPT with correct meta
- [ ] Bulk import (select multiple) creates all CPTs
- [ ] Upload via tus resumable -- small file (< 50MB)
- [ ] Upload via tus resumable -- large file (> 500MB)
- [ ] Upload progress bar updates correctly
- [ ] Upload resume after network interruption
- [ ] Streaming URL generated as HLS (`playlist.m3u8`)
- [ ] Signed URL authentication works
- [ ] Disconnect removes connection but preserves imported videos
- [ ] Video deletion calls Bunny delete API

### Platform Connections -- YouTube
- [ ] Connect with API key + channel ID
- [ ] Browse channel videos with thumbnails
- [ ] Import creates CPT with YouTube embed URL
- [ ] Upload new video to channel
- [ ] Upload progress tracking
- [ ] Disconnect preserves imported videos

### Platform Connections -- Vimeo
- [ ] Connect with access token
- [ ] Browse video library
- [ ] Import creates CPT with Vimeo embed URL
- [ ] Upload via tus resumable
- [ ] Upload progress tracking
- [ ] Disconnect preserves imported videos

### Platform Connections -- Wistia
- [ ] Connect with API token
- [ ] Browse project videos
- [ ] Import creates CPT with Wistia embed
- [ ] Upload via multipart form
- [ ] Upload progress tracking
- [ ] Disconnect preserves imported videos

### Multiple Library Connections
- [ ] Connect two Bunny libraries simultaneously
- [ ] Browse each library separately
- [ ] Import from different libraries assigns correct platform_video_id
- [ ] Disconnect one library does not affect the other

### DRM -- Bunny Stream (Cloud)
- [ ] DRM method set to `cloud_bunny` saves correctly
- [ ] DRM-enabled Bunny videos use Shaka Player
- [ ] ClearKey license request succeeds for authorized users
- [ ] ClearKey license request denied for unauthorized users
- [ ] Video plays with DRM protection (Chrome)
- [ ] Video plays with DRM protection (Firefox)
- [ ] Video plays with DRM protection (Edge)
- [ ] Safari fallback to standard protection (no DRM)
- [ ] iOS fallback to standard protection
- [ ] Watermark still visible on DRM-protected videos

### DRM -- Local Shaka Packager
- [ ] DRM method set to `local_shaka` saves correctly
- [ ] Shaka Packager path validation
- [ ] Manual package button works
- [ ] AES-128 key generated and encrypted in ms_drm_keys
- [ ] DASH segments created in output directory
- [ ] Auto-package on upload works (when enabled)
- [ ] Packaged video plays via Shaka Player
- [ ] Action Scheduler job created for packaging

### DRM -- License Management
- [ ] Streaming license issued (24h default)
- [ ] Persistent license issued (30d default)
- [ ] License duration configuration saves
- [ ] License revocation marks `revoked_at`
- [ ] Revoked license prevents key serving
- [ ] License table in admin shows active licenses
- [ ] Bulk revoke by user works

### DRM -- Offline Playback
- [ ] Service Worker registered on DRM pages
- [ ] "Save for Offline" button appears
- [ ] DASH segments cached by Service Worker
- [ ] Offline playback works after disconnect
- [ ] Persistent license validated offline

### Advanced Watermark
- [ ] All 7 fields configurable: username, email, IP, user_id, timestamp, site_name, custom_text
- [ ] Enable/disable individual fields
- [ ] Custom text field accepts user input
- [ ] Font size selector works (small/medium/large)
- [ ] Watermark renders all enabled fields
- [ ] Per-video watermark override
- [ ] Watermark config saved via settings REST API
- [ ] Badge visibility toggle works

### Email Gate
- [ ] Set video access type to `email_gate`
- [ ] Non-authenticated user sees email gate overlay
- [ ] Email submission succeeds with valid email
- [ ] Cookie set after successful submission
- [ ] Cookie persists for configured duration (default 7 days)
- [ ] Returning visitor with cookie skips email gate
- [ ] Email stored in ms_email_captures table
- [ ] Unique constraint: same email + video does not duplicate
- [ ] Webhook fires on email submission
- [ ] Webhook payload includes all expected fields
- [ ] Rate limiting: 6th submission from same IP within 1 hour rejected
- [ ] Name field is optional
- [ ] Consent checkbox required when shown
- [ ] Cookie is HttpOnly and SameSite=Lax

### Heatmap Analytics
- [ ] Playback events logged to ms_playback_events
- [ ] Hourly aggregation cron creates heatmap buckets
- [ ] Heatmap chart renders for videos with data
- [ ] Empty state for videos with no data
- [ ] Position buckets are 10-second intervals
- [ ] Retention line calculates correctly
- [ ] Device breakdown chart renders
- [ ] Playlist funnel chart renders
- [ ] Heatmap REST endpoint returns correct data

### Realtime Dashboard
- [ ] Active viewers list populates with real sessions
- [ ] 15-second auto-refresh works
- [ ] Session details show: user, video, device, browser, duration
- [ ] Sessions disappear after 5 minutes without heartbeat
- [ ] Empty state when no active viewers
- [ ] Realtime REST endpoint returns correct data

### Suspicious Activity
- [ ] Multi-IP alert fires when same user on 2+ IPs (medium sensitivity)
- [ ] Devtools alert fires on devtools detection event
- [ ] Rapid seek alert fires on excessive seeking
- [ ] Concurrent stream alert fires when limit exceeded
- [ ] Alert severity levels correct
- [ ] Alert details JSON stored correctly
- [ ] Dismiss alert marks as dismissed
- [ ] Safe user whitelists user from future alerts
- [ ] Sensitivity setting changes threshold behavior
- [ ] Dismissed alerts pruned after 90 days by cron

### Milestone Actions
- [ ] Configure milestone action: tag user
- [ ] Configure milestone action: send email
- [ ] Configure milestone action: fire webhook
- [ ] Tag action assigns tag to user meta
- [ ] Email action sends email at milestone
- [ ] Webhook action POSTs to configured URL
- [ ] Multiple actions per milestone work
- [ ] Actions fire for correct milestone percentages
- [ ] Milestone config REST endpoint validates input

### Data Export -- CSV
- [ ] Export watch_sessions as CSV
- [ ] Export milestones as CSV
- [ ] Export users as CSV
- [ ] Date range filter works
- [ ] CSV excludes session_token (security)
- [ ] 50,000 row limit enforced
- [ ] File downloads in browser
- [ ] UTF-8 encoding correct

### Data Export -- PDF
- [ ] Generate PDF report queues Action Scheduler job
- [ ] PDF contains overview stats
- [ ] PDF contains top 10 videos
- [ ] PDF contains completion rate data
- [ ] Download URL created as 24-hour transient
- [ ] Admin receives email notification with download link
- [ ] Status endpoint returns correct progress
- [ ] PDF renders correctly (A4, no overflow)

### Weekly Digest
- [ ] Digest cron job scheduled on activation
- [ ] Digest email sends on schedule
- [ ] Email contains: views, completions, avg completion, top 5, alert count
- [ ] HTML email renders correctly in Gmail, Outlook
- [ ] Enable/disable toggle works
- [ ] Custom recipient email works
- [ ] Digest does not send when disabled

### Role-Based Access
- [ ] Set per-video role restriction to `subscriber`
- [ ] Subscriber can watch the video
- [ ] Editor cannot watch the video (wrong role)
- [ ] Administrator can always watch (capability check)
- [ ] Access denied message shows correctly
- [ ] Multiple roles per video (if supported)

### Frontend Upload
- [ ] `[mediashield_upload]` shortcode renders form
- [ ] User without `upload_mediashield` capability sees nothing
- [ ] User with capability sees upload form
- [ ] Connected platforms appear as upload targets
- [ ] Drag-and-drop file selection works
- [ ] Upload progress bar shows
- [ ] Upload creates video CPT on completion
- [ ] Error handling for invalid file types

### Pro Admin Pages UX
- [ ] Platforms page loads without errors
- [ ] Alerts page loads without errors
- [ ] Heatmap page loads without errors
- [ ] Realtime page loads without errors
- [ ] DRM page loads without errors
- [ ] Export page loads without errors
- [ ] All 6 Pro routes appear in admin sidebar
- [ ] Navigation between Pro and free pages works
- [ ] No console errors on any Pro page

### GDPR -- Pro Data
- [ ] Pro data exporter registered
- [ ] Export includes: email captures, DRM licenses, playback events
- [ ] Pro data eraser registered
- [ ] Erasure deletes email captures for user
- [ ] Erasure revokes DRM licenses for user
- [ ] Erasure anonymizes playback events
- [ ] User meta `ms_completed_*` exported and erased

### Uninstall
- [ ] Pro deactivation clears Pro crons
- [ ] Pro deactivation resets badge to default
- [ ] Pro deletion drops all 8 Pro tables
- [ ] Pro deletion removes all 17 Pro options
- [ ] Pro deletion does NOT affect free plugin data
- [ ] Free plugin continues working after Pro deletion

### Compatibility with Free Plugin
- [ ] Pro features degrade gracefully when free plugin is deactivated
- [ ] Free plugin works normally without Pro
- [ ] Updating free plugin does not break Pro
- [ ] Updating Pro does not break free plugin
- [ ] Both plugins active: no duplicate hooks or double execution

### Security
- [ ] Platform credentials encrypted with AES-256-CBC
- [ ] Shaka Packager arguments escaped with escapeshellarg()
- [ ] DRM license endpoint validates user access
- [ ] Email gate endpoint validates input (sanitize_email, absint)
- [ ] Export endpoint requires manage_options
- [ ] No direct file access to Pro PHP files
- [ ] No SQL injection vectors in Pro queries

### Performance
- [ ] Heatmap aggregation cron completes in under 30 seconds
- [ ] Alert pruning cron completes in under 10 seconds
- [ ] Realtime viewer query uses proper indexes
- [ ] CSV export streams without memory exhaustion
- [ ] PDF generation does not timeout (async via Action Scheduler)
- [ ] No N+1 queries in Pro admin pages
- [ ] Platform API calls use proper timeouts
