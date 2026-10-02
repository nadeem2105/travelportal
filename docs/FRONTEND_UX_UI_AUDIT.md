# Frontend UX/UI Audit — Leemroz Travels Portal

**Date:** 2026-09-21
**Scope:** Public customer-facing website only (admin panel excluded).
**Principle:** Evolution, not rebuild. Preserve brand, architecture, routes, booking/payment/CRM/analytics. Improve UX, polish, mobile, performance, accessibility, and close conversion gaps where the existing backend already supports them.

---

## 1. Current Architecture

| Layer | Technology | Notes |
|---|---|---|
| Backend | Laravel 12, PHP 8.2 | Public controllers under `app/Http/Controllers/Site/*` |
| Views | Blade | Public views in top-level `resources/views/*` (`home/`, `packages/`, `hotels/`, `flights/`, `cabs/`, `destinations/`, `account/`, `checkout/`, `auth/`). No `views/site` dir. |
| CSS | **Tailwind CSS v4** (`@tailwindcss/vite`) | Single stylesheet `resources/css/app.css`. Tokens via `@theme`. No `tailwind.config.js` (v4 CSS-config). No Bootstrap. |
| JS | **Alpine.js 3** + axios | `resources/js/app.js` only boots Alpine. Components defined inline per-view via `Alpine.data()` in `@push('scripts')`. |
| Build | Vite 7 + laravel-vite-plugin | Inputs: `resources/css/app.css`, `resources/js/app.js`. |
| Other libs | Chart.js (admin only) | No jQuery, Swiper, flatpickr. Native `<input type=date/time>`. |

**Layout chain:** `layouts/base.blade.php` (HTML head, SEO/OG, JSON-LD, Google Fonts, `@vite`, analytics injection) → `layouts/site.blade.php` (header + `@yield('hero')` + `@yield('page')` + footer).

**Product flows:** Four independent booking entry points (package / flight / hotel / cab) funnel into ONE shared checkout → payment → confirmation → account pipeline keyed on `booking_reference`.

---

## 2. Current Design System

Well-defined, token-driven, single-framework. Codified as component classes in `resources/css/app.css`.

- **Brand palette:** blue, primary `--color-brand-600 #2563eb` (50→900 scale). Neutral "ink" scale (`ink-900 #0f172a` … `ink-300`). `--color-canvas #f6fafd` page bg, `--color-night #0b1f3a` footer/dark.
- **Typography:** `--font-sans` Inter; `--font-display` Poppins (h1–h3, `.font-display`); `--font-script` Dancing Script (hero accent). Loaded via Google Fonts.
- **Radius:** `--radius-card 1rem`; pills `rounded-full` (buttons/tags), `rounded-2xl` (cards/fields), `rounded-3xl` (header), `rounded-xl` (inputs).
- **Shadows:** two tokens — `shadow-card` (resting), `shadow-float` (elevated). Hairlines via `ring-1 ring-slate-900/5` rather than solid borders.
- **Components (class-based):** `.btn` (+ `-primary/-ghost/-white/-danger`, `-sm/-md/-lg`), `.card` (+ `.card-hover`, `.pkg-card`, `.dest-card`, `.testi-card`), `.badge` variants, `.chip`, `.field/.field-input`, `.input/.label/.form-error`, `.alert-*`, `.shell` (max-w-1400 container).
- **Blade components:** `site/header`, `site/footer`, `search-widget`, `package-card`, `destination-card`, `testimonial-card`, `rating`, `account/shell`, `partials/flash`.

**Design-system inconsistencies:**
- Two parallel input systems: `.field-input` (rounded-2xl, ring) vs `.input` (rounded-xl, border). Different look in different contexts.
- Navigation markup duplicated (desktop nav vs mobile nav lists; fallback links hardcoded in 3 places: header desktop, header mobile, footer).
- No shared Blade atoms for button/input/modal/skeleton/empty-state — atoms are class-only, so SVG icons and markup are repeated inline across views.

---

## 3. Existing User Journeys

