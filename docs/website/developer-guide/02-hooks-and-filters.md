# Hooks and Filters

**Free + Pro** - These hooks fire from the free plugin. Pro attaches to several of them and adds its own, which are listed in the Pro developer guide.

This page documents the 15 actions and 37 filters the free plugin fires in version 1.4.1, verified against the source. Browser events are on [JavaScript Events and Player Config](08-javascript-events-and-config.md).

## Actions

### mediashield_loaded

Fires at the end of `Plugin::instance()`, which Free runs on `plugins_loaded` at priority 10. Code that depends on MediaShield being ready should boot on `plugins_loaded` at priority 20 or later (as Pro does) or check `did_action( 'mediashield_loaded' )`; a callback added on `plugins_loaded` at priority 10 by a plugin that loads after Free would miss the action. Nothing in Pro listens to it.

```php
add_action( 'mediashield_loaded', function() {
    // Safe to use MediaShield APIs here.
} );
```

---

### mediashield_session_started

Fires when a new watch session is created, for logged-in viewers and guests alike.

**Parameters:** `$session_id` (int), `$video_id` (int), `$user_id` (int - 0 for guests), `$ip` (string)

```php
add_action( 'mediashield_session_started', function( $session_id, $video_id, $user_id, $ip ) {
    // Log to external analytics, fire a webhook, etc.
}, 10, 4 );
```

---

### mediashield_session_ended

Fires when a watch session is finalized.

**Parameters:** `$session_id` (int), `$video_id` (int), `$user_id` (int)

---

### mediashield_concurrent_limit_reached

Fires when a logged-in user tries to start a stream beyond their allowed concurrent limit (`ms_max_concurrent_streams`). Never fires for guests, who are not subject to the limit.

**Parameters:** `$user_id` (int), `$video_id` (int), `$active_count` (int), `$max` (int)

---

### mediashield_user_access_revoked

Fires when all sessions for a user are revoked (`POST /session/revoke-user`).

**Parameters:** `$user_id` (int), `$count` (int - number of sessions revoked)

---

### mediashield_milestone_reached

Fires when any milestone percentage is reached for a user and video. Fires once per user per video per threshold. Never fires for guests (user ID 0).

**Parameters:** `$user_id` (int), `$video_id` (int), `$pct` (int), `$session_id` (int)

```php
add_action( 'mediashield_milestone_reached', function( $user_id, $video_id, $pct, $session_id ) {
    if ( 100 === $pct ) {
        // Grant a certificate, update LMS progress, etc.
    }
}, 10, 4 );
```

---

### mediashield_milestone_{pct}

Fires for a specific milestone percentage, right after `mediashield_milestone_reached`. `mediashield_milestone_25`, `_50`, `_75`, and `_100` always exist; a video with milestone tags at other percentages (1 to 100) gets those too, and `mediashield_milestone_thresholds` can add any value.

**Parameters:** `$user_id` (int), `$video_id` (int)

```php
add_action( 'mediashield_milestone_100', function( $user_id, $video_id ) {
    learndash_process_mark_complete( $user_id, $video_id );
}, 10, 2 );
```

---

### mediashield_upload_started

*Since 1.0.1.* Fires just before an upload driver runs.

**Parameters:** `$driver` (string), `$file_path` (string), `$options` (array)

---

### mediashield_upload_complete

Fires when an upload finishes successfully.

**Parameters:** `$video_id` (int), `$driver` (string), `$result` (array)

---

### mediashield_upload_failed

*Since 1.0.1.* Fires when an upload driver returns an error.

**Parameters:** `$driver` (string), `$error` (string), `$options` (array)

---

### mediashield_before_player

*Since 1.1.0.* Fires immediately before the player container HTML is emitted. Use to enqueue per-video assets or print HTML above the player.

**Parameters:** `$video_id` (int)

---

### mediashield_after_player

*Since 1.1.0.* Fires immediately after the player HTML is built and the `mediashield_player_html` filter has run.

**Parameters:** `$video_id` (int)

---

### mediashield_needs_shaka

Fires while rendering a player whose platform needs adaptive streaming (self-hosted or Bunny by default, see `mediashield_adaptive_platforms`), from both the shortcode/block renderer and the auto-wrapper. In free this is what causes the bundled hls.js library to be enqueued. Despite the name, free does not load Shaka Player.

**Parameters:** none

---

### mediashield_devtools_detected

