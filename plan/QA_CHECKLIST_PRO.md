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

### Platform Browsers
- [ ] Bunny Browser: browse videos from connected library
- [ ] Bunny Browser: search works with debounce
- [ ] Bunny Browser: collection filter dropdown works
- [ ] Bunny Browser: checkbox select + bulk import
- [ ] Bunny Browser: import creates mediashield_video CPT
- [ ] Bunny Browser: import downloads thumbnail as featured image
- [ ] Bunny Browser: "Already imported" badge on imported videos
- [ ] Bunny Browser: shows "No Bunny connection" when not connected
- [ ] YouTube Browser: browse channel videos
- [ ] YouTube Browser: playlist filter works
- [ ] YouTube Browser: bulk import with correct meta (_ms_platform=youtube)
- [ ] Vimeo Browser: browse user videos
- [ ] Vimeo Browser: folder filter works
- [ ] Vimeo Browser: bulk import with correct meta
- [ ] Wistia Browser: browse project medias
- [ ] Wistia Browser: project filter works
- [ ] Wistia Browser: bulk import with correct meta

### Multiple Platform Connections
- [ ] Can connect multiple Bunny libraries (different Library IDs)
- [ ] Connection Name/label saves and displays on card
- [ ] Each connection shows "Browse & Import" button
- [ ] Platform cards show platform-specific help text for API keys
- [ ] Bunny: Pull Zone hostname auto-appends .b-cdn.net if just prefix entered
- [ ] YouTube: Channel ID field appears when YouTube selected
- [ ] Info card shows "Videos streamed from CDN, not stored locally"

### Multiple Library Connections (Legacy)
- [ ] Connect two Bunny libraries simultaneously
- [ ] Browse each library separately
- [ ] Import from different libraries assigns correct platform_video_id
- [ ] Disconnect one library does not affect the other

### LMS Integration
- [ ] LMS Integration meta box appears on video edit when LearnDash active
- [ ] Lesson dropdown grouped by course
- [ ] Completion % dropdown saves (per-video override)
- [ ] "Require enrollment" checkbox saves
- [ ] Video completion at configured % marks LearnDash lesson complete
- [ ] Non-enrolled user blocked from watching linked video
- [ ] LMS meta box hidden when no LMS active
- [ ] Tutor LMS adapter: lesson dropdown shows Tutor lessons
- [ ] LifterLMS adapter: lesson dropdown shows Lifter lessons
- [ ] Third-party adapter can register via mediashield_lms_adapters_loaded
- [ ] mediashield_lms_lesson_completed action fires after completion

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

### DRM Admin
- [ ] DRM method dropdown saves (none/cloud_bunny/cloud_aws/local_shaka)
- [ ] AWS shows "Coming Soon" warning when selected
- [ ] Shaka Packager path field appears when local_shaka selected
- [ ] License duration (streaming) saves in hours, stored in seconds
- [ ] License duration (persistent) saves in days, stored in seconds
- [ ] Auto-package toggle saves
- [ ] DRM intro educational text displayed
- [ ] "Enable Offline Playback" help text mentions DRM requirement

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
- [ ] Email gate toggle saves
- [ ] Webhook URL saves
- [ ] Cookie duration saves
- [ ] Retention months saves
- [ ] Set video access type to `email_gate`
- [ ] Non-authenticated user sees email gate overlay
- [ ] Email gate overlay shows on gated video
- [ ] Email submission succeeds with valid email
- [ ] Email submission fires webhook
- [ ] Cookie set after successful submission
- [ ] Cookie prevents re-asking (per-video)
- [ ] Cookie persists for configured duration (default 7 days)
- [ ] Returning visitor with cookie skips email gate
- [ ] Email stored in ms_email_captures table
- [ ] Unique constraint: same email + video does not duplicate
- [ ] Webhook fires on email submission
- [ ] Webhook payload includes all expected fields
- [ ] Rate limiting: 5 attempts/min per IP
- [ ] Rate limiting: 6th submission from same IP within 1 hour rejected
- [ ] Name field is optional
- [ ] Consent checkbox required when shown
- [ ] Cookie is HttpOnly and SameSite=Lax
- [ ] Email gate overlay: role=dialog, focus trap, Escape key
- [ ] After email gate passed, video session starts and plays

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

### VPN / Proxy Detection
- [ ] Setting `ms_vpn_detection_enabled` default true when Pro active
- [ ] Setting can be toggled via Settings REST / admin UI
- [ ] Session started from public IP → Action Scheduler queues `ms_vpn_lookup`
- [ ] Session started from private IP (10.x, 192.168.x, 127.x) → lookup skipped
- [ ] Session started from localhost → lookup skipped
- [ ] Lookup calls ip-api.com with 3s timeout
- [ ] Successful lookup caches result for 24h in transient `ms_vpn_{md5(ip)}`
- [ ] Failed lookup caches negative result for 1h (rate limit protection)
- [ ] Proxy-flagged IP creates alert row with type=`vpn_detected`, severity=`info`
- [ ] Hosting/datacenter-flagged IP creates same alert
- [ ] Mobile carrier IP (mobile=true, proxy=false) does NOT create alert
- [ ] User in safe-list (`ms_safe_users`) → lookup skipped even if IP flagged
- [ ] Disable `ms_vpn_detection_enabled` → no new lookups queued
- [ ] Alert message includes IP, classification (proxy/VPN or datacenter), and ISP name
- [ ] When Action Scheduler unavailable, falls back to inline lookup (no fatal)

### DevTools Detection Alert (from free)
- [ ] Free plugin fires `mediashield_devtools_detected` action on client report
- [ ] Pro's SuspiciousActivity creates alert with type=`devtools`, severity=`warning`
- [ ] Alert appears in Alerts admin page with strategy (size_delta/debugger_timing) in message
- [ ] User in safe-list → alert NOT created
- [ ] Free plugin rate-limits (1/hr per user+IP) — Pro does not receive duplicate events

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
- [ ] Milestone Config page shows 25/50/75/100% cards
- [ ] Each card has enable toggle + action type dropdown
- [ ] Tag user action saves tag name
- [ ] Send email action saves recipient + subject
- [ ] Fire webhook action saves URL
- [ ] Global milestone config saves via milestones/config endpoint
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
- [ ] Bunny webhook validates signature when key configured
- [ ] Encrypt/decrypt handles all binary data correctly (no colon collision)
- [ ] Settings PUT only accepts defined keys (no arbitrary ms_ write)
- [ ] Session start response does NOT include source_url
- [ ] Email gate rate limit uses REMOTE_ADDR only (not X-Forwarded-For)
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
