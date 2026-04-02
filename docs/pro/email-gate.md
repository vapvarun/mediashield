# MediaShield Pro -- Email Gate

The email gate requires visitors to submit their email address before accessing gated videos. This is useful for lead generation, content locking, and building email lists.

---

## How It Works

1. A video is configured with `_ms_access_type` = `email_gate`.
2. When a user tries to watch, the `mediashield_can_watch` filter (priority 15) checks for a valid email gate cookie.
3. If no cookie exists, the session start request returns a 403 with `email_gate_required` reason.
4. The frontend JavaScript dispatches a `mediashield:access-denied` custom event.
5. `email-gate.js` listens for this event and displays an email capture overlay.
6. The user submits their email via `POST /mediashield-pro/v1/email-gate/submit`.
7. On success, a cookie is set and the video becomes accessible.

---

## Setting Up Email Gate

### Per-Video

1. Edit a video in **MediaShield > Videos**.
2. In the sidebar, set **Access Type** to "Email Gate".
3. Save the video.

### Global Configuration

Configure email gate behavior in **MediaShield > Settings**:

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Webhook URL | `ms_email_gate_webhook_url` | `''` | URL to POST captured emails (e.g., Zapier, n8n) |
| Cookie Duration | `ms_email_gate_cookie_duration` | `7` | Days the access cookie persists |
| Retention Period | `ms_email_retention_months` | `12` | Months before captured emails are auto-deleted |

---

## Email Capture Overlay

The overlay appears as a dark backdrop with a centered form containing:

- Email input field
- Name input field (optional)
- Consent checkbox with configurable text
- Submit button

Styling is in `assets/css/email-gate.css`.

---

## Rate Limiting

The email gate endpoint includes built-in rate limiting:
- Maximum 5 submissions per IP per hour
- Prevents abuse and bot submissions

---

## Webhook Integration

When a webhook URL is configured, each email submission triggers a POST request:

```json
{
  "email": "user@example.com",
  "name": "Jane Doe",
  "video_id": 42,
  "video_title": "Introduction to Course",
  "consent_given": true,
  "consent_text": "I agree to receive updates",
  "ip_address": "203.0.113.45",
  "timestamp": "2026-04-01T12:00:00Z",
  "source": "email_gate"
}
```

Use this to send captures to:
- **Email marketing** -- Mailchimp, ConvertKit, ActiveCampaign
- **CRM** -- HubSpot, Salesforce
- **Automation** -- Zapier, n8n, Make
- **Custom endpoints** -- Your own API

---

## Database

Email captures are stored in `ms_email_captures`:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Auto-increment ID |
| `video_id` | bigint | Video CPT post ID |
| `email` | varchar | Captured email (unique per video) |
| `name` | varchar | Optional name |
| `consent_given` | tinyint | Whether consent was checked |
| `consent_text` | text | The consent text shown |
| `ip_address` | varchar | Submitter IP |
| `source` | varchar | Capture source identifier |
| `created_at` | datetime | Submission timestamp |

**Unique constraint:** `(video_id, email)` -- a user only needs to submit once per video.

---

## Cookie Behavior

After successful email submission:

1. A cookie named `ms_email_gate_{video_id}` is set.
2. Duration is configurable (default: 7 days).
3. When the cookie exists, the `EmailGate` filter passes through without requiring re-submission.
4. Cookie is `HttpOnly` and `SameSite=Lax`.

---

## REST Endpoint

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/email-gate/submit` | public | Submit email for gated video |

**Request body:**

```json
{
  "video_id": 42,
  "email": "user@example.com",
  "name": "Jane Doe",
  "consent": true
}
```

**Response (200):**

```json
{
  "success": true,
  "message": "Access granted"
}
```

---

## GDPR Compliance

- Email captures are included in the Pro GDPR data exporter.
- The GDPR eraser deletes all email captures for the requesting user.
- Auto-retention: captures older than `ms_email_retention_months` are deleted daily by the `ms_email_capture_retention` cron job.

---

## JavaScript Events

| Event | When | Data |
|-------|------|------|
| `mediashield:access-denied` | Email gate required | `{ reason: 'email_gate_required', video_id }` |
| `mediashield:email-gate-passed` | Email submitted successfully | `{ video_id, email }` |

Listen for these to integrate with other frontend logic:

```javascript
document.addEventListener( 'mediashield:email-gate-passed', function( e ) {
    console.log( 'Email captured for video:', e.detail.video_id );
    // Track conversion in analytics
});
```
