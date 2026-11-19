---
journey: protection-levels
plugin: mediashield
priority: high
roles: [guest, subscriber, administrator]
covers: [protection-levels, card:10526229932, card:10527059554]
prerequisites:
  - "Site reachable at $SITE_URL, Free active"
  - "Published self-hosted videos NONE, BASIC, STANDARD and STRICT set to those protection levels, each on its own page"
  - "A YouTube video YT (level Standard) with a Featured Image, on its own page"
estimated_runtime_minutes: 10
---

# Each protection level does what its label says

The owner picks a level per video from the labels in Settings, the video
edit screen, the Video block and the setup wizard. Each label must match
what a viewer then gets.

## Steps

1. card:10526229932 - As an administrator open MediaShield > Settings > Protection, the Video block's level picker and the wizard's General step. **Expect** None reads "No extra protection" (Settings adds "The site-wide Require Login setting still applies."), never "Free preview" or "No protection". With Require Login on, open NONE logged out: **Expect** the login prompt. Turn Require Login off: **Expect** NONE plays for the guest.
2. card:10527059554 - Turn **Block Right-Click** off. As a subscriber right-click BASIC's player. **Expect** no browser context menu. Right-click STANDARD's player. **Expect** the context menu (Standard follows the toggle). Turn the toggle back on: **Expect** no menu on either.

## Pass criteria
Every step's Expect holds at 1440px and 390px.
