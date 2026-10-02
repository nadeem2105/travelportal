# AI Marketing & Advertising Platform — Architecture

This module adds an advertising + AI marketing layer **on top of** the existing OTA/CRM. It never becomes authoritative for bookings, payments, or inventory — those systems remain the source of truth. Marketing only *references* and *consumes* their data/events.

## Guiding rules

- **No faked API capability.** Every ad-platform operation maps to an official API call. When something isn't connected/enabled/supported, the code throws `AdApiUnavailableException` and the UI shows "Not available through current API." Never simulate success.
- **No accidental spend.** Campaigns are created locally as `draft` and remotely `paused`. Activation (spend) is a separate, explicitly-authorized, permission-gated action.
- **AI never fabricates facts.** The AI layer is instructed never to invent prices, availability, discounts, or metrics; missing data is reported as unavailable.
- **Secrets in `.env` only**, encrypted at rest where stored (`marketing_connections.credentials` uses `encrypted:array`). Tokens are never exposed in UI/logs.
- **Queue-driven.** Sync, AI generation, and video work run on queues, never in HTTP requests.

## Data model (migrations `2026_09_20_1300xx`)

- `marketing_connections` — OAuth connections (encrypted credentials, status, token expiry).
- `marketing_accounts` — ad accounts discovered under a connection.
- `marketing_campaigns` → `marketing_campaign_groups` (ad groups / ad sets) → `marketing_ads`; plus `marketing_creatives`, `marketing_assets`, `marketing_audiences`, `marketing_keywords`.
- `marketing_daily_metrics` — one aggregated row per entity/day for fast reporting.
- `marketing_conversions` + `marketing_attributions` — funnel events and campaign→revenue links (reference CRM leads/quotations + existing bookings; not authoritative).
- AI/optimization: `marketing_recommendations`, `marketing_optimization_rules` + `_runs`, `marketing_experiments` + `_variants`, `marketing_ai_generations`, `marketing_ai_audits`.
- Ops: `marketing_sync_runs`, `marketing_alerts`, `marketing_campaign_templates`, `marketing_campaign_changes`. Webhook idempotency reuses the existing `webhook_events` table.

## Services (`app/Services/Marketing`)

- `AdPlatformInterface` — common contract; implemented by `GoogleAdsService` and `MetaAdsService`. OAuth is real (HTTP); campaign writes are gated until the full provider API clients are wired.
- `AdvertisingAccountService` — resolves the platform adapter, manages connections, discovers accounts.
- `CampaignService` — local draft lifecycle, standardized naming, UTM generation, safety-gated publish/activate/pause/budget, change logging.
- `CampaignSafetyService` — pre-publish validation + global daily-spend and budget-swing limits.
- AI: `Ai/AiProviderInterface` + `AiProviderManager` (resolves OpenAI/Anthropic/Gemini adapters or `NullAiProvider`). Business logic depends only on the interface.

## Config & env

`config/services.php` → `google_ads`, `meta_ads`, `ai`. Env keys (all default off/empty): `GOOGLE_ADS_*`, `META_ADS_* / META_APP_*`, `AI_*`, `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`. OAuth redirect URIs default to `${APP_URL}/admin/marketing/{google|meta}/callback`.

## RBAC

Permissions (seeded): `marketing.view`, `marketing.accounts`, `marketing.campaigns.create`, `marketing.campaigns.publish`, `marketing.campaigns.pause`, `marketing.optimize`, `marketing.ai`, `marketing.assets`, `marketing.analytics`, `marketing.settings`. Assign to Marketing Manager/Executive roles.

## Admin module

Admin → **Advertising** group: Marketing Dashboard (`marketing.overview`) + Ad Accounts (`marketing.accounts`, OAuth connect/callback/sync/disconnect). More sections (Campaign Builder, AI Creator, Creative Studio, Analytics, Recommendations, Optimization Center, Copilot) are added in later phases on this foundation.

## Relationship to existing lead ingestion

The Meta/Google **lead** webhooks (`/webhooks/meta`, `/webhooks/google-leads`) and the CRM attribution already built continue to work independently. This module adds outbound campaign management + AI on top, and will link ingested leads to `marketing_campaigns` via attribution once campaigns carry external IDs.

## Status & next phases

Foundation (Phase 2/3) complete: data layer, config, RBAC, service + AI provider skeletons, admin shell. Live Google Ads / Meta campaign writes require finishing the provider API clients (Google Ads API SDK + developer token; Meta Graph Marketing calls) — scaffolded and gated. Then: campaign wizard, AI campaign creator, creative studio (image/video providers), metrics sync jobs, analytics, recommendations/optimization engine, copilot, alerts, reports, tests.
