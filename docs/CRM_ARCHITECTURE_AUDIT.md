# CRM Architecture Audit

_Travel OTA platform — pre-implementation audit for the Travel CRM + Marketing Automation initiative._
_Generated 2026-09-19. No destructive changes were made during this audit._

---

## 0. Executive summary

The platform **already contains a lightweight CRM** (leads, follow-ups, quotations), a **B2B agent module**, and an **affiliate module**, plus a mature **booking/payment/PDF/notification** stack. The requested system is a large superset: unified contacts, configurable pipelines, attribution, WhatsApp Cloud API + inbox + chatbot, Meta/Google Ads OAuth + webhooks + sync + conversion attribution, an automation engine, campaigns, contact groups, custom fields, and analytics.

**Guiding decision:** _extend_ the existing CRM tables and notification/queue/webhook infrastructure; do **not** replace booking, payment, auth, RBAC, PDF, or the existing lead/quotation tables. Booking/payment remain authoritative; CRM references them.

---

## 1. Existing CRM-related features

| Area | Exists today | Location |
|---|---|---|
| Leads | Yes (basic) | `crm_leads`, `Admin\CrmController`, `admin.crm.*` views |
| Follow-ups | Yes | `crm_follow_ups`, `CrmController@storeFollowUp` |
| Quotations | Yes (simple) | `crm_quotations`, `CrmQuotationMail`, `admin/crm/quotation_pdf.blade.php` |
| B2B agents + wallet/credit | Yes | `agents`, `agent_transactions`, `Admin\AgentController` |
| Affiliates | Yes | `affiliates`, `affiliate_clicks`, `affiliate_commissions`, `Admin\AffiliateController` |
| Contacts (unified profile) | **No** | — (only `ContactMessage` = contact-form inbox) |
| Companies | **No** | — |
| Deals | **No** (leads double as deals) | — |
| Configurable pipelines / stages | **No** (hardcoded status enum) | — |
| Kanban | **No** | — |
| Lead scoring | **No** | — |
| Attribution (UTM / gclid / fbclid, first/last touch) | **No** | — |
| WhatsApp Cloud API | **No** (only a generic SMS/WhatsApp "webhook" gateway driver) | `SmsService` |
| WhatsApp inbox / conversations | **No** | — |
| Chatbot | **No** | — |
| Campaigns (bulk WA/email/SMS) | **No** | — |
| Contact groups / segments | **No** | — |
| Automation engine | **No** (only fixed booking-confirmation triggers) | `BookingService`, `NotificationService` |
| Meta Ads / Google Ads integration | **No** | — |
| Custom fields | **No** | — |
| Global search | Partial (per-section search only) | — |

## 2. Existing tables (CRM-relevant)

- `crm_leads` — name, email, phone, destination, `product_type`(enum), budget, travellers_count, travel_date, `source`(enum: website/referral/phone/social/campaign), `status`(enum: new/contacted/quotation_sent/negotiating/converted/lost), `assigned_to`→admins, notes, `tags`(json), `converted_booking_id`→bookings. Indexes: status, assigned_to, created_at.
- `crm_follow_ups` — lead_id, admin_id, note, scheduled_at, is_completed, completed_at.
- `crm_quotations` — lead_id, quotation_number(unique), title, package_id, `items`(json), subtotal/tax_amount/total_amount, valid_until, `status`(enum: draft/sent/accepted/rejected/expired).
- `agents`, `agent_transactions` — B2B wallet/credit/commission ledger, linked to `bookings`.
- `affiliates`, `affiliate_clicks`, `affiliate_commissions` — referral tracking, linked to `bookings`.
- Supporting: `bookings`, `booking_hotels`, `booking_package_flights`, `payments`, `refunds`, `notification_templates` (channel enum includes `whatsapp`), `user_notifications`, `webhook_events`, `activity_logs`, `admins`, `roles`, `permissions`.

## 3. Existing models & relationships

- `CrmLead` (belongsTo Admin `assignee`, hasMany `followUps`, hasMany `quotations`), `CrmFollowUp`, `CrmQuotation` (belongsTo lead, package).
- `Booking` is the polymorphic spine (`product_type` flight|hotel|cab|package) with `packageBooking`, `bookingHotels`, `packageFlights`, `payments`, `refunds`. `crm_leads.converted_booking_id` already links a lead → booking.
- `Admin` is the staff model (RBAC via `roles`/`permissions`, `admin.permission:*` middleware). Customers are `User`.
- `NotificationTemplate` (key/channel/subject/body/variables), `UserNotification` (in-app), `WebhookEvent` (provider/event_type/event_id unique + processed_at → idempotency).

