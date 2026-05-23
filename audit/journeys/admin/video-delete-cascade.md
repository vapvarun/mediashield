---
journey: video-delete-cascade
plugin: mediashield
priority: critical
roles: [administrator]
covers:
  - Cleanup::handle_video_delete
  - orphan ms_tags purge
  - _ms_video_tags user-meta purge
  - cascade ms_milestones / ms_watch_sessions / ms_playlist_items
  - analytics post_status='publish' filter
prerequisites:
  - "Site reachable at $SITE_URL"
  - "FREE plugin active"
  - "Administrator with autologin: ?autologin=journey-admin"
  - "wp-cli available on host (for fixture + assertion queries)"
estimated_runtime_minutes: 4
---

# Permanent delete cascades — no orphan tags, no orphan user-meta, no trashed videos in analytics

Card **9843340517** documented 4 ways data leaks when a video is trashed or
permanently deleted: trashed videos stay in analytics top-row queries, orphan
`ms_tags` accumulate when their only referring video is removed, per-user
`_ms_video_tags` entries dangle, and the GDPR eraser misses the same user-meta.
This journey locks all four contracts in one pass.

## Setup

- Site: `$SITE_URL`
- Admin user: `journey-admin` (autologin)
- Fixtures (created via wp-cli, capture IDs as we go):
  ```bash
  VIDEO_ID=$(wp post create --post_type=mediashield_video --post_status=publish \
    --post_title='Cascade Test Video' --porcelain)
  wp eval '
    global $wpdb;
    $vid='"$VIDEO_ID"'; $u=1;
    $wpdb->insert($wpdb->prefix."ms_tags",      ["name"=>"orphan-tag-".$vid,"slug"=>"orphan-".$vid,"created_by"=>$u,"created_at"=>current_time("mysql",true)]);
    $tag=(int)$wpdb->insert_id;
    $wpdb->insert($wpdb->prefix."ms_video_tags",["video_id"=>$vid,"tag_id"=>$tag,"tagged_by"=>$u,"tagged_at"=>current_time("mysql",true)]);
    $wpdb->insert($wpdb->prefix."ms_milestones",["video_id"=>$vid,"user_id"=>$u,"milestone_pct"=>50,"reached_at"=>current_time("mysql",true)]);
    update_user_meta($u, "_ms_video_tags", [ $vid."_50" => ["video_id"=>$vid,"pct"=>50,"tag"=>"orphan-tag","earned_at"=>current_time("mysql",true)] ]);
    echo "TAG_ID=".$tag."\n";
  '
  ```

## Steps

### 1. Baseline — every fixture row present
- **Action**: SQL probes for `VIDEO_ID` / `TAG_ID` / `user_id=1`.
- **Expect**:
  ```sql
  SELECT COUNT(*) FROM wp_ms_tags        WHERE id = TAG_ID;             -- 1
  SELECT COUNT(*) FROM wp_ms_video_tags  WHERE video_id = VIDEO_ID;     -- 1
  SELECT COUNT(*) FROM wp_ms_milestones  WHERE video_id = VIDEO_ID;     -- 1
  SELECT meta_value FROM wp_usermeta WHERE user_id = 1 AND meta_key = '_ms_video_tags';  -- contains VIDEO_ID_50
  ```

### 2. Trash the video — analytics top-rows must exclude it immediately
- **Action**:
  - `wp post delete VIDEO_ID` (without `--force` — trashes only).
  - `curl "$SITE_URL/wp-json/mediashield/v1/analytics/overview?range=30" -H "X-WP-Nonce: $NONCE" --cookie "$ADMIN_JAR"`
  - `curl ".../analytics/users/1" ...`
  - `curl ".../analytics/milestones" ...`
- **Expect**: the trashed `VIDEO_ID` does NOT appear as a row in `top_videos`, `recent_milestones`, or user-detail breakdowns. (Aggregate counts may still include historical rows — that's fine; only named-row queries must filter.)
- **On fail**: `includes/REST/AnalyticsController.php` named-row queries missing `INNER JOIN posts ... post_status='publish'`.

### 3. Permanently delete the video
- **Action**: `wp post delete VIDEO_ID --force` (fires `before_delete_post` → `Cleanup::handle_video_delete`).
- **Expect**: HTTP success; no PHP error in `wp-content/debug.log`.
- **On fail**: `includes/Cron/Cleanup.php::handle_video_delete` threw, or `before_delete_post` not hooked.

### 4. Cascade — every fixture row gone, including the orphan tag
- **Action**: rerun the SQL probes from step 1.
- **Expect**:
  ```sql
  SELECT COUNT(*) FROM wp_ms_video_tags WHERE video_id = VIDEO_ID;   -- 0
  SELECT COUNT(*) FROM wp_ms_milestones  WHERE video_id = VIDEO_ID;   -- 0
  SELECT COUNT(*) FROM wp_ms_watch_sessions WHERE video_id = VIDEO_ID; -- 0
  SELECT COUNT(*) FROM wp_ms_tags WHERE id = TAG_ID;                  -- 0  ← orphan dropped
  ```
- **On fail**: orphan-tag `NOT EXISTS` cleanup missing → `includes/Cron/Cleanup.php::handle_video_delete` (the `DELETE t FROM ms_tags t WHERE t.id IN (...) AND NOT EXISTS (...)` block).

### 5. User-meta entry purged
- **Action**:
  ```sql
  SELECT meta_value FROM wp_usermeta WHERE user_id = 1 AND meta_key = '_ms_video_tags';
  ```
- **Expect**: result is empty, OR the array no longer contains a key starting with `VIDEO_ID_`.
- **On fail**: `includes/Cron/Cleanup.php::purge_user_milestone_tags_for_video` regressed.

### 6. GDPR eraser also drops the user-meta
- **Action**: trigger GDPR erase request for user 1's email via WP admin → Tools → Erase Personal Data, or:
  ```bash
  wp eval '
    $req = wp_create_user_request("admin@example.com","remove_personal_data");
    do_action("wp_privacy_personal_data_erasure_page", 1, $req);
  '
  ```
- **Expect**: after erase, ANY remaining `_ms_video_tags` entries for that user are gone.
- **On fail**: `includes/Privacy/PrivacyEraser.php` does not call `delete_user_meta( …, '_ms_video_tags' )`.

## Pass criteria

ALL of the following hold:
1. Trashed video disappears from named-row analytics queries within one refresh.
2. Permanent delete cascades to `ms_video_tags`, `ms_milestones`, `ms_watch_sessions`.
3. Tag becomes orphan after its only video is removed → row dropped from `ms_tags`.
4. `_ms_video_tags` user-meta entries keyed to the deleted video are purged.
5. GDPR erase also drops `_ms_video_tags` for the targeted user.
6. `debug.log` stays empty across the whole cascade.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Trashed video still appears in Top Videos | Analytics named-row query missing `post_status='publish'` join | `includes/REST/AnalyticsController.php` |
| `ms_tags` row remains after only video deleted | Orphan-tag `NOT EXISTS` block missing | `includes/Cron/Cleanup.php` |
| User-meta still contains `VIDEO_ID_*` | `purge_user_milestone_tags_for_video` not called or LIKE narrowed | `includes/Cron/Cleanup.php` |
| GDPR erase leaves `_ms_video_tags` | PrivacyEraser doesn't touch the key | `includes/Privacy/PrivacyEraser.php` |
| Aggregate counts shift wildly | Aggregate queries inadvertently joined with `post_status` filter (over-filtered) | `includes/REST/AnalyticsController.php` |