*Since 1.1.0.* Fires when the devtools beacon receives a detection event from the browser. Rate limited to one event per user and IP per hour; a rate-limited request does not fire the action. Free does nothing else with the event (it does not log it or write a row); Pro listens and records a suspicious-activity alert.

**Parameters:** `$context` (array with keys: `user_id`, `ip`, `url`, `strategy`, `ua`, `screen`, `at`)

`strategy` is `debugger_timing` (`size_delta`, a window-size check that fired on browser zoom, is no longer sent since 1.4.2). `at` is a UTC MySQL timestamp.

---

### mediashield_privacy_before_erase

Fires before MediaShield's GDPR eraser deletes or anonymizes rows for a given email. Use to roll your own removals into the GDPR receipt. The person's watch sessions still carry their user ID at this point; the eraser sets it to 0 afterwards, so remove anything you find through those sessions here.

**Parameters:** `$email` (string), `$user` (WP_User or false), `$page` (int), `$counters` (stdClass, passed by reference)

---

## Filters

### mediashield_can_watch

The primary access control gate. Return the `$result` array unchanged to allow access. Return an array with `allowed => false` and a `reason` string to deny.

Note that free core's own checks - the login gate, the per-video role, and the domain whitelist - run *before* this filter, not through it. A denial from any of them short-circuits and the filter never runs. To let a guest past the login gate, use `mediashield_guest_can_watch` below.

**Parameters:** `$result` (array `{allowed: bool, reason: string}`), `$video_id` (int), `$user_id` (int)

```php
// Restrict to active subscribers.
add_filter( 'mediashield_can_watch', function( $result, $video_id, $user_id ) {
    if ( ! user_has_active_subscription( $user_id ) ) {
        return array(
            'allowed' => false,
            'reason'  => 'An active subscription is required.',
        );
    }
    return $result;
}, 10, 3 );
```

Pro attaches its own gates at priority 20 (role re-check) and 25 (LMS enrollment). Pick a priority outside that range, or be ready for a decision one of them has already made.

The filter does not run for administrators: `manage_options` users are allowed before any other check.

---

### mediashield_guest_can_watch

*Since 1.4.1.* Lets an integration allow a logged-out visitor to watch a video while **Require Login** is on. An LMS uses it for free-preview lessons. Return `true` to let the guest past the login check. The default is `false`, so guests stay blocked unless something opts in.

**Parameters:** `$allow` (bool, default `false`), `$video_id` (int)

```php
// Allow guests on videos that belong to a free-preview lesson.
add_filter( 'mediashield_guest_can_watch', function( $allow, $video_id ) {
    return $allow || my_lms_video_is_preview( $video_id );
}, 10, 2 );
```

It runs only for guests, and only to answer the Require Login check. It does not turn Require Login off, and it is not a way to skip other rules: the per-video role and Allowed Domains checks still apply. Learnomy 2.2.0 uses this filter for its free-preview lessons. See [Courses with Learnomy](../using-mediashield/15-courses-with-learnomy.md).

---

### mediashield_video_watch_url

*Since 1.4.1.* Supplies the page where a member can watch a video. The My Videos block and shortcode use it to link each title and to show the **Resume** link. The default is an empty string. With no URL the title shows as plain text and there is no Resume link. Videos have no public page of their own (their permalink lands on the home page), so MediaShield cannot guess one.

**Parameters:** `$url` (string, default empty), `$video_id` (int), `$user_id` (int, the member the list is for)

```php
// Send members to the lesson that embeds this video.
add_filter( 'mediashield_video_watch_url', function( $url, $video_id, $user_id ) {
    $lesson_url = my_lms_lesson_url_for_video( $video_id );
    return $lesson_url ? $lesson_url : $url;
}, 10, 3 );
```

Return a full URL on your own site. The player on that page offers to resume by itself. Learnomy returns the lesson page, and Pro returns the lesson chosen in the video's LMS box. See [Viewers, Tags and My Videos](../using-mediashield/14-viewers-tags-and-my-videos.md).

---

### mediashield_my_videos_per_page

*Since 1.4.1.* How many videos the My Videos block and shortcode show per page. Default 12; values are kept between 1 and 100.

**Parameters:** `$per_page` (int)

```php
add_filter( 'mediashield_my_videos_per_page', fn() => 24 );
```

---

### mediashield_library_choices