1. **Ready package:** Home/search → package listing → package detail → package booking wizard (date/travellers → optional flights → optional hotel → travellers → billing → review) → checkout → payment → confirmation. *(Most polished path.)*
2. **Flight / Hotel / Cab:** search widget → results → select → traveller/contact form → checkout → payment → confirmation. *(Plainer, no stepper, no live total.)*
3. **Account:** login/register → dashboard → trips (status-filtered) → trip detail (with milestone timeline) → wishlist / profile / saved travellers / notifications.

**MISSING journeys:** No public "Build Your Trip" custom builder. No public AI Trip Planner (only inbound WhatsApp AI exists).

---

## 4. UX Problems

| # | Problem | Location | Priority |
|---|---|---|---|
| UX-1 | **Contact form bypasses CRM** — posts to `contact.submit`, only creates `ContactMessage` + notification; never becomes a CRM lead. Lead/attribution pipeline exists but is unused by the site. | `PageController@submitContact`, `pages/contact.blade.php` | **P0** |
| UX-2 | **Lead-capture endpoint orphaned** — `POST /leads/capture` (`LeadCaptureController`, rich fields, honeypot) has NO frontend. No "Get Quote" / "Customize Trip" / callback form or popup anywhere. | `routes/web.php`, no view | **P0** |
| UX-3 | Inconsistent booking UX — stepper + live total for packages only; flights/hotels/cabs feel like an older product. | flight/hotel/cab flows | P1 |
| UX-4 | No T&C / cancellation-policy acceptance at checkout (legal + trust gap). | `checkout/show.blade.php` | P1 |
| UX-5 | Fare-lock shown as text but not enforced (no countdown, no `expires_at` check in `pay()`). | `CheckoutController@pay` | P2 |
| UX-6 | No password reset, no email verification, no "create account to track this trip" after guest checkout. | `auth/*` | P1 |
| UX-7 | Package wizard uses `alert()` for validation errors. | `packages/book.blade.php` | P2 |
| UX-8 | Global `/search` has no autocomplete/debounce/recent searches and (with the dead header icon) no discoverable entry point on desktop. | `search.blade.php`, header | P2 |

---

## 5. UI Inconsistencies

- Two input systems (`.field-input` vs `.input`).
- Duplicated nav markup and hardcoded menu fallbacks in 3 locations.
- Hotel & cab results have NO filters/sorting; flights has a full filter sidebar + sort + skeletons. Packages listing exposes only text/destination/sort (no budget, duration UI, type, star, meal).
- Package detail has a single cover image, no gallery/carousel; hotel detail pads with static SVG thumbnails.
- No shared modal/drawer/toast/skeleton/empty-state components — each page rolls its own or omits them.

---

## 6. Mobile Problems

| # | Problem | Priority |
|---|---|---|
| M-1 | **No mobile sticky CTA** on package & hotel detail — booking card is `lg:sticky` only, so on mobile it stacks far below long itinerary/reviews. Major conversion loss. | **P1** |
| M-2 | Mobile nav panel lacks account links, phone, and primary CTA (Get Quote / Book). | P1 |
| M-3 | Flight/hotel/cab flows have no mobile-optimized summary/price bar. | P2 |
| M-4 | Search widget on small screens: tab + multi-field forms need spacing/overflow review at 360–414px. | P2 |

---

## 7. Accessibility Problems

- Forms rely on placeholders; labels not always tied via `for`/`id` (contact form, hotel/cab `datalist` inputs, flight hidden inputs).
- Dropdowns (account menu, mobile nav, type-ahead) lack `aria-expanded`/`aria-controls`/`role="menu"` and focus trapping.
- Generic `alt="logo"`; dynamic card images depend on admin data with weak alt fallbacks.
- No skip-to-content link.
- Raw `{!! settings('seo_analytics_code') !!}` injection (admin-only, but note).
- Good baseline: semantic `<header>/<main>/<footer>`, `aria-label` on icon buttons, `x-cloak`.

---

## 8. Performance Problems

