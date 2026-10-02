# WhatsApp Cloud API — Setup

Two-way WhatsApp messaging (CRM Inbox + notifications) uses the Meta **WhatsApp Cloud API**.

> **Credentials now live in the database, managed from the admin panel.**
> **Admin → System → Settings → WhatsApp Settings** edits the access token, phone number ID, WABA ID, app secret, verify token, template IDs, document-attach flags and the AI-assistant options — all stored **encrypted** in the `integration_settings` table and applied immediately (no `php artisan config:clear` needed). AI provider keys (OpenAI / Anthropic / Gemini) are managed under **Admin → System → AI Providers**.
>
> The `.env` keys below remain a **fallback**: any field left blank in the admin panel falls back to its `.env`/`config` value, so existing deployments keep working. Set credentials in whichever place you prefer — the database wins when a value is present.

## 1. Required `.env` keys

```dotenv
WHATSAPP_ENABLED=true
WHATSAPP_API_VERSION=v21.0
WHATSAPP_PHONE_NUMBER_ID=          # WhatsApp phone number ID (from the Meta app → WhatsApp → API Setup)
WHATSAPP_WABA_ID=                  # WhatsApp Business Account ID
WHATSAPP_ACCESS_TOKEN=             # Permanent System User token (recommended) or temporary token
WHATSAPP_VERIFY_TOKEN=             # Any random string you choose; used for the webhook handshake
WHATSAPP_APP_SECRET=               # Meta App → Settings → Basic → App Secret (verifies X-Hub-Signature-256)
WHATSAPP_DEFAULT_TEMPLATE_LANG=en_US
```

After editing `.env`, run `php artisan config:clear`.

> If `WHATSAPP_APP_SECRET` is empty, inbound signature verification is skipped (development only). Always set it in production.

## 2. Webhook configuration (Meta App → WhatsApp → Configuration)

- **Callback URL:** `https://YOUR_DOMAIN/webhooks/whatsapp`
- **Verify token:** the same value as `WHATSAPP_VERIFY_TOKEN`
- **Subscribe to fields:** `messages`

Meta will call `GET /webhooks/whatsapp` once to verify (the app echoes `hub.challenge` when the token matches), then delivers events to `POST /webhooks/whatsapp`.

Both webhook routes are exempt from CSRF (`bootstrap/app.php` → `validateCsrfTokens(except: ['webhooks/*'])`) and are authenticated by the HMAC signature instead.

## 3. The 24-hour window

WhatsApp only allows free-form messages within 24 hours of the customer's last inbound message. The Inbox tracks this per conversation (`window_expires_at`):

- **Window open:** the "Reply" composer sends a free-form text.
- **Window closed:** only an **approved template** may be sent (use the "Template" tab). Sending a template does not itself open the window — only a customer reply does.

Approved templates are created and approved in the Meta Business Manager. Enter the exact template name and language code (e.g. `en_US`) in the Inbox; body variables map to `params[]` in order.

## 4. Transactional templates (automatic notifications)

Booking confirmations can fire an approved WhatsApp template automatically when a booking reaches status `confirmed` (alongside the existing email + SMS). Configure the template name in `.env`:

```dotenv
WHATSAPP_TEMPLATE_BOOKING_CONFIRMED=booking_confirmed   # your approved template name; blank = skip WhatsApp
```

Transactional templates are configured per event; if a key is blank, WhatsApp is skipped for that event and email/SMS still fire. Sending is also skipped when `WHATSAPP_ENABLED=false` or the customer has no phone. Create each template in Meta Business Manager with the body variables in the order below.

```dotenv
WHATSAPP_TEMPLATE_BOOKING_CONFIRMED=
WHATSAPP_TEMPLATE_BOOKING_CANCELLED=
WHATSAPP_TEMPLATE_QUOTATION_SENT=
```

**`booking_confirmed`** — fired when a booking reaches status `confirmed`:

1. customer name
2. booking reference
3. destination
4. travel date
5. amount (e.g. `₹57,750.00`)

**`booking_cancelled`** — fired when a booking is cancelled/refunded:

1. customer name
2. booking reference
3. refund amount
4. refund processing days (e.g. `5-7`)

**`quotation_sent`** — fired when a quotation is generated/sent to a lead:

1. lead name
2. quotation number
3. total amount
4. public quote link (the customer's accept/decline URL)

All events route through `WhatsAppService::notifyEvent('<event>', $phone, [$params], $name)`, which looks up the template name from `services.whatsapp.templates.<event>` — add new events there.

## 5. Where it lives in the app

- Transport: `app/Services/WhatsApp/WhatsAppCloudClient.php`
- Orchestration + persistence: `app/Services/WhatsApp/WhatsAppService.php`
- Webhook: `app/Http/Controllers/Site/WhatsAppWebhookController.php`
- Inbox UI: **Admin → B2B & CRM → WhatsApp Inbox** (`admin.whatsapp.index`, permission `manage_crm`)
- Data: `whatsapp_conversations`, `whatsapp_messages` (linked to CRM `contacts`)

Inbound messages create/annotate a CRM contact automatically (deduped by normalized phone) and are written to the contact/lead activity timeline.

## 6. Automation suite (templates, groups, campaigns, chatbot)

Under **Admin → B2B & CRM**:

- **WA Templates** — "Sync from Meta" pulls approved templates from your WABA into the app (name, language, category, status, body-variable count). Campaigns and auto-replies pick from these.
- **Contact Groups** — static (manually added members) or dynamic (auto-membership by lifecycle stage, source, tag, WhatsApp opt-in). Used as campaign audiences.
- **WA Campaigns** — pick a template + group, provide body variables, then **Send now** or **Schedule**. Recipients are limited to WhatsApp-reachable, opted-in contacts. Sends run on the `whatsapp` queue with ~1s spacing; per-recipient status (sent/delivered/read/failed) updates from status webhooks.
- **WA Auto-replies** — keyword chatbot rules (exact / contains / starts-with), checked in priority order on each inbound message. A rule replies with text (inside the 24h window) or a template (anytime); a default fallback rule catches unmatched messages; a handoff rule pauses the bot for that conversation so a human can take over.

### Sending documents (quotation / invoice / itinerary / voucher PDFs)

PDFs are uploaded to WhatsApp's media endpoint (no public file URL needed — works on localhost/ngrok) and sent as document messages.

**From the Inbox (manual):** open a conversation → composer → **Document** tab → pick a quotation (this contact's quotations) or enter a booking reference for its invoice/itinerary/voucher → optional caption → Send. Requires an open 24-hour window (free-form document rule).

**Automatically with a template (outside the window):** a template can carry the PDF in a **document header**. To enable:

1. In Meta, create the template **with a Document header** (in addition to the body).
2. Turn on the matching flag in `.env`:
   ```dotenv
   WHATSAPP_ATTACH_QUOTATION_PDF=true      # attaches the quote PDF to quotation_sent
   WHATSAPP_ATTACH_BOOKING_INVOICE=true    # attaches the invoice to booking_confirmed
   ```
   Then `php artisan config:clear`.

> Only enable a flag once its template actually has a document header — otherwise Meta rejects the header parameter and the send fails (email/SMS still go out). Leave them `false` for text-only templates.

### Requirements for automation

- **Queue worker** must be running for campaigns to send: `php artisan queue:work --queue=whatsapp,default`
- **Scheduler** must be running for scheduled campaigns to dispatch: ensure `php artisan schedule:run` runs each minute (cron). The `whatsapp:dispatch-campaigns` command picks up due campaigns.
- **Opt-in / compliance**: only contacts with `whatsapp_opt_in = true` receive campaign messages. Marketing templates are subject to Meta's category rules and quality rating — respect user opt-outs.

## 7. AI Travel Assistant (customer self-service over WhatsApp)

An optional LLM layer that answers customer questions about their **own** bookings, payments, documents and new trip inquiries — in natural language, including Hindi/Hinglish. It is an enhancement on top of the existing pipeline: the deterministic keyword auto-replies always run first and win; the assistant only handles a message when **no** keyword rule matched **and** the bot is not paused (human handoff). It is wired in `WhatsAppWebhookController` after `AutoReplyService::handle()` returns `false`, wrapped in try/catch so it can never break the webhook.

### How it works

```
Inbound WhatsApp text
  → recordInbound (persist + open 24h window + CRM automation)   [unchanged]
  → AutoReplyService::handle()  ── matched a keyword rule? ──► reply, stop   [unchanged]
        │ no rule matched, bot not paused, assistant enabled
        ▼
  TravelAssistantService
        → CustomerResolver: map WhatsApp number → verified customer identity
        → AI provider (chat + tool calling) chooses backend tools
        → CustomerTools (ownership-scoped) execute against existing services
        → reply text + any PDFs sent via the existing WhatsAppService
```

### Security model (important)

- **Authorization is enforced in the backend, never by the AI.** `CustomerResolver` is the single choke point: it resolves the WhatsApp number to a verified `User`/`Contact` and returns the exact set of booking IDs the conversation may access. Every data tool scopes to that set.
- **Verification** (config `WHATSAPP_AI_REQUIRE_VERIFICATION=true`): a number is auto-verified when it matches a registered user's phone or a booking's own contact phone. Otherwise the customer must supply a **booking reference tied to their own number** — knowing a stranger's booking ID is *not* enough, because the reference must match the sender's WhatsApp number.
- **No fabrication**: the assistant answers booking/payment/document questions only from tool output. Stored booking info is clearly distinguished from live provider status (no live flight/cab status is wired, so it is labelled unavailable).
- **No silent money movement / cancellation**: cancellations and modifications are *requests* that require explicit customer confirmation and are reviewed by staff. The assistant never claims a booking is cancelled or changed.

### Backend tools (all reuse existing services)

`get_my_profile`, `get_my_bookings`, `get_my_upcoming_bookings`, `get_my_past_bookings`, `get_booking_details`, `get_booking_hotel`, `get_booking_flight`, `get_booking_cab`, `get_booking_payment_status`, `get_booking_invoice`, `get_booking_receipt`, `get_booking_itinerary`, `get_booking_documents`, `get_cancellation_policy`, `request_booking_cancellation`, `request_booking_modification`, `get_payment_link`, `request_human_agent`, `search_packages`, plus the special `verify_identity`. Documents are delivered as PDFs through the existing `WhatsAppService::sendDocumentPdf` (uses `PdfDocumentService`); payment links reuse the existing `checkout.show` route; cancellations reuse `BookingService::requestCancellation`.

### Enabling

1. Set an AI provider key: `OPENAI_API_KEY` (or `ANTHROPIC_API_KEY` / `GEMINI_API_KEY`) and `AI_DEFAULT_PROVIDER` / `AI_DEFAULT_MODEL` (a tool-calling capable model, e.g. `gpt-4o`).
2. Set `WHATSAPP_AI_ASSISTANT_ENABLED=true` (optionally override `WHATSAPP_AI_PROVIDER` / `WHATSAPP_AI_MODEL`).
3. `php artisan migrate` (adds verification/context columns to `whatsapp_conversations`), then `php artisan config:clear`.
4. Because free-form replies need the open 24-hour window, the assistant only replies inside that window (Meta rule). Outside it, transactional templates still apply.

### Config keys (`config/services.php` → `whatsapp.ai_assistant`)

| env | default | meaning |
|-----|---------|---------|
| `WHATSAPP_AI_ASSISTANT_ENABLED` | `false` | master switch |
| `WHATSAPP_AI_PROVIDER` | (shared `services.ai`) | override provider for the assistant |
| `WHATSAPP_AI_MODEL` | (shared `services.ai`) | override model |
| `WHATSAPP_AI_MAX_TOOL_ITERATIONS` | `5` | max tool round-trips per message |
| `WHATSAPP_AI_HISTORY_LIMIT` | `12` | prior messages fed for context |
| `WHATSAPP_AI_REQUIRE_VERIFICATION` | `true` | require identity verification before private data |

When no provider key is set, the assistant falls back to `NullAiProvider` and simply declines (returns to existing behavior) — it never fabricates a reply.