*Since 1.4.4.* A read for other plugins: the videos ready to be chosen, as `[ id => title ]`, newest first. Ready means published, with something to play (not "No source", not unavailable at its platform), the same rule as the Video block's picker. Use it for a video select in your own screens, for example a lesson editor.

**Parameters:** `$choices` (array, pass `[]`), `$args` (array: `page` int, default 1; `per_page` int, default 20, at most 100; `search` string)

Ask one page at a time; a page shorter than `per_page` is the last one.

```php
$videos = apply_filters( 'mediashield_library_choices', array(), array( 'page' => 1, 'per_page' => 50, 'search' => 'intro' ) );
foreach ( $videos as $id => $title ) {
	printf( '<option value="%d">%s</option>', $id, esc_html( $title ) );
}
```

---

### mediashield_plays_bunny

*Since 1.4.4.* Whether Bunny Stream links play on this site. False in the free plugin; MediaShield Pro answers true when a Bunny library is connected. It decides whether the "which links work" words (link field hints, the setup wizard, the refusal for a link that cannot play) name Bunny Stream.

**Parameters:** `$plays` (bool)

---

### mediashield_watermark_config

Customize the watermark configuration handed to the browser in the `POST /session/start` response.

**Parameters:** `$config` (array with keys `enabled`, `opacity`, `color`, `swap_interval`, `text`, `ip`), `$video_id` (int), `$user_id` (int)

`text` is the display name (or "Guest"); `ip` is appended by the client on players 640 px wide or wider. Pro uses this filter to compose its 7-field text and adds `font_size` and `show_badge`.

---

### mediashield_protection_levels

*Since 1.3.0.* Filter the protection levels offered on the video edit screen.

**Parameters:** `$levels` (array, slug => label), `$post` (WP_Post), `$selected` (string)

Adding a level only makes it selectable and storable. Whatever it is meant to *do* still has to be implemented, typically through `mediashield_player_type`.

---

### mediashield_upload_drivers

Register custom upload driver classes. Each class must implement `MediaShield\Upload\Drivers\DriverInterface`.

**Parameters:** `$drivers` (array, driver name => class name)

```php
add_filter( 'mediashield_upload_drivers', function( $drivers ) {
    $drivers['s3'] = MyPlugin\Upload\S3Driver::class;
    return $drivers;
} );
```

---

### mediashield_stored_filename

*Since 1.3.0.* Filter the on-disk filename for a self-hosted upload. Stored names carry a random token so a file path is impractical to guess; return the plain sanitised name to opt out.

**Parameters:** `$obscured` (string), `$original_name` (string)

---

### mediashield_library_file_in_use

*Since 1.4.2.* Report a use of a Media Library file that MediaShield cannot see, so the file is left in place instead of being moved into the protected folder. Return where it is used, in a few words the owner will read in the notice (for example `a Learnomy lesson`). MediaShield's Learnomy integration uses it for lesson and course URLs.

**Parameters:** `$where` (string, '' when unused so far), `$relative` (string, path below the uploads folder), `$attachment_id` (int; since 1.4.3 always set, because a file with no Media Library item is never moved), `$video_id` (int)

```php
add_filter( 'mediashield_library_file_in_use', function( $where, $relative ) {
    return '' === $where && my_plugin_uses_file( $relative ) ? 'a My Plugin page' : $where;
}, 10, 2 );
```

---

### mediashield_library_files_batch_size

*Since 1.4.2.* Videos checked per pass of the one-off `ms_protect_library_files` job, and since 1.4.3 of the one-off `ms_repair_library_files` job. Default 50, clamped to 10-200.

**Parameters:** `$batch` (int)

---

### mediashield_poster_rename_batch_size

*Since 1.4.2.* Videos checked per pass of the one-off `ms_rename_id_posters` job, which renames posters saved under the platform video ID. Default 50, clamped to 10-200.

**Parameters:** `$batch` (int)

---

### mediashield_player_type

Override the player type for a video. Free renders `standard` for every video; the value is emitted as `data-player-type` on the player container.
**Parameters:** `$type` (string), `$video_id` (int)

---

### mediashield_video_stream_url

*Since 1.2.0.* Supply or override the direct stream URL for a video, which is what lets MediaShield play it in a real `<video>` element instead of a provider iframe. Pro uses it to build Bunny HLS playlist URLs for videos imported before the URL was recorded.

**Parameters:** `$stream_url` (string), `$video_id` (int), `$platform` (string), `$platform_video_id` (string)

---

### mediashield_milestone_thresholds