## 4. Booking lifecycle (authoritative — must not change)

`BookingService::create` (payment_pending) → `PaymentManager::createOrder` → gateway → `handleCallback`/webhook (signature verified, `lockForUpdate`, idempotent) → `BookingService::handlePaymentSuccess` → `confirmWithSupplier` → status `confirmed` **or** `payment_success_booking_failed`. **Payment success alone never confirms a booking.** CRM conversion must key off `status === 'confirmed'`, never off payment capture.

## 5. Payment lifecycle

`payments` (gateway/order/payment/signature, status created→captured), Razorpay + Mock gateways via `PaymentManager`. Webhook dedup via `WebhookEvent`. Refunds via `Refund` + `RefundController`. Reconciliation command `bookings:reconcile-failed` handles `payment_success_booking_failed`.

## 6. Notification architecture

`NotificationService`: DB-template-driven email (`Mail::raw` or `SendNotificationJob` when a real queue is configured) + SMS (`SmsService`) + in-app (`UserNotification`). Rich Mailables (`BookingConfirmationMail`, `CrmQuotationMail`) bypass templates. **WhatsApp channel is declared in `notification_templates` but has no real sender.** `SmsService` has a "webhook" driver that POSTs to a generic SMS/WhatsApp gateway — this is **not** the official WhatsApp Cloud API.

## 7. Queue architecture

Jobs exist (`SendNotificationJob`); `NotificationService` dispatches to queue only when `queue.default !== 'sync'`. **Action item:** confirm queue driver in `config/queue.php`/`.env`. Redis is recommended for the CRM (dedicated queues: crm, whatsapp, email, sms, webhooks, campaigns, marketing, documents, notifications).

## 8. Staff / permission architecture

`admins` + `roles` + `permissions` + pivots; `admin.auth` and `admin.permission:<perm>` middleware guard every admin route (`routes/admin.php`). New CRM permissions slot into this system — **no new auth/RBAC system needed.**

## 9. API / webhook architecture

`routes/api.php` → `api_v1.php`. Existing inbound webhook: Razorpay (`/webhooks/razorpay`) with `WebhookEvent` idempotency. **No** marketing/WhatsApp webhooks yet. `WebhookEvent` (provider+event_id unique) is the reusable idempotency primitive for Meta/Google/WhatsApp inbound.

## 10. Admin UI structure

Blade + Tailwind v4 + Alpine 3 + Vite. `layouts/admin.blade.php` shell + sidebar, `admin-card`/`admin-table`/`status-pill`/`input`/`btn-*` classes, `@stack('scripts')`. Reusable components under `resources/views/components/admin/` (incl. the new `x-admin.filters` and `x-admin.sort-header`). CRM UI must reuse this shell.

## 11. What can be REUSED

