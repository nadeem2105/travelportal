# AI Ad Creative Studio — Implementation Plan (mapped to the real codebase)

**Date:** 2026-09-21
**Rule:** Enhancement only. Reuse existing marketing module, AI layer, media/storage, RBAC, queue, admin design system. No duplication, no breaking WhatsApp/booking/CRM.

---

## A. What already exists (reuse, don't rebuild)

| Area | Status | Reuse |
|---|---|---|
| Marketing module | Campaigns, AI ad-copy, provider settings, metrics, attribution, alerts, experiments (schema) | Extend, don't duplicate |
| `marketing_creatives`, `marketing_assets` tables + models | **Orphaned scaffold** (no controller/route/UI, loose `asset_id`/`creative_id`) | **Adopt as the Studio's core tables** |
| `marketing_ai_generations` | Working (logs AI calls, has `generation_type` incl. image/video, `asset_id`, cost/tokens) | Log all Studio generations here |
| AI provider layer (`app/Services/Marketing/Ai`) | **TEXT/CHAT ONLY** — OpenAI/Anthropic/Gemini/Groq/OpenRouter + Manager + Null | Reuse for copy; **extend for images** |
| AI image/video | **None** — but `services.ai.enable_image`/`enable_video` flags + enum values already exist | Greenfield, provider-agnostic |
| Credentials | `IntegrationSetting` (`encrypted:array`) + `IntegrationSettings::applyToConfig()` overlay onto `config('services.ai')`; admin at `admin.ai-settings.*` | Add image-provider keys here |
| Media/storage | `Media` model + `admin.media.upload` + `x-admin.image-upload` + `img()` helper; disks: `public` (active), `s3`/R2 (configured, unused) | Reuse Media + upload; enable R2 later |
| Queue | `database` driver; jobs pattern (`SendWhatsAppCampaignMessage`) | Model generation job on it |
| RBAC | `marketing.assets`, `marketing.ai`, `marketing.analytics`, `marketing.settings` perms; `admin.permission:<slug>` middleware; `Admin::can()` (super-admin bypass) | Gate Studio on these |
| Admin UI | Tailwind + Alpine, `layouts.admin`, `group_advertising` sidebar, `.admin-card`/`.btn-primary`/`.admin-table`, generic form/index partials | Reuse fully |
| Source of truth | `Package` (name/slug/price/discount/duration/highlights/inclusions/exclusions/gallery/itinerary), `Hotel`, `Destination`, flights via `PackageFlightOption`; brand/contact via `settings()` | Never re-enter; pull live |

---

## B. Provider-agnostic AI media architecture (§29)

New contracts under `app/Services/Marketing/Ai/` (mirroring the existing text layer):

```
AiImageProviderInterface   -> generateImage(prompt, opts): {url|bytes, meta}
                              editImage(bytes, prompt, opts) [optional]
  OpenAiImageProvider       (gpt-image-1 / DALL·E 3 — /v1/images/generations)
  GeminiImageProvider       (imagen / gemini image) [optional]
  StabilityImageProvider    [optional]
  NullImageProvider         (throws AiUnavailableException; isConfigured=false)
AiImageProviderManager     (resolves from config('services.ai'), key from IntegrationSetting)

# Phase 3+ (provider-gated, documented, NOT faked if no provider):
AiVoiceProviderInterface, AiMusicProviderInterface, AiVideoProviderInterface
```

- Keys stored via `IntegrationSetting` (encrypted) + overlay — same as text providers. New slugs: `openai_image` (or reuse `openai`), `stability`, etc.
- If unconfigured → UI shows **"Connect an image provider"** (never breaks, never fakes). Copy generation still works via existing text layer.
- Compositing (logo/price/CTA/badges over the AI/library image) done **server-side** via Intervention Image (GD/Imagick) in a queued job — deterministic, brand-accurate, no fake photos. AI generates *backgrounds/concepts*; factual text is overlaid from DB, never invented by the model.

---

## C. Data model (adopt + extend, additive migrations)