Customize which completion percentages trigger milestones. Percentages enabled in a video's Milestone Tags box are merged in on top of whatever this returns.

**Parameters:** `$thresholds` (array of ints, default `[25, 50, 75, 100]`), `$video_id` (int)

---

### mediashield_settings_response

Filter the settings REST API GET response. Use to expose additional settings to the admin SPA.

**Parameters:** `$settings` (array)

---

### mediashield_settings_update

Filter settings data before saving from the settings REST API PUT. Use to intercept and save your own settings keys.

**Parameters:** `$data` (array)

---

### mediashield_trusted_ip_headers

Configure which HTTP headers are checked for client IP detection when recording a session or a devtools event. Default: `array( 'REMOTE_ADDR' )`. Useful when behind a proxy or CDN.

**Parameters:** `$headers` (array of header name strings)

```php
add_filter( 'mediashield_trusted_ip_headers', function( $headers ) {
    array_unshift( $headers, 'HTTP_CF_CONNECTING_IP' );
    return $headers;
} );
```

The watermark overlay reads `REMOTE_ADDR` directly and is not affected by this filter.

---

### mediashield_enable_output_buffer

Control whether the output-buffer scan that wraps library videos in a pasted embed runs on the current request. Return `false` to turn the scan off for the request. See also `mediashield_force_output_buffer`.

**Parameters:** `$enabled` (bool)

```php
// Disable on WooCommerce checkout.
add_filter( 'mediashield_enable_output_buffer', function( $enabled ) {
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        return false;
    }
    return $enabled;
} );
```

---

### mediashield_player_html

Filter the final rendered player HTML of the shortcode, block and playlist player.

**Parameters:** `$html` (string), `$video_id` (int), `$atts` (array with keys `platform`, `protection_level`, `player_type`)

---

### mediashield_unprotected_player_html

*Since 1.2.0.* Filter the plain player markup emitted when MediaShield is switched off site-wide (`ms_enabled` off).

**Parameters:** `$html` (string), `$video_id` (int), `$platform` (string)

---

### mediashield_allow_empty_referer

*Since 1.1.0.* When the allowed-domain whitelist is active, controls whether requests with no Referer header are allowed. Default `false` (deny).

**Parameters:** `$allow` (bool)

---

### mediashield_frontend_config

*Since 1.1.0.* Filter the frontend localized config payload before it is emitted as `window.mediashieldConfig`.

**Parameters:** `$config` (array)

---

### mediashield_player_classes

*Since 1.1.0.* Filter the CSS classes on the player container element.

**Parameters:** `$classes` (array, default `['ms-protected-player']`), `$video_id` (int)

---

### mediashield_protection_config

*Since 1.1.0.* Filter the protection JavaScript config before it is passed to `protection.js`. Keys are the booleans and strings built from the `ms_block_*`, `ms_hide_source` and `ms_*devtools*` settings.

**Parameters:** `$config` (array with keys `block_right_click`, `block_keyboard`, `hide_source`, `detect_devtools`, `pause_on_devtools`, `devtools_title`, `devtools_message`)

---

### mediashield_player_access_type

*Since 1.1.0.* Return a non-empty slug to emit as `data-access-type` on the player container, so the client can render an alternative gate UI instead of the login overlay. Applied on both render paths (shortcode/block and auto-wrapped embeds).

**Parameters:** `$access_type` (string), `$video_id` (int)

---

### mediashield_session_allow_anonymous_start

*Since 1.1.0.* Gates whether anonymous (logged-out) visitors may call `POST /session/start` for a given video.

Defaults to `true` when the `ms_require_login` setting is off (*since 1.3.0*), or when the video has a non-empty `_ms_access_type` meta value. Reaching the handler is not the same as being allowed to watch: `AccessControl::can_watch()` still runs.

**Parameters:** `$allow` (bool), `$video_id` (int), `$access_type` (string), `$request` (WP_REST_Request)

---

### mediashield_shortcode_source_url

*Since 1.1.0.* Override the source URL resolved by the `[mediashield]` shortcode at render time.

**Parameters:** `$source_url` (string), `$video_id` (int), `$atts` (array)

---

### mediashield_enqueue_frontend

*Since 1.1.0.* Return `false` to prevent the frontend player assets from registering.

**Parameters:** `$register` (bool)

---

### mediashield_embed_url

*Since 1.3.0.* Filter a minted signed embed URL. See [Extension Architecture](05-extension-architecture.md) for what embed links are.

