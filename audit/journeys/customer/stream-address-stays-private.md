---
journey: stream-address-stays-private
plugin: mediashield
priority: high
roles: [guest, subscriber]
covers: [card:10426248133, bunny-direct-play, player-route]
prerequisites:
  - "Require Login on; one Bunny video in a playlist with direct play on; one self-hosted video with an explicit CDN playlist address"
  - "Never delete or edit anything at bunny.net"
estimated_runtime_minutes: 6
---

# A visitor who may not watch never receives the video address

A site owner who turns on Require Login expects a logged-out visitor to get no
usable video address from the page, the player route, or the page source.

## Steps
1. Logged out, `curl` the page holding each video. **Expect** `data-stream-url` is empty and the CDN host appears nowhere in the HTML.
2. Logged out, `GET /wp-json/mediashield/v1/playlists/{id}/player/{video}`. **Expect** no stream address in the response.
3. Put a password on the playlist. **Expect** the same route answers 404 until the password is entered.
4. As a member, repeat 1. **Expect** the video plays (direct play address present).
5. Turn Require Login off. **Expect** a logged-out visitor now plays, and the setting is the only difference.
6. Break the token in a playing video's address. **Expect** it recovers by itself (also with Plain permalinks).

## Pass criteria
Steps 1 to 3 give a visitor nothing usable, 4 to 6 work as stated. Direct play is put back as found.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Address in guest HTML | gate missing | `includes/Player/Renderer.php` |
| Address in route reply | route skips the gate | `includes/REST/PlaylistController.php` |
