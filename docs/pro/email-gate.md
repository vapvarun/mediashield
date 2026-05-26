# MediaShield Pro -- Email Gate

Email Gate turns any protected video into a lead-capture moment. When a logged-out visitor lands on a gated video, MediaShield shows a small email form before the player will start. They enter their email, agree to your privacy policy, and the video unlocks -- and you keep the captured email forever, exportable on demand. It's the easiest way to grow your list off the back of the content you already protect.

This guide walks through what visitors see, how to turn the gate on (site-wide or per-video), what gets captured, the consent and GDPR story, and how to forward captures into a CRM or email tool.

For the broader Pro setup, see [`getting-started.md`](getting-started.md).

---

## How a visitor experiences it

Picture a free preview lesson on your course site. A visitor clicks through from Google. They land on the page with the protected video embedded.

1. Instead of the player loading right away, a small dark overlay slides over the video. It has one email field, your consent text with a checkbox, and a **Watch Video** button.
2. The visitor types their email, ticks the consent box, and clicks the button.
3. The overlay disappears, the video starts playing, and they enjoy the lesson.
4. MediaShield drops a cookie on their browser (default: **7 days**, configurable). For the next week they can come back to any of your gated videos and the player loads instantly -- no second form, no friction.

Logged-in users never see the gate. Email Gate is purely for the anonymous visitor who hasn't told you who they are yet.

If the visitor clears cookies, switches to a new browser, or comes back after the cookie expires, they'll see the form again. The capture you already have is unaffected -- it stays in your list either way.

---

## Turning it on -- two scopes

You have two ways to gate videos. Use them together or separately, whichever fits your workflow.

### Site-wide (Settings → Email Gate)

Open **MediaShield → Settings** and find the **Email Gate** section. Switch on **Enable Email Gate** to apply the gate to every protected video on your site for anonymous visitors. This is the right choice when most of your video catalogue should require an email -- a single switch covers the whole library.

The same panel holds the supporting controls described below: cookie duration, webhook URL, retention period, and the consent text shown on the form. See [`getting-started.md`](getting-started.md) for the full options tour.

### Per video (new in 1.1.0)

Sometimes you only want one or two videos to ask for an email -- a free preview lesson, a lead magnet -- while the rest of your library is reserved for logged-in members.

When you edit a video at **Videos → Edit Video**, look for the **Email Gate** panel in the right sidebar. It holds a single checkbox:

> Require an email address before guests can watch this video.

Tick it, click **Update**, and the gate is active for *that single video* -- regardless of the site-wide setting. Untick it later to turn the gate off again for that video.

The two scopes layer cleanly: if site-wide Email Gate is ON, every video shows the gate. If it's OFF, only the videos you've ticked individually show the gate. Either way, logged-in visitors continue to walk straight through.

---

## What gets captured

Every time a visitor submits the form, MediaShield records a row you can review later. Each capture holds:

- **The email address** they entered.
- **The video** they were watching (so you know which lesson hooked them).
- **The timestamp** of the capture, in your site's timezone.
- **The visitor's IP address**, for spam and abuse review.
- **Whether they ticked the consent box** along with the exact consent wording shown to them.

Captures are stored in your WordPress database and shown in the Pro admin. To download them, head to the **Export** page in the MediaShield admin and use the **CSV Export** option for email captures -- you'll get a spreadsheet with every column above. Pair that with the data-export tools you already use for your audience and you can be onboarding new leads within minutes.

---

## Consent and your Privacy Policy

The submission form includes a consent checkbox that reads, by default, that the visitor agrees to receive updates and that their email may be stored according to your privacy policy. Submissions without the checkbox ticked are rejected -- consent is required, not optional. The exact wording is editable in the Email Gate settings panel so you can match your existing brand voice and legal language.

Make sure you've set your site's **Privacy Policy** page in **WordPress → Settings → Privacy** so the consent text can link to it. Without that link, the consent message has nowhere meaningful to point a visitor.

