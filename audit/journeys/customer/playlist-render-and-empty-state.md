---
journey: playlist-render-and-empty-state
plugin: mediashield
priority: critical
roles: [administrator, editor, subscriber, anonymous]
covers:
  - mediashield_playlist (CPT)
  - "[mediashield_playlist] shortcode"
  - mediashield/playlist block
  - PlaylistRenderer::render
  - empty-state notice (card 9905052855)
prerequisites:
  - "Site reachable at $SITE_URL"
  - "FREE plugin active"
  - "At least one published mediashield_playlist with >= 1 item (HAPPY_PL_ID)"
  - "An empty published mediashield_playlist with 0 items (EMPTY_PL_ID)"
  - "A non-existent invalid playlist ID — e.g. 999999"
estimated_runtime_minutes: 5
---

# Playlist renders, and empty/invalid states surface to editors only

The `[mediashield_playlist]` shortcode and `mediashield/playlist` block must
render a real player when items exist, must show a clear notice to admins/
editors when the playlist is empty or invalid, and must stay silent for
subscribers and guests so the frontend never leaks debug copy. This is the
regression sentinel for card **9905052855** (silent empty playlist) and the
broader playlist contract.

## Setup

- Site: `$SITE_URL`
- Users (via dev-auto-login mu-plugin):
  - `journey-admin`     → `?autologin=journey-admin`
  - `journey-editor`    → `?autologin=journey-editor`
  - `journey-subscriber`→ `?autologin=journey-subscriber`
- Fixtures:
  - `HAPPY_PL_ID` — playlist with >= 1 row in `wp_ms_playlist_items`
  - `EMPTY_PL_ID` — playlist with 0 rows in `wp_ms_playlist_items`
  - A page with content: `[mediashield_playlist id=HAPPY_PL_ID][mediashield_playlist id=EMPTY_PL_ID][mediashield_playlist id=999999]` (capture `QA_PAGE_ID`)
- DB sanity:
  ```sql
  SELECT playlist_id, COUNT(*) FROM wp_ms_playlist_items
    WHERE playlist_id IN (HAPPY_PL_ID, EMPTY_PL_ID) GROUP BY playlist_id;
  ```

## Steps

### 1. Admin sees player for happy playlist, notice for empty + invalid
- **Action**: `playwright_navigate $SITE_URL/?autologin=journey-admin&p=QA_PAGE_ID`
- **Expect**:
  - DOM contains `.ms-playlist-player` for `HAPPY_PL_ID` with one or more `.ms-playlist-item` children.
  - DOM contains `.ms-playlist-notice` with text matching `/no videos yet/i` for `EMPTY_PL_ID`.
  - DOM contains `.ms-playlist-notice` with text matching `/not found|not published/i` for `999999`.
- **On fail**: `includes/Player/PlaylistRenderer.php::render` (empty/invalid branches), `src/blocks/playlist/render.php` (block delegates to PlaylistRenderer).

### 2. Editor sees the same notices
- **Action**: `playwright_navigate $SITE_URL/?autologin=journey-editor&p=QA_PAGE_ID`
- **Expect**: same as step 1 — `edit_posts` gate must allow editor role to see the notice.
- **On fail**: `current_user_can( 'edit_posts' )` check in `PlaylistRenderer::notice`.

### 3. Subscriber sees player but NO notices
- **Action**: `playwright_navigate $SITE_URL/?autologin=journey-subscriber&p=QA_PAGE_ID`
- **Expect**:
  - `.ms-playlist-player` for `HAPPY_PL_ID` is present.
  - **No** `.ms-playlist-notice` element anywhere on the page.
- **On fail**: notice gate leaked to non-editor roles.

### 4. Guest (logged out) sees player but NO notices
- **Action**: Clear cookies, `playwright_navigate $SITE_URL/?p=QA_PAGE_ID`
- **Expect**:
  - Either `.ms-protected-player` with the login overlay (if items require login) OR a public `.ms-playlist-player`.
  - **No** `.ms-playlist-notice` for empty/invalid playlists — guests see blank for those two.
- **On fail**: editor-only gate failing open for guests.

### 5. REST shape — playlist items endpoint
- **Action**: `curl $SITE_URL/wp-json/mediashield/v1/playlists/HAPPY_PL_ID/items` (admin cookie + nonce).
- **Expect**: HTTP 200, JSON array; each item has `video_id`, `sort_order`, `title`.
- **On fail**: `includes/REST/PlaylistController.php::get_items`.

### 6. Block render parity with shortcode
- **Action**: Open the page editor for `QA_PAGE_ID`, insert a `mediashield/playlist` block bound to `EMPTY_PL_ID`, save (do not publish over).
- **Expect**: Frontend now shows the same empty-state notice via the block path (delegation to `PlaylistRenderer`).
- **On fail**: `src/blocks/playlist/render.php` does not delegate, or block attributes (`playlistId`) not wired.

## Pass criteria

ALL of the following hold:
1. Happy playlist renders `.ms-playlist-player` with items for every role.
2. Empty playlist shows `.ms-playlist-notice` ("no videos yet") for admin + editor.
3. Invalid playlist ID shows `.ms-playlist-notice` ("not found / not published") for admin + editor.
4. Empty + invalid notices are absent for subscriber and guest.
5. `/playlists/{id}/items` REST returns 200 with the expected shape.
6. Block path produces identical output to the shortcode (same renderer).

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Empty playlist renders blank for admin too | Editor gate inverted, or notice helper removed | `includes/Player/PlaylistRenderer.php::notice` |
| Subscriber sees the notice | Gate broadened from `edit_posts` to `is_user_logged_in` | `includes/Player/PlaylistRenderer.php::notice` |
| Block renders nothing while shortcode renders fine | Block render.php returns early on attribute mismatch | `src/blocks/playlist/render.php` |
| `/playlists/{id}/items` returns 401/403 | Permission callback too strict (admin-only) | `includes/REST/PlaylistController.php` |
| Items render but in wrong order | Missing `ORDER BY sort_order` | `includes/REST/PlaylistController.php::get_items` |