**Parameters:** `$url` (string), `$video_id` (int), `$user_id` (int)

---

### mediashield_video_ad_breaks

Supply the in-video ad break list for a video. Returning a non-empty array is what causes the ad engine to load. Free ships a bridge that fills this from WB Ad Manager video creatives; with no ad plugin the list is empty and nothing loads.

**Parameters:** `$breaks` (array), `$video_id` (int), `$duration` (int, seconds)

---

### mediashield_video_ads

Filter the pool of ad creatives available to a video before breaks are planned. Return an empty array to suppress ads for that video. Each item keeps the shape `{ id, label, video_url, click_url, skip_after }`.

**Parameters:** `$ads` (array), `$video_id` (int), `$duration` (int)

---

### mediashield_ad_break_plan

Filter the break plan. Default is a pre-roll plus the configured number of mid-rolls, no post-roll.

**Parameters:** `$plan` (array `{ pre: bool, mid_count: int, post: bool }`), `$video_id` (int), `$duration` (int)

---

### mediashield_restore_archive_batch_size

*Since 1.3.0.* Rows moved per batch by the one-off job that restores archived watch sessions to the live table. Default 2000, clamped to 100-20000.

**Parameters:** `$batch` (int)

---

### mediashield_privacy_export_result

*Since 1.0.1.* Filter the GDPR export result before WordPress processes it. Append your own items, or set `done` to false while you still have rows to hand back on a later page.

**Parameters:** `$result` (array `{data: array, done: bool}`), `$email` (string), `$user` (WP_User or false), `$page` (int)

---

### mediashield_privacy_erase_result

Filter the final GDPR erasure result before WordPress processes it. Append messages or adjust counts.

**Parameters:** `$result` (array), `$email` (string), `$user` (WP_User or false), `$page` (int)

---

### mediashield_player_overrides

*Since 1.3.0.* Which player features an editor can override per video. Maps a post meta key to the config key the player reads. Default: `_ms_player_speed` => `speedControl`, `_ms_player_keyboard` => `keyboard`, `_ms_player_resume` => `resume`, `_ms_player_sticky` => `sticky`, `_ms_player_endscreen` => `endscreen`, `_ms_player_prevent_forward_seek` => `preventForwardSeek`. A meta value of `on` or `off` wins over the site setting; empty means follow the setting.

**Parameters:** `$map` (array, meta key => config key)

---

### mediashield_adaptive_platforms

*Since 1.3.0.* Platforms that load the adaptive-streaming (hls.js) player. Default: `array( 'self', 'bunny' )`.

**Parameters:** `$platforms` (string[], platform slugs)

---

### mediashield_bunny_pull_zones

*Since 1.3.1.* Bunny pull-zone hostnames this site's videos are served from, so a Bunny stream URL can be matched to its library. Hostnames are lower-cased.

**Parameters:** `$zones` (array, hostname => library ID, default empty)

```php
add_filter( 'mediashield_bunny_pull_zones', function( $zones ) {
    $zones['vz-12345678-abc.b-cdn.net'] = '12345';
    return $zones;
} );
```

---

### mediashield_default_upload_driver

*Since 1.3.0.* The upload driver used when `ms_default_upload_target` is `auto`. Default `self_hosted`. Pro answers with its first connected platform. If the returned name is not a registered driver, `self_hosted` is used.

**Parameters:** `$driver` (string)

---

### mediashield_force_output_buffer

*Since 1.3.0.* The output-buffer scan only starts when the page's own content contains a recognised embed. Return `true` to buffer every front-end request, for themes and page builders that print embeds outside post content. Costs one buffered page per request.

**Parameters:** `$force` (bool, default `false`)

---

### mediashield_claim_files_batch_size

*Since 1.4.0.* Videos handled per pass of the one-off job that records which video owns each uploaded file. Default 200, clamped to 50-1000.

**Parameters:** `$batch` (int)

---

### mediashield_display_timezone

*Since 1.4.3.* The timezone MediaShield shows times in: analytics activity, the PDF report and the weekly digest (with MediaShield Pro). Times are stored in UTC; only the display moves. Default is the site timezone from Settings > General. Return a person's own timezone to show each viewer their local time. A value that is not a `DateTimeZone` falls back to the site timezone.

