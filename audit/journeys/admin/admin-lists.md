---
journey: admin-lists
plugin: mediashield
priority: high
roles: [administrator]
covers: [admin-playlists, admin-tags, card:10526587425]
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

## Pass criteria
Every step's Expect holds.