- **Adopt** `marketing_creatives`, `marketing_assets`. Add columns: creative `campaign_id?`, `brand_kit_id?`, `template_id?`, `format`(e.g. ig_1x1), `platform`, `objective`, `audience`, `language`, `status`(draft/pending/approved/rejected/published/archived), `approval_*`, `variation_group`, `parent_id`(versioning), `render_path`, `thumb_path`, `tracking`(json), `ai_generation_id`. Asset: add `width/height`, `source`(portal/ai/upload), `checksum`.
- **New tables:** `creative_brand_kits` (logo, colors, fonts, contact, socials, default CTA/disclaimer, is_default, company scope), `creative_templates` (category, platform, format, layers json, variables), `creative_variations` (or reuse variation_group on creatives), `creative_exports` (bundle path, formats, status). Reuse `marketing_experiments`/`_variants` for A/B, `marketing_attributions`/`marketing_conversions` for tracking→lead→booking→revenue.
- Proper FKs, indexes, soft deletes, `created_by`, timestamps. Guard migrations with `Schema::hasTable`.

---

## D. Phased build

**Phase 1 — Core Studio (image + copy, real, shippable):**
Studio dashboard; Create-Creative wizard (source→objective→audience→type→format); pull package/hotel/destination data live; AI copy variations (reuse `AiCampaignService` pattern, strict no-invention); provider-agnostic image generation + "use portal image" option; server-side brand compositing (logo/price/CTA/disclaimer) → PNG/JPG/WEBP; Brand Kit CRUD (seed from `settings()`); dynamic templates with `{{package_name}}` etc.; asset/creative library (adopt tables) with filters/pagination; queued generation job + statuses + retry; fact-check vs DB before export; quality checks (overflow/contrast/dims/contact/disclaimer); export/download; RBAC (`marketing.assets`/`marketing.ai`); audit log; "Create Ad" buttons on Package/Hotel/Destination admin; responsive admin UI; sidebar "Creative Studio" in `group_advertising`.

**Phase 2 — Editor & variations:** lightweight Alpine/canvas editor (text/logo/badges/layers/undo), multi-format "Generate All Formats", version history, media library upgrades.

**Phase 3 — Video (provider-gated):** storyboard from package, scene editor, AI video/voice/music via new provider interfaces, ffmpeg render queue. If no provider → "Connect a video provider". No fake video.

**Phase 4 — Marketing intelligence:** UTM + tracking IDs on every creative, attribution creative→lead→booking→revenue (reuse `marketing_attributions`/`marketing_conversions`), analytics dashboard (only real data), AI insights when data sufficient.

**Phase 5 — Publishing:** `AdPublisherInterface` + Meta/Google/YouTube publishers (Meta already partly wired), WhatsApp Status/QR/deep-link assets (do NOT touch existing WhatsApp automation). "Connect account" when unconfigured.

---

## E. Cross-cutting (all phases)

Cost tracking (log tokens/est-cost to `marketing_ai_generations`, per-user/company limits, admin monthly cap via `services.ai.monthly_budget`); security (validation, MIME/size limits, keys never in frontend, rate limiting, `marketing.*` gates, audit); performance (queue heavy work, thumbnails, lazy load, paginate, avoid N+1, cache brand/templates); error handling (human-readable, retry without losing work, log details). QR via a small lib; UTM auto-built like `CampaignService::trackingUrl`.

---

## F. Genuine decisions needed before Phase 1 build

1. **Image provider** — which one, and is a key available? (OpenAI `gpt-image-1`, Google Imagen/Gemini, Stability.) The architecture ships provider-agnostic and works keyless (shows "Connect provider"); the choice only affects which adapter I wire first.
2. **Compositing library** — Intervention Image (needs GD or Imagick PHP extension). If neither is available in the environment, fall back to a client-side canvas render for export. (I cannot detect PHP extensions from here.)
3. **Scope of this build** — Phase 1 is large but self-contained and real. Phases 3 (video) and 5 (publishing) are provider-gated and should be scaffolded now, implemented when providers are connected.
