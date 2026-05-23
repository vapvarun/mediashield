---
journey: gdpr-export-and-erase
plugin: mediashield
priority: critical
roles: [administrator, subscriber]
covers:
  - PrivacyExporter (watch sessions, milestones, _ms_video_tags)
  - PrivacyEraser
  - wp_privacy_personal_data_exporters filter
  - wp_privacy_personal_data_erasers filter
prerequisites:
  - "Site reachable at $SITE_URL"
  - "FREE plugin active"
  - "Administrator with autologin: ?autologin=journey-admin"
  - "Subscriber with prior watch history: journey-subscriber"
  - "wp-cli available"
estimated_runtime_minutes: 6
---

# GDPR personal data export + erase — every plugin-owned table is covered

A subscriber has watched protected videos, hit milestones, and earned tagged
milestone entries. When they invoke their GDPR right to export, the exporter
must surface that data; when they invoke their right to be forgotten, the
eraser must remove it (or anonymize it). If a single table or user-meta key
slips through, the plugin ships a GDPR violation.

## Setup

- Site: `$SITE_URL`
- Admin: `journey-admin` (autologin)
- Subject: `journey-subscriber` (autologin; capture `SUBJECT_ID`, `SUBJECT_EMAIL`)
- Fixture history (seed via wp-cli):
  ```bash
  SUBJECT_ID=$(wp user get journey-subscriber --field=ID)
  SUBJECT_EMAIL=$(wp user get journey-subscriber --field=user_email)
  VIDEO_ID=$(wp post list --post_type=mediashield_video --post_status=publish --posts_per_page=1 --format=ids)
  wp eval '
    global $wpdb;
    $sid='"$SUBJECT_ID"'; $vid='"$VIDEO_ID"'; $now=current_time("mysql",true);
    $wpdb->insert($wpdb->prefix."ms_watch_sessions", [
      "video_id"=>$vid,"user_id"=>$sid,"session_token"=>"journey-tok","ip_address"=>"203.0.113.7",
      "user_agent"=>"JourneyAgent","device_type"=>"desktop","browser"=>"qa","started_at"=>$now,
      "last_heartbeat"=>$now,"total_seconds"=>120,"max_position"=>60,"completion_pct"=>50,"is_active"=>0
    ]);
    $wpdb->insert($wpdb->prefix."ms_milestones",["video_id"=>$vid,"user_id"=>$sid,"milestone_pct"=>50,"reached_at"=>$now]);
    update_user_meta($sid, "_ms_video_tags", [ $vid."_50" => ["video_id"=>$vid,"pct"=>50,"tag"=>"journey-tag","earned_at"=>$now] ]);
  '
  ```

## Steps

### 1. Subject's data exists across all plugin tables
- **Action**: SQL probes for `user_id = SUBJECT_ID`.
- **Expect**:
  ```sql
  SELECT COUNT(*) FROM wp_ms_watch_sessions WHERE user_id = SUBJECT_ID;  -- >= 1
  SELECT COUNT(*) FROM wp_ms_milestones      WHERE user_id = SUBJECT_ID;  -- >= 1
  SELECT meta_value FROM wp_usermeta WHERE user_id = SUBJECT_ID AND meta_key = '_ms_video_tags';
  ```

### 2. GDPR exporter returns every category
- **Action**: admin triggers an export request for `SUBJECT_EMAIL`, then runs the exporter callback for the MediaShield groups:
  ```bash
  wp eval '
    $req = wp_create_user_request("'"$SUBJECT_EMAIL"'", "export_personal_data");
    $exporters = apply_filters("wp_privacy_personal_data_exporters", []);
    foreach ($exporters as $key => $e) {
      if (strpos($key, "mediashield") === 0) {
        $r = call_user_func($e["callback"], "'"$SUBJECT_EMAIL"'", 1);
        echo $key.":".count($r["data"])."\n";
      }
    }
  '
  ```
- **Expect**:
  - At least one MediaShield exporter group keyed under a `mediashield*` slug returns `data` with > 0 records.
  - Across all MediaShield groups, the export references the seeded `session_token`, the milestone (`50%`), and the `_ms_video_tags` payload.
- **On fail**: `includes/Privacy/PrivacyExporter.php` — exporter not registered on `wp_privacy_personal_data_exporters`, or a group's callback returns an empty `data` array even when rows exist.

### 3. GDPR eraser drops every MediaShield row
- **Action**:
  ```bash
  wp eval '
    $req = wp_create_user_request("'"$SUBJECT_EMAIL"'", "remove_personal_data");
    $erasers = apply_filters("wp_privacy_personal_data_erasers", []);
    foreach ($erasers as $key => $e) {
      if (strpos($key, "mediashield") === 0) {
        $r = call_user_func($e["callback"], "'"$SUBJECT_EMAIL"'", 1);
        echo $key.":".intval($r["items_removed"] ?? 0)."\n";
      }
    }
  '
  ```
- **Expect**:
  - Every MediaShield eraser group returns `items_removed >= 0` (no PHP error).
  - At least one group reports `items_removed > 0` for the seeded fixtures.

### 4. Post-erase — data is gone or anonymized
- **Action**: rerun the SQL probes from step 1.
- **Expect**:
  ```sql
  SELECT COUNT(*) FROM wp_ms_milestones WHERE user_id = SUBJECT_ID;   -- 0
  SELECT meta_value FROM wp_usermeta WHERE user_id = SUBJECT_ID AND meta_key = '_ms_video_tags';
                                                                       -- empty / row gone
  ```
  Sessions may be anonymized rather than deleted — accepted if `ip_address` and `user_agent` are blanked:
  ```sql
  SELECT ip_address, user_agent FROM wp_ms_watch_sessions WHERE user_id = SUBJECT_ID;
  ```
- **On fail**:
  - Milestones still present → eraser's milestone branch broken.
  - `_ms_video_tags` still present → `delete_user_meta` call missing in `PrivacyEraser.php`.
  - PII still in sessions → anonymization (or delete) missing.

### 5. No debug.log noise across the full GDPR cycle
- **Action**: `cat wp-content/debug.log`
- **Expect**: empty (no PHP notices/warnings/errors from the export or erase).
- **On fail**: silent failure in an eraser/exporter callback — chase the first warning.

## Pass criteria

ALL of the following hold:
1. Exporter groups under `mediashield*` return non-empty `data` for the seeded user.
2. Exported data references the watch session, the milestone, and `_ms_video_tags`.
3. Eraser groups under `mediashield*` execute without PHP errors and report `items_removed > 0`.
4. Post-erase: milestones row gone; `_ms_video_tags` cleared; session row gone OR anonymized (no IP / no user agent).
5. `debug.log` is empty across the full cycle.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| No MediaShield exporter groups appear | Not hooked to `wp_privacy_personal_data_exporters` | `includes/Privacy/PrivacyExporter.php::register` |
| Exporter returns data but missing `_ms_video_tags` | User-meta branch not implemented | `includes/Privacy/PrivacyExporter.php` |
| Eraser leaves sessions with raw IP | Anonymization path missing | `includes/Privacy/PrivacyEraser.php` |
| Eraser leaves `_ms_video_tags` | `delete_user_meta( …, '_ms_video_tags' )` missing | `includes/Privacy/PrivacyEraser.php` |
| Notices in `debug.log` during export | Type coercion on missing fields | `PrivacyExporter` group callbacks |
