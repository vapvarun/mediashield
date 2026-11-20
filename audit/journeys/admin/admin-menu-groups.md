---
journey: admin-menu-groups
plugin: mediashield
priority: high
roles: [administrator]
covers: [admin-spa, sidebar, card:10530930846]
prerequisites:
  - "Site reachable at $SITE_URL; MediaShield active, Pro active and inactive in turn"
estimated_runtime_minutes: 5
---

# The admin menu has six groups and loses no screen

## Check lines

1. card:10530930846 - As an administrator open MediaShield. **Expect** six top items in the app menu: Dashboard, Videos, Analytics, Security, Platforms, Settings. Open each of `#/dashboard #/videos #/playlists #/tags #/upload-queue #/viewers #/students #/heatmap #/realtime #/milestones #/export #/alerts #/platforms #/bunny-browser #/settings #/license #/milestone-config`: **Expect** each opens its own screen (not the Dashboard fallback), its group is highlighted and the screen is marked current under it. With Pro inactive: **Expect** still six items, Security and Platforms shown locked with PRO, and Heatmaps, Realtime and Export locked under Analytics. At 390px: **Expect** the six icons on one row and the open group's screens on a second row, each a 40px target. **On fail**: `src/admin/components/Sidebar.js` (`GROUPS`), `src/admin/admin.css` (`.mediashield-sidebar__group`, `.mediashield-sidebar__sub`).
