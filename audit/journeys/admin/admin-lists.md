---
journey: admin-lists
plugin: mediashield
priority: high
roles: [administrator]
covers: [admin-playlists, admin-tags, admin-videos, card:10526587425, card:10530930295]
prerequisites:
  - "Site reachable at $SITE_URL, Free active"
  - "25 or more published playlists (wp post generate --post_type=mediashield_playlist --count=25)"
estimated_runtime_minutes: 5
---

# Admin list screens show every row, a page at a time

A site with many playlists, tags or videos must reach every one from the
admin screens, not only the first page the REST API returns by default.

## Steps

1. card:10526587425 - Open MediaShield > Playlists. **Expect** the header counts every playlist (25+), 20 rows on page 1, a "Page 1 of 2" pager with Previous disabled; Next shows the remaining rows and Previous returns. Check at 1440px and 390px.

2. card:10530930295 - With 30+ videos (Bunny imports, a YouTube and a Vimeo video, two pages embedding a video), open MediaShield > Videos. **Expect** columns Video (thumbnail + title), Platform, Duration, Status (Ready / Encoding / Failed), Protection, Used in (links to the pages that embed it), Actions; "Page 1 of N" with 20 rows; no badge or platform name clipped or wrapped at 1440px (`scrollWidth <= clientWidth`), stacked cards and no horizontal scroll at 390px. At 1440 and 1280 all 8 header cells have a width above zero and line up with their row cells (left edges within 2px), the table is not wider than its card, and titles take one or two lines; at 1024 Duration and Used in step aside and nothing else is cut (bounce: the shared five-column nth-child widths zeroed Protection, Used in and Actions).
3. card:10530930295 - Set Platform = YouTube, then Protection = Strict. **Expect** only matching videos and the header count follows the filter. Clear both.
4. card:10530930295 - Search a few test videos, tick "Select all on this page", choose "Set protection: Basic", Apply. **Expect** "N updated." and every row now shows Basic. Tick two test drafts, choose "Move to Trash", Apply. **Expect** they leave the list; the Trash view lists them and "Restore" brings them back. Nothing is deleted at the video platform.
5. card:10530930295 - With Pro and a Bunny connection, the header shows "Import from Bunny" next to "Add New Video" and it opens the Bunny Library.

## Pass criteria
Every step's Expect holds.
