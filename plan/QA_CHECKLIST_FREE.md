# MediaShield Free -- QA Manual Checklist

## Pre-Release Testing

### Activation & Setup
- [ ] Fresh install on WP 6.5+ with PHP 8.1
- [ ] Activation creates all 6 DB tables
- [ ] All default options set correctly
- [ ] Setup wizard redirects on first activation
- [ ] Wizard saves settings correctly (step 1-4)
- [ ] Admin SPA loads without JS errors
- [ ] Deactivation clears crons without data loss
- [ ] Re-activation recovers existing data

### Video Management
- [ ] Add New Video page -- no editor, proper meta boxes
- [ ] URL auto-detection: paste YouTube URL -- detects platform + ID
- [ ] URL auto-detection: paste Vimeo URL -- detects platform + ID
- [ ] URL auto-detection: paste Bunny embed URL -- detects platform + ID
- [ ] URL auto-detection: paste Wistia URL -- detects platform + ID
- [ ] Shortcode copy box works (copy to clipboard)
- [ ] PHP template code copy works
- [ ] Protection level dropdown saves
- [ ] Role restriction dropdown saves
- [ ] Milestone tags save (per-video, per-percentage)
- [ ] Player options save (autoplay, loop, muted, controls)
- [ ] Feature overrides save (speed, keyboard, sticky, endscreen)
- [ ] Video deletion cascade-deletes sessions, milestones, tags, playlist items

### Video Playback
- [ ] `[mediashield id=X]` shortcode renders player
- [ ] MediaShield Video block renders in Gutenberg
- [ ] YouTube video plays with watermark overlay
- [ ] Vimeo video plays with watermark overlay
- [ ] Self-hosted video plays with watermark overlay
- [ ] Bunny Stream video plays with watermark overlay
- [ ] Wistia video plays with watermark overlay
- [ ] Watermark shows username + IP
- [ ] Watermark position swaps at configured interval
- [ ] Right-click disabled on player
- [ ] Fullscreen keeps watermark visible
- [ ] Login overlay shows for non-logged-in users (when require_login enabled)
- [ ] Output buffer detection wraps embedded videos on non-shortcode pages

### Player Controls
- [ ] Speed control visible on self-hosted (not on YouTube/Vimeo/Wistia)
- [ ] Speed selector changes playback rate
- [ ] Keyboard shortcuts work when player focused (Space, arrows, M, F)
- [ ] Sticky player appears when scrolling past playing video
- [ ] Sticky player close button works
- [ ] End screen shows after video finishes (when enabled)
- [ ] End screen CTA link works
- [ ] End screen replay button works
- [ ] Per-video overrides take precedence over global settings

### Playlist Functionality
- [ ] Create playlist with title and description
- [ ] Add videos to playlist via REST API
- [ ] Reorder playlist items (drag-and-drop)
- [ ] Remove videos from playlist
- [ ] `[mediashield_playlist]` block renders correctly
- [ ] Autoplay advances to next video
- [ ] Countdown timer between videos
- [ ] Shuffle mode randomizes order
- [ ] Loop mode restarts playlist

### Session Tracking
- [ ] Session starts on video play (REST call succeeds)
- [ ] Heartbeat fires every 30 seconds
- [ ] Session ends on page unload (sendBeacon)
- [ ] Tab switch pauses heartbeat (does NOT end session)
- [ ] Concurrent stream limit enforced
- [ ] Session token uses HMAC validation
- [ ] Expired sessions (no heartbeat for 5min) marked inactive
- [ ] Milestone fires at 25/50/75/100%
- [ ] Per-video tags assigned to user meta at milestones
- [ ] `mediashield_milestone_reached` action fires with correct params

### Admin Dashboard
- [ ] Stats show real data (no fake numbers)
- [ ] Empty state shown when no data
- [ ] Period selector works (7d, 30d, 90d)
- [ ] Top videos list populates
- [ ] Chart renders with real session data
- [ ] User drill-down works (click user to see their sessions)

### Admin Pages
- [ ] Videos -- list, edit, preview lightbox
- [ ] Playlists -- CRUD works
- [ ] Viewers -- shows display_name (not "Unknown")
- [ ] Tags -- create, assign, delete
- [ ] Milestones -- list with user/video data
- [ ] Settings -- all sections save correctly
- [ ] Settings -- auto-save with debounce
- [ ] No admin notices on MediaShield pages

### Access Control
- [ ] Non-logged-in user sees login overlay (when enabled)
- [ ] Role restriction blocks unauthorized users
- [ ] Access denied message shows correctly
- [ ] `mediashield_can_watch` filter works (custom callback)
- [ ] Domain whitelisting blocks embeds on unlisted domains
- [ ] Concurrent stream limit shows proper error message

### Upload (Self-Hosted)
- [ ] File upload via REST endpoint works
- [ ] .htaccess created in upload directory
- [ ] Uploaded video accessible via proxy endpoint (not direct URL)
- [ ] Upload progress tracking works
- [ ] File size limit enforced
- [ ] Supported formats: MP4, WebM, MOV, M4V

### Uninstall
- [ ] Deactivation clears crons
- [ ] Uninstall drops all 6 tables
- [ ] Uninstall removes all free ms_* options
- [ ] Uninstall does NOT delete Pro options when Pro is active
- [ ] Uninstall removes capability from roles

### GDPR
- [ ] Personal data exporter registered
- [ ] Export includes watch sessions, milestones, tags
- [ ] Personal data eraser registered
- [ ] Erasure anonymizes IP/user agent in sessions
- [ ] Erasure deletes milestones and tag assignments
- [ ] Aggregate analytics preserved after erasure

### REST API
- [ ] All endpoints require proper authentication
- [ ] `manage_options` required for admin endpoints
- [ ] Nonce validation works
- [ ] Invalid input returns proper error responses
- [ ] Rate limiting does not block legitimate admin requests

### Compatibility
- [ ] Works with full-page caching plugins (WP Super Cache, W3TC, LiteSpeed)
- [ ] No JS conflicts on pages without videos
- [ ] Assets only load on pages with video content
- [ ] Works with LearnDash lesson pages
- [ ] Works with BuddyBoss/BuddyPress
- [ ] Works with Elementor page builder
- [ ] Works with WooCommerce (no checkout interference)
- [ ] Responsive at 782px breakpoint (tablet)
- [ ] Responsive at 480px breakpoint (mobile)
- [ ] Player works in WordPress block editor preview
- [ ] No PHP notices/warnings in debug.log
- [ ] No deprecated function calls

### i18n
- [ ] All user-facing strings wrapped in translation functions
- [ ] POT file generated and up to date
- [ ] No hardcoded English strings in JS
- [ ] RTL layout not broken (basic check)

### Performance
- [ ] Admin SPA loads in under 3 seconds
- [ ] No N+1 queries on video list page
- [ ] Session heartbeat does not degrade page performance
- [ ] Output buffer scanning does not add visible latency
- [ ] Cron jobs complete without timeout