- Full airport table embedded as JSON in every page carrying the search widget (flight type-ahead) — payload weight on all pages with the hero widget.
- Images: no explicit responsive `srcset`/`sizes`, format (WebP/AVIF), or `width/height` to prevent CLS observed in card components; lazy-loading not systematic.
- Google Fonts render-blocking `<link>` (no `preconnect`/`display=swap` audit).
- Skeletons only on flights results — hotels/cabs/packages show nothing during load (though most are server-rendered, so limited async).
- No custom branded error pages (`resources/views/errors/` absent) — falls back to unbranded Laravel 404/500.

---

## 9. Analytics / Tracking Status

- **Attribution capture: EXCELLENT** — `CaptureAttribution` middleware (global web group) + `AttributionService` preserve first/last-touch UTM, gclid, fbclid, landing_page, referrer in session, mapped to lead columns via `LeadService`.
- **Gap:** Because public forms bypass `LeadService` (UX-1/UX-2), that attribution rarely reaches CRM from the website — only ad-webhook leads benefit.
- **Analytics events: MISSING** — single admin-pasted head snippet (`settings('seo_analytics_code')`), head-only (no GTM `<noscript>` body tag). Zero conversion-event instrumentation: no ViewContent / Search / Lead / InitiateCheckout / Purchase anywhere. Even with a base GA4/Pixel snippet, only pageviews fire.

---

## 10. Confirmed Bugs (quick wins)

| # | Bug | Location | Priority |
|---|---|---|---|
| B-1 | **Wishlist heart broken outside account** — cards call `toggleWishlist(...)` but `window.toggleWishlist` is defined only on the wishlist page. Throws `ReferenceError` on home/listing/detail/search. | `package-card.blade.php`, `destination-card.blade.php`, `app.js` | **P1** |
| B-2 | **Dead desktop search icon** — header dispatches `open-search` but nothing listens. Desktop search icon does nothing. | `components/site/header.blade.php` | **P1** |
| B-3 | `confirmation()` has no ownership guard — anyone with a `booking_reference` can view it (shows first name + email). | `CheckoutController@confirmation` | P1 (privacy) |
| B-4 | Confirmation labels figure "Amount Paid" even when `payment_pending`. | `checkout/confirmation.blade.php` | P2 |
| B-5 | Packages listing ignores `date`/`travellers` from the hero widget; no UI for the `duration` filter the controller already supports. | `PackageController@index`, `packages/index.blade.php` | P2 |

---

## 11. Missing Features (OTA gap audit)

**Backend EXISTS → implement frontend (safe):**
- Lead popup + embedded "Get Quote / Customize Trip" form wired to `leads.capture` (attribution auto-attaches). **[UX-2, P0]**
- Route contact form through `LeadService` (or add a lead alongside `ContactMessage`). **[UX-1, P0]**
- Wishlist entry points during browsing (backend `wishlist.toggle` exists; fix B-1). **[P1]**
- Hotel/cab results filters + sort (data available; mirror flights pattern). **[P2]**
- Package listing budget/duration/type filters (controller partly supports). **[P2]**
- Branded error pages (pure view work). **[P2]**
- WhatsApp click-to-chat CTA (number in settings). **[P1]**
- Password reset (Laravel built-in; needs mail). **[P1]**

**Backend PARTIAL / needs work → document, do not fake:**
- **Public "Build Your Trip" custom builder** — no builder engine on the public side; the package wizard only chooses among predefined hotel/flight options. A true multi-destination/nights builder needs a `TripBuilderService` + trip/trip-items model + revalidation. **[P1, BACKEND REQUIRED — spec before build]**
- **Public AI Trip Planner** — no website AI UI. The AI provider layer (`AiProviderManager`, tools) exists for WhatsApp; a web planner would reuse it + a `search_packages`/inventory tool and MUST share the same pricing/booking engine. **[P1, BACKEND + FRONTEND — spec before build]**
- Save/continue abandoned wizard (only created bookings persist; no draft restore). **[P2, BACKEND REQUIRED]**
- Recently viewed (no tracking). **[P3, small backend]**
- Pre-pay revalidation for flight/hotel/cab (only packages via `PackageBookingVerifier`). **[P1, BACKEND]**