If you're capturing emails in a jurisdiction with explicit-consent requirements (EU, UK, California), this combination -- the consent checkbox plus a working Privacy Policy URL -- is the standard pattern reviewers expect.

---

## Webhook integration (optional)

If you want captured emails to land in your CRM, email-marketing tool, or automation platform without you exporting CSV files, paste a **webhook URL** into the Email Gate settings. From that point on, MediaShield will POST every new capture to the URL you provided, in real time, the moment the visitor submits the form.

Common destinations:

- **Mailchimp / ConvertKit / ActiveCampaign** via a Zapier or Make webhook
- **Klaviyo** via their incoming-webhook flow
- **HubSpot / Salesforce / Pipedrive** via a Zap or a custom connector
- **Your own CRM** at any HTTPS endpoint that can receive JSON

There's a **Send Test Webhook** button in the settings panel so you can verify the URL is reachable before you trust real traffic to it.

The webhook payload includes the email, the video ID, the video title, and the timestamp -- enough for most automation tools to drop the lead into the right list, tagged by which video they came from. If you need consent metadata or IP in your downstream system, those columns are always available on the CSV export.

---

## GDPR and privacy

Captured emails are personal data, and MediaShield treats them as such.

- **Export requests** -- when a user invokes their right to data export through **Tools → Export Personal Data**, MediaShield's Pro exporter automatically surfaces every email-gate capture associated with their email address. You don't have to write a single query.
- **Erase requests** -- when a user invokes their right to be forgotten through **Tools → Erase Personal Data**, the same captures are erased from your database. The WordPress request log records what was removed.
- **Retention period** -- captures don't sit forever. Set how long they're kept in the **Email Gate retention** setting (in months). MediaShield runs a daily background task that removes captures older than the limit. The default is 12 months; tighten or relax it to match your privacy policy.

If a regulator or an individual asks "what email-gate data do you hold on me?" the WordPress privacy export gives them a complete, accurate answer in one click.

---

## Frequently asked

**Does Email Gate replace login?**
No. Email Gate is an extra gate for *anonymous* visitors. Logged-in users walk straight through. If your video library is members-only, keep using your regular login -- Email Gate is for the free previews or lead-magnet videos that sit outside the paywall.

**Can I use Email Gate without connecting a CRM or email tool?**
Yes. Captures are saved in your WordPress database whether or not a webhook is configured. You can download them as CSV any time from the **Export** page. The webhook is purely a convenience for real-time syncing.

**What if a visitor clears their cookies?**
They'll see the form again next time they visit. Their original capture record stays in your list -- you don't lose the lead, they just have to re-confirm before watching again. If you want to extend the time between re-prompts, raise the **Cookie Duration** setting (default 7 days).

**Can I gate only some videos?**
Yes -- that's exactly what the per-video toggle (introduced in 1.1.0, described above) is for. Edit any video, tick the **Email Gate** checkbox in the sidebar, save, and that one video is gated without affecting any others.

**Will the gate slow down my page?**
No. The email form is small and ships with the player assets you're already loading. It only renders for anonymous visitors on gated videos, and not at all for logged-in users.

**Can a determined visitor bypass it?**
The gate sits in front of the player. Like every soft gate (newsletter pop-ups, paywalls, age-gates), it's designed for the honest visitor who wants to watch the video -- not as a security wall. For real access control, combine Email Gate with MediaShield's protection layer, login requirements, and (in Pro) DRM. The right combination is described in [`getting-started.md`](getting-started.md).

---

## Where to next

- [Pro: Getting Started](getting-started.md) -- the full Pro walkthrough end-to-end
- [Pro: Analytics](analytics.md) -- how to see which gated videos are converting captures into engagement
- [Pro: Platform Connections](platform-connections.md) -- connect Bunny, YouTube, Vimeo, or Wistia and gate their content too
