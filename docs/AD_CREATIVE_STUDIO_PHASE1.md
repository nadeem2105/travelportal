# Ad Creative Studio — Phase 1 (Shipped)

Native module inside the existing portal: **Admin → Advertising → Ad Creative Studio**. Turns a real package/hotel/destination into a ready-to-publish image ad (live business data + AI copy + AI/portal image + brand overlay). No existing feature changed; WhatsApp/booking/CRM untouched.

## What was built

**DB (additive, idempotent)** — `2026_09_21_170000_create_ad_creative_studio_tables.php`
- Adopted the previously-orphaned `marketing_creatives` + `marketing_assets` and extended them (format/platform/objective/audience/language/style/headline/spec/tracking/warnings/generation_status/approval_status/variation_group/parent_id/version/render_path/thumb_path + soft deletes; asset width/height/source/thumb/product/checksum/license).
- New `creative_brand_kits`, `creative_templates`.

**AI provider architecture (§29)** — provider-agnostic, separate from text layer:
- `AiImageProviderInterface`, `OpenAiImageProvider` (gpt-image-1, reuses the OpenAI key), `NullImageProvider` (safe "Connect provider"), `AiImageProviderManager` (gated by `services.ai.enable_image`). Credentials via the existing encrypted `integration_settings` overlay. Keys never reach the browser.

**Services** (`app/Services/Marketing/Creative/`)
- `CreativeContextService` — resolves AUTHORITATIVE facts (live price/discount/duration/inclusions/images/booking URL/contact) from Package/Hotel/Destination + `settings()`. Single source of truth.
- `CreativeCopyService` — AI copy (headline/primary/description/hook/CTA) via the existing text `AiProviderManager`, strict no-invention prompts, logged to `marketing_ai_generations`. Deterministic template fallback when AI is off.
- `CreativeFormats` — platforms, formats+dimensions, objectives→CTA, audiences, styles, languages, variation focuses.
- `CreativeStudioService` — orchestrator: resolve facts → brand kit → copy → tracking/UTM → draft creative → queue render. One path for normal + variation flows.
- `CreativeCompositor` — GD compositing: base photo (AI or portal) + scrim + logo + headline + price badge + CTA pill + contact + disclaimer, per-format dimensions; saves PNG render + JPG thumb; persists AI backgrounds to the asset library marked **illustrative**.
- `CreativeFactCheckService` (§35) + `CreativeQualityService` (§36) — post-generation warnings (duration mismatch, false scarcity, superlatives, resolution, overflow, missing contact/CTA).

**Queue** — `GenerateCreativeJob` (database queue, retryable, statuses queued→processing→completed/failed). Never blocks the request.

**Controllers + routes** (gated `marketing.assets`; approve/reject/publish gated `marketing.campaigns.publish`)
- `CreativeStudioController` — dashboard, wizard, store, show, status (poll), regenerate, variations, submit/approve/reject/publish, duplicate (versioned), delete (blocks published), download.
- `CreativeBrandKitController`, `CreativeTemplateController`, `CreativeAssetController` (media library + upload).
- All under `admin/studio/*`, names `admin.studio.*`.

**Views** (`resources/views/admin/marketing/studio/`) — dashboard, create wizard (4-step Alpine), creative show (live preview polling + copy + facts + tracking + approval + variations), brand kits, templates, media library. Reuses admin design system (`.admin-card`, `.btn-*`, `.status-pill`); responsive.

**Integration**
- Sidebar: "Ad Creative Studio" under Advertising.
- "✨ Create Ad" buttons on admin Package / Hotel / Destination edit pages (deep-link with source preselected).
- AI Providers settings: image-generation toggle + image model.

**Seeder** — `CreativeStudioSeeder` (default brand kit from `settings()` + 14 system templates). Registered in `DatabaseSeeder`.

## Safety / principles honored
- Facts never invented — copy is constrained to resolved DB facts; fact-check flags mismatches; no auto-publish on warnings.
- AI images labeled illustrative; portal images preferred when chosen.
- Provider-agnostic; keyless-safe (shows "Connect provider"); keys encrypted, never in frontend.
- Reused: AI layer, `integration_settings`, media/`public` disk, queue, RBAC, admin layout, Package/Hotel/Destination/settings. Nothing removed or renamed.

## PENDING (user must run — no PHP/npm in build env)
```
php artisan migrate
php artisan db:seed --class=CreativeStudioSeeder
php artisan db:seed --class=PermissionSeeder   # ensure marketing.assets assigned to roles
php artisan config:clear && php artisan route:clear && php artisan view:clear
npm run build
php artisan queue:work --queue=default          # so GenerateCreativeJob runs
```
- To enable AI images: AI Providers page → set OpenAI key → tick "Enable AI image generation".
- Fonts: GD overlays use `config('creative.fonts')` (defaults to Windows Arial). Override `CREATIVE_FONT_BOLD`/`CREATIVE_FONT_REGULAR` to bundle your own; if unreadable, text overlay is reduced (warned).
- Requires the PHP **GD** extension (standard in XAMPP).

## NOT in Phase 1 (roadmap — provider-gated, documented not faked)
Phase 2 canvas editor, multi-format bulk beyond the main set, version compare. Phase 3 video/voice/music (need video/TTS providers + ffmpeg → "Connect provider"). Phase 4 full attribution dashboard + AI insights (schema ready via `marketing_attributions`/`marketing_conversions`). Phase 5 publishing (Meta already partly wired; `AdPublisherInterface` pattern). QR codes (add `endroid/qr-code`). Object storage/R2 (s3 disk configured, unused).