**Do NOT build:** fake reviews/ratings/scarcity, second CSS framework, duplicate booking logic, JS-only authoritative pricing.

---

## 12. Backend / API Dependencies (per improvement)

| Improvement | Backend dependency | Status |
|---|---|---|
| Lead popup/embedded form | `LeadService`, `leads.capture`, `CaptureAttribution` | ✅ Ready |
| Contact → CRM | `LeadService` | ✅ Ready |
| Wishlist fix | `wishlist.toggle`, `Wishlist` model | ✅ Ready |
| Hotel/cab filters | supplier data in controllers | ✅ Ready (view work) |
| WhatsApp CTA | `settings('company_phone'/whatsapp)` | ✅ Ready |
| Password reset | Laravel Password broker + mail | ✅ Framework; needs mail config |
| Error pages | none | ✅ View-only |
| Analytics conversion events | none (dataLayer/fbq push in views) | ✅ View-only + settings |
| Build Your Trip | `TripBuilderService`, trip model, pricing, revalidation | ⚠️ To be built |
| AI Trip Planner (web) | `AiProviderManager` + inventory tools + shared pricing/booking | ⚠️ Partial; needs orchestration + UI |
| Flight/hotel/cab revalidation | verifier services per product | ⚠️ To be built |
| Save/continue trip | draft model/persistence | ⚠️ To be built |

---

## 13. Recommended Improvements & Priority

**P0 — Critical (lead loss, do first):**
1. Lead popup + embedded "Get Quote / Plan My Trip" form → `leads.capture` (UX-2).
2. Route contact form through CRM `LeadService` (UX-1).

**P1 — High (conversion, mobile, bugs, trust):**
3. Fix wishlist heart globally (B-1) + expose wishlist during browsing.
4. Fix desktop search + add search UX (B-2, UX-8) and mobile sticky booking CTA (M-1).
5. WhatsApp click-to-chat CTA (non-intrusive).
6. Analytics conversion events (ViewContent/Search/Lead/InitiateCheckout/Purchase) via `dataLayer`/`fbq`, attribution preserved.
7. Checkout T&C/cancellation acceptance (UX-4) + confirmation privacy/label fixes (B-3, B-4).
8. Password reset + guest→account prompt (UX-6). Mobile nav account/CTA (M-2).
9. Design-system polish: shared Blade atoms (button, input, modal, skeleton, empty-state, toast), unify input systems.

**P2 — Medium:**
10. Hotel/cab results filters + sort; package listing budget/duration/type filters (parity with flights).
11. Branded error/empty/loading states everywhere.
12. Package detail gallery + explicit cancellation section; fare-lock countdown.
13. Image optimization (srcset/sizes/lazy/dimensions), font loading.

**P1/P2 — Larger, backend-gated (spec first, then build sharing existing engines):**
14. Public Build Your Trip builder (shared `TripBuilderService` + pricing + revalidation).
15. Public AI Trip Planner (concierge UI reusing `AiProviderManager` + inventory tools + same pricing/booking engine, with AI-labeled recommendations and authoritative backend data only).
16. Flight/hotel/cab pre-pay revalidation.

**P3 — Optional:** recently-viewed, share-trip.

---

## 14. Implementation Guardrails

- Reuse existing components/classes/services; do not introduce a second CSS framework.
- Never compute authoritative price in JS; always server-side (`PricingService`).
- AI never invents price/availability/booking status — authoritative data from backend only; AI output clearly labeled.
- Preserve all routes, controllers, payment (Razorpay order/callback/webhook/signature), auth, CRM, attribution, analytics.
- Every phase: run tests, check console/network, verify routes/forms/tracking, verify responsive at 360/375/390/414/768/1024/1440.
- Backend-gated features: document + spec; do not fake frontend functionality.