Auth & RBAC (admins/roles/permissions), admin layout + components, `crm_leads`/`crm_follow_ups`/`crm_quotations` (extend, don't replace), `WebhookEvent` (idempotency), `NotificationService` + `NotificationTemplate` + `SmsService` (extend with a real WhatsApp Cloud channel), `PdfDocumentService` + dompdf (quotation PDFs), `ActivityLogger` (audit), queue/jobs pattern, `Booking`/`Payment` as authoritative references, `BookingService::handlePaymentSuccess` as the conversion hook, `agents`/`affiliates` (companies/referrals foundation).

## 12. What needs to be CREATED

`contacts` (unified, dedup by normalized phone/email) + backfill link from `crm_leads`; extend `crm_leads` (uuid, lead_number, contact_id, company_id, pipeline/stage, score, attribution columns, external_* ids, follow-up timestamps); `companies`; `pipelines` + `pipeline_stages`; `crm_activities` (timeline); `crm_tasks`; `tags` (polymorphic) + `taggables`; `lead_sources`; `custom_fields` + `custom_field_values`; `contact_groups` + membership; WhatsApp: `whatsapp_conversations`, `whatsapp_messages` (outbox), `whatsapp_templates`; `chatbots`/`flows`/`nodes`; `campaigns` + `campaign_recipients`; `automations` (trigger/condition/action) + `automation_runs`; marketing: `marketing_connections`, `marketing_accounts`, `marketing_campaigns/adsets/ads/ad_groups`, `marketing_lead_forms`, `marketing_spend`, `marketing_conversions`, `marketing_sync_logs`; generic `webhook_events` reuse for meta/google/whatsapp. Services: Lead/Contact/ContactMerge/LeadAssignment/LeadScoring/Quotation/Conversation/WhatsAppCloud/WhatsAppTemplate/Campaign/Automation/Attribution/MetaAds/GoogleAds/MarketingSync/AdConversion. Events/Listeners, Jobs (per §QUEUE), Policies, Form Requests, Blade UI, docs.

## 13. Conflicts

- **Two "quotation" notions:** `crm_quotations` (simple) vs the package hotel/flight snapshot. Recommendation: evolve `crm_quotations` into the richer quotation (line items already JSON) rather than a new table.
- **"Contact" naming collision:** `ContactMessage` (contact-form inbox) ≠ new `contacts`. Keep names distinct.
- **Lead vs Deal:** existing `crm_leads` doubles as a deal. Recommendation: keep Lead as the entity; add pipeline/stage fields rather than a separate `deals` table initially (extensible later).
- **Source as enum vs `lead_sources` table:** requested design wants a configurable `lead_sources` table + `source_id`. Migrating the enum → FK is a backward-compatible additive change (keep enum, add nullable `source_id`).

## 14. Risks

- Scope: 20 phases ≈ enterprise, multi-month. Attempting it in one pass would be unsafe and untestable.
- External integrations (Meta/Google/WhatsApp Cloud) require **live credentials, app review, and approved templates** — cannot be truly functional without them; only scaffolding + sandbox is possible otherwise.
- Migrations touching `crm_leads` must be additive (nullable columns, backfill) to avoid breaking the existing CRM UI.
- Bulk campaigns must be queue/chunk-based to avoid memory blowups.

## 15. Performance concerns

Dashboard aggregates must be cached (not per-request). Campaign recipient resolution must use `chunkById()`/`cursor()`. Add indexes on phone/email/lead_number/quotation_number/source_id/campaign_id/assigned_user_id/status/stage/next_follow_up_at/created_at/external_* /utm_*. Avoid N+1 in inbox/timeline (eager load). Confirm Redis for queues/cache/locks.

## 16. Security concerns

Encrypt marketing/WhatsApp tokens at rest; never log secrets. Verify Meta/Google/WhatsApp webhook signatures + idempotency. Policies/Gates for lead/conversation ownership (prevent IDOR). Form Requests + `$fillable` discipline (mass-assignment). Respect opt-in/opt-out before any marketing send. Do not expose sequential quotation IDs — use signed/secret tokens for public quote URLs.

## 17. Recommended phasing (incremental, test-gated)

1. **Phase 2 — CRM core**: `contacts` + dedup, extend `crm_leads` (contact_id, pipeline/stage/score/attribution), `pipelines`/`stages`, `crm_activities`, `crm_tasks`, `tags`, `lead_sources`, website capture + attribution. (No external APIs — fully testable now.)
2. **Phase 3 — Customer 360** timeline.
3. **Phase 4 — Quotation upgrade** (extend `crm_quotations`, secure public URL, PDF via existing engine, tracking).
4. **Phase 5–6 — WhatsApp Cloud API + Inbox** (needs credentials).
5. **Phase 7–9 — Meta/Google Ads + attribution** (needs credentials).
6. **Phase 10–13 — Automation, chatbot, campaigns, groups.**
7. **Phase 14–20 — analytics, revenue attribution, reports, RBAC/audit, performance, tests, prod-readiness.**

Each phase: migrate → test → verify booking/payment flows → verify routes/permissions → then proceed.

---

### Open questions (blockers for the external-integration phases)

1. **Credentials**: Do you have (or can you provision) WhatsApp Cloud API (Meta WABA + phone number + token + approved templates), Meta Ads app (App ID/secret, Lead Ads webhook), and Google Ads (OAuth client + developer token)? Without these, phases 5–9 can only be scaffolded, not made live.
2. **Queue driver**: Is Redis available in this environment (needed for the queue/cache/lock model the spec requires)?
3. **Session scope**: This is a 20-phase program. Which slice should be built and verified first?