**Parameters:** `$timezone` (DateTimeZone), `$user_id` (int, the person the time is shown to; for the weekly digest, the recipient's account when the digest address belongs to one)

```php
add_filter( 'mediashield_display_timezone', function( $timezone, $user_id ) {
    $own = get_user_meta( $user_id, 'my_timezone', true );
    return $own ? new DateTimeZone( $own ) : $timezone;
}, 10, 2 );
```

---

### mediashield_measure_watch

*Since 1.4.4.* Decides whether a player at the **None** or **Basic** level measures a logged-in member's watch time for the site-wide "Watch X% to complete" (**Settings > Lesson Completion**). That percentage is about lessons, and the player cannot tell a lesson from a blog post, so the default is `false`: return `true` on the screens where your LMS completes a lesson by it. Standard and Strict videos measure anyway. A lesson that sets its own percentage on the player (`data-watch-required`, the shortcode's `watch_required`) is measured without this filter.

**Parameters:** `$measure` (bool, default `false`), `$video_id` (int), `$post_id` (int, the post or page being shown; 0 when there is none)

```php
// Measure on my LMS's lesson pages.
add_filter( 'mediashield_measure_watch', function( $measure, $video_id, $post_id ) {
    return $measure || 'my_lesson' === get_post_type( $post_id );
}, 10, 3 );
```

MediaShield Pro answers `true` on LearnDash lessons and topics. When nothing answers, a None or Basic video makes no requests at all.

---

### mediashield_create_video

*Since 1.4.4.* The one way another plugin adds a video to the MediaShield library, for example an LMS lesson editor that offers "Add a video" or turns an ordinary video lesson into a protected one. Call it instead of writing MediaShield's posts or meta yourself.

```php
$video_id = apply_filters( 'mediashield_create_video', null, array(
    'source_url'    => 'https://youtu.be/jNQXAC9IVRw', // a YouTube, Vimeo, Wistia or Bunny Stream link, or a link to an .mp4 file
    // or 'attachment_id' => 123,                       // a video file in the Media Library
    'title'         => 'Lesson 1',                      // optional
) );

if ( is_wp_error( $video_id ) ) {
    // $video_id->get_error_message() is ready to show; get_error_data()['status'] is 400, 403, 404 or 500.
} elseif ( is_int( $video_id ) ) {
    $shortcode = '[mediashield id="' . $video_id . '"]';
}
// null: MediaShield is not active (nothing answered the filter).
```

**Parameters:** `$result` (`null`; a callback that already holds an `int` or `WP_Error` keeps it), `$args` (array):

| Key | Type | Meaning |
|-----|------|---------|
| `source_url` | string | A link MediaShield can play: YouTube, Vimeo, Wistia, Bunny Stream (with Pro), or a link to a video file. |
| `attachment_id` | int | A video file already in the Media Library. Used instead of `source_url` when both are given. |
| `title` | string | Optional. Empty: the platform's title (or "Video 123"), or the Media Library item's title for a file. |

**Returns** the video ID (`int`), or a `WP_Error`:

| Code | Status | When |
|------|--------|------|
| `mediashield_cannot_create_video` | 403 | The current user may not add videos (needs `edit_posts`), or may not edit that Media Library file. |
| `mediashield_unplayable_source` | 400 | The link is not a video MediaShield can play. The message says what to use instead, in the same words as the Add video screen. |
| `mediashield_not_a_video_file` | 404 | `attachment_id` is not a video in the Media Library. |
| (WordPress's code) | 500 | The video could not be saved. |

A video added by someone who may publish posts is published at once; anyone else's (a Contributor, say) is saved as **Pending Review** for an editor to publish, as WordPress does for posts. Check `get_post_status( $video_id )` if your screen needs to say so.

It takes the same path as **Videos > Add New**: the same link check, the same save (platform and id stored in one shape, the platform's title fetched when none is given, the Videos list status), no protection level stamped (the video follows the site default), and a Media Library file is moved into the protected folder with its Media Library item made private, as when an owner pastes that file's link. A file keeps the name it was uploaded with and its length is read from it. A link already in the library, however it is written (`youtu.be/…` or `watch?v=…`), returns that video instead of adding a copy, as the Video block does; so does the same Media Library file twice.

A file the site still uses somewhere else (a lesson's own video field, a post) is left where it is and played from there. Point that use at the new video first if the file should be protected.

---

## JavaScript events

The player dispatches browser events (`mediashield:player-ready`, `mediashield:access-denied` and others). They are documented on [JavaScript Events and Player Config](08-javascript-events-and-config.md).
