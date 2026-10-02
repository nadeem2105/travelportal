# Architecture & Codebase Audit: Travel Portal (OTA)

**Date**: 2026-09-18  
**Environment Audited**: Local Development / Production Baseline  
**Framework**: Laravel 12.0.0 (PHP 8.2.12 / Target PHP 8.4+)  
**Database**: MySQL 8.0+ (`travel_portal` with 69 tables) / SQLite (`:memory:` for test harness)  
**Document Status**: Phase 0 Complete — Awaiting Approval for Phase 1/Phase 4 Execution  

---

## Executive Summary

The **Travel Portal** application is a production-oriented, modular Online Travel Agency (OTA) and Travel Management platform built on Laravel 12. It encompasses a multi-product booking engine (Flights, Hotels, Cabs, Packages), unified checkout with dual-mode payment orchestration (Razorpay + Mock sandbox), multi-guard role-based authentication and authorization (Super Admin, Staff RBAC, Customer Auth, Sanctum REST API), CMS, dynamic pricing and taxation engines, customer self-service portals, and administrative operations.

The codebase is already significantly developed (59 Eloquent models, 64 controllers, 23 services, 275+ registered routes, and 14 migration sets). However, several architectural vulnerabilities, concurrency race conditions, unqueued synchronous operations, stubbed supplier adapters, and missing enterprise modules (B2B Agent Subsystem, CRM, Affiliate Marketing, Financial Double-Entry Ledgers) require systematic remediation and hardening before scaling in a high-concurrency production environment.

---

## 1. Project Architecture

### Architecture Pattern
- **Pattern**: Layered MVC with Service Layer and Supplier Adapter pattern.
- **Presentation**: Blade templating with Tailwind CSS v4, Alpine.js 3.17, Chart.js 4.5.
- **Application Services**:
  - `BookingService`: Unified booking lifecycle orchestration, price validation, and supplier confirmation.
  - `PricingService`: Dynamic stackable pricing calculation (Markup, Service Fee, Convenience Fee, Taxes).
  - `FlightEngine` / `HotelEngine` / `CabService`: Domain-specific aggregators fanning out across suppliers.
  - `PaymentManager`: Gateway-agnostic payment order creation, webhook handling, and refund orchestration.
  - `CouponService`: Promo code validation, usage restriction, and discount application.
  - `NotificationService`: Database and email templated dispatching.
  - `SettingsService`: Database-backed system settings caching.
  - `ActivityLogger`: Administrative audit logging.
- **Data Persistence**: Eloquent ORM interacting with MySQL 8 (`travel_portal`) with relational constraints and JSON document storage for payloads and journey details.

---

## 2. Existing Modules

1. **Flight Booking Engine**: One-way, round-trip search, fare calculation, seat/baggage structure, booking generation, PNR/ticket tracking.
2. **Hotel Booking Engine**: Destination-based hotel and room search, room inventory management, meal plans, direct and supplier booking.
3. **Cab & Transfer Engine**: Point-to-point, airport transfer, local rental distance/hourly calculation, vehicle fleet assignment, vendor management.
4. **Tour Package Engine**: Destination packages, multi-day itineraries, seasonal pricing, departure inventory, guest count calculations.
5. **Checkout & Payment Engine**: Server-side price recalculation, coupon application, order creation, signature verification, webhooks, idempotency tracking.
6. **Customer Portal**: Account dashboard, booking history, PDF/printable invoice and itinerary vouchers, traveller profiles, wishlists, reviews, support tickets.
7. **Admin Panel**: Role-based access control, booking status transitions, refund processing, reconciliation, catalog CRUDs (Destinations, Hotels, Rooms, Cabs, Packages, Airlines, Airports), CMS, SEO, Reports, System Settings.
8. **REST API (v1)**: Sanctum token-authenticated endpoints for mobile/third-party access (`/api/v1/auth`, `/api/v1/destinations`, `/api/v1/packages`, `/api/v1/flights/search`, `/api/v1/hotels/search`, `/api/v1/cabs/search`, `/api/v1/bookings`).

---

## 3. Existing Database Structure

The MySQL database (`travel_portal`) contains **69 tables**:
- **Core Auth & Security**: `users`, `admins`, `roles`, `permissions`, `permission_role`, `admin_role`, `otps`, `password_reset_tokens`, `sessions`.
- **Bookings & Transactions**: `bookings`, `booking_items`, `booking_travellers`, `payments`, `refunds`, `webhook_events`.
- **Vertical Child Tables**: `flight_bookings`, `hotel_bookings`, `cab_bookings`, `package_bookings`.
- **Catalog & Inventory**:
  - *Flights*: `airlines`, `airports`, `flight_searches`.
  - *Hotels*: `hotels`, `hotel_rooms`.
  - *Cabs*: `vehicles`, `vehicle_types`, `cab_vendors`, `cab_locations`.
  - *Packages*: `packages`, `package_itineraries`, `package_prices`, `package_departures`, `package_hotels`.
  - *Destinations*: `destinations`.
- **Commercial Engines**: `pricing_rules`, `taxes`, `coupons`, `offers`, `suppliers`, `supplier_credentials`, `payment_gateways`.
- **CMS & Support**: `homepage_sections`, `pages`, `banners`, `faqs`, `testimonials`, `blogs`, `guides`, `menus`, `menu_items`, `media`, `seo_metadata`, `support_tickets`, `support_messages`, `contact_messages`, `reviews`, `notification_templates`, `user_notifications`, `newsletter_subscribers`, `wishlists`, `saved_travellers`, `activity_logs`, `settings`.
- **Infrastructure**: `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`.

---

## 4. Existing APIs

- Defined in `routes/api.php` and `routes/api_v1.php` with prefix `/api/v1` and rate limiting `throttle:60,1`.
- **Public Endpoints**:
  - `POST /api/v1/auth/register`
  - `POST /api/v1/auth/login`
  - `GET /api/v1/destinations`
  - `GET /api/v1/packages`
  - `GET /api/v1/packages/{package:slug}`
  - `GET /api/v1/flights/search`
  - `GET /api/v1/hotels/search`
  - `GET /api/v1/cabs/search`
- **Authenticated Endpoints (`auth:sanctum`)**:
  - `GET /api/v1/me`
  - `GET /api/v1/bookings`
  - `GET /api/v1/bookings/{booking}`
  - `POST /api/v1/bookings/{booking}/cancel`
- **Identified API Gaps**:
  - No API endpoint for booking creation (`POST /api/v1/bookings`) or checkout/payment initiation.
  - No hotel room availability lookup endpoint in API.
  - No flight seat/fare rules API endpoint.
  - No API endpoints for profile updates, saved travellers, notifications, or reviews.

---

## 5. Existing Supplier Integrations

- **Registry**: `SupplierAdapterRegistry` resolves supplier instances dynamically based on `suppliers.adapter`.
- **Flight Suppliers**:
  - `DemoFlightSupplier`: Fully functional deterministic mock supplier with realistic airlines (IndiGo, Vistara, Air India), realistic fares, on-time performance, and baggage details.
  - `AmadeusFlightSupplier`: Skeleton adapter with OAuth2 token structure; endpoints return stubbed failure responses.
  - `TBOFlightSupplier`: Skeleton adapter copying Amadeus signature; endpoints stubbed.
  - `AkbarFlightSupplier`: Skeleton adapter; endpoints stubbed.
- **Hotel Suppliers**:
  - `DemoHotelSupplier`: Deterministic search and room supplier.
  - `ManualHotelSupplier`: Resolves locally managed hotel inventory and rooms from database.
- **Supplier Architecture Evaluation**:
  - Interfaces (`FlightSupplierInterface`, `HotelSupplierInterface`) exist and isolate controller logic.
  - Live supplier credentials in `supplier_credentials` are encrypted in database via Eloquent casts.
  - **Gap**: Fallback and circuit-breaker logic across multiple suppliers is absent; if one supplier times out, the search engine waits until HTTP timeout instead of graceful degradation.

---

## 6. Existing Payment Integrations

- **Orchestration**: `PaymentManager` resolves active gateway from `payment_gateways` table.
- **Adapters**:
  - `RazorpayGateway`: Integration with Razorpay API (order creation, HMAC-SHA256 signature verification, webhooks, refunds).
  - `MockGateway`: Sandbox gateway for local testing and immediate capture simulation.
- **Lifecycle & Reconciliation**:
  - `PaymentManager::handleCallback`: Locks payment record (`lockForUpdate`), updates payment to `captured`, and triggers `BookingService::handlePaymentSuccess`.
  - `PaymentManager::handleWebhook`: Idempotency tracking via `WebhookEvent::firstOrCreate(['event_id' => ...])`.
  - **Reconciliation State**: Explicit state `payment_success_booking_failed` is implemented in `Booking::STATUSES` and handled in `BookingService::handlePaymentSuccess` when the supplier confirmation fails after payment capture.
- **Admin Reconciliation Screen**: Route `admin.reconciliation` (`Admin\RefundController@reconciliation`) displays bookings stuck in `payment_success_booking_failed` with direct refund or retry options.

---

## 7. Existing Authentication

- **Multi-Guard Setup**:
  - `web` guard: Uses `App\Models\User` provider for customers. Protected via `CustomerAuthenticate` middleware.
  - `admin` guard: Uses `App\Models\Admin` provider for administrators and staff. Protected via `AdminAuthenticate` middleware.
  - `sanctum` guard: Token-based authentication for mobile/external APIs.
- **Rate Limiting**:
  - Customer Login: `throttle:10,1`
  - Admin Login: `throttle:8,1`
  - Registration: `throttle:10,1`
  - API v1: `throttle:60,1`

---

## 8. Existing Authorization

- **RBAC Architecture**:
  - `Admin` model belongs to `Role` (`admin_role` pivot).
  - `Role` belongs to many `Permission` records (`permission_role` pivot).
  - 20 permissions seeded covering all sensitive operations (`manage_settings`, `view_bookings`, `edit_booking`, `cancel_booking`, `refund_booking`, `manage_payments`, `manage_customers`, `manage_staff`, `manage_roles`, `manage_permissions`, `manage_suppliers`, `manage_pricing`, `manage_packages`, `manage_hotels`, `manage_cabs`, `manage_flights`, `manage_content`, `manage_reports`).
  - Super Admin flag (`is_super_admin = 1`) bypasses permission checks via `Gate::before`.
  - Server-side middleware `EnsurePermission` (`admin.permission:<slug>`) is strictly applied to every admin route in `routes/admin.php`.

---

## 9. Existing Admin Functionality

- **Dashboard**: Revenue metrics, booking volume counters, recent bookings, conversion summaries.
- **Booking Management**:
  - Full details view, status changer (quick header, sidebar card, inline list dropdown).
  - Internal administrative notes logging.
  - Booking cancellation and refund triggering.
  - Printable / downloadable Invoices and Itinerary vouchers for all 4 booking types.
  - CSV export of booking registers.
- **Reconciliation Center**: Specific interface for handling paid bookings where supplier issuance failed.
- **Catalog Management**:
  - Packages, multi-day itineraries, seasonal rates, departure dates.
  - Hotels, room types, pricing, amenities, policies.
  - Cabs, vehicle types, vendors, pricing formulas.
  - Airlines, airports, routes.
- **Financial & Commercial**:
  - Dynamic pricing rules, GST/tax management, coupon codes, promotional offers.
  - Payment gateway settings and live/test mode toggles.
- **CMS & SEO**:
  - Homepage section reordering and toggling, pages, banners, blogs, travel guides, FAQs, testimonials, navigation menus.
  - Per-page meta tags, OpenGraph configuration, canonical URLs.
- **Support & Audit**:
  - Support ticket inbox and admin reply threads.
  - Full activity audit trail logging admin actions (`activity_logs`).

---

## 10. Existing Customer Functionality

- Self-service dashboard (`/account`):
  - "My Trips" tabbed view (Upcoming, Completed, Cancelled).
  - Booking detail view with itemized pricing, traveller lists, driver/flight/hotel vouchers.
  - Invoices and Itineraries with print-to-PDF formatting.
  - Cancellation requests with customer reason input.
  - Profile management, password updates, and saved travellers management.
  - Support ticket creation and message history.
  - In-app notification center with unread counters.

---

## 11. Existing B2B Functionality

- **Status**: **NOT IMPLEMENTED** (Architectural Gap).
- Currently, only B2C customers (`User`) and administrative staff (`Admin`) exist.
- Missing: Agent profiles, agency KYC/approval flow, agent deposit wallets, credit limits, markup/commission rules per agent tier, sub-agents, and agent statements.

---

## 12. Existing CRM

- **Status**: **PARTIALLY IMPLEMENTED / RUDIMENTARY**.
- Existing: Customer message inbox (`contact_messages`), support ticket system (`support_tickets`, `support_messages`), and customer profile activity view.
- Missing: Structured Lead generation, inquiry pipeline stages, quotation generation, automated follow-ups, and sales agent assignment.

---

## 13. Existing CMS

- Database-driven CMS models: `HomepageSection`, `Page`, `Banner`, `Blog`, `Guide`, `Faq`, `Testimonial`, `Menu`, `MenuItem`, `Media`.
- Homepage layout is completely controllable via `Admin\HomepageController` with dynamic visibility, sorting order, and content blocks.
- Media management with secure local upload and image selection.

---

## 14. Existing Reporting

- Implemented in `Admin\ReportController`:
  - Booking status summary, daily/monthly revenue reports, supplier revenue breakdown, top booked packages/hotels/cabs.
  - CSV exports for accounting.
- Missing: Profit/loss margin calculation, tax liability reconciliation, gateway settlement fee tracking, and agent ledger reporting.

---

## 15. Existing Notifications

- `NotificationService`:
  - Multi-channel architecture prepared with email templates stored in `notification_templates` (`booking_confirmed`, `booking_processing`, `booking_cancelled`, `otp`, `welcome`).
  - In-app notifications stored in `user_notifications`.
- **Vulnerability / Bottleneck**:
  - Email dispatch is **synchronous** (`Mail::raw(...)` called directly in request cycle). A slow SMTP server directly blocks customer checkout response.
  - SMS / WhatsApp gateways are not yet connected (code contains phone number placeholders).

---

## 16. Existing SEO

- Implemented via `SeoService` and `SeoMetadata` model.
- Meta titles, meta descriptions, canonical URLs, OG tags, and schema JSON-LD generation for Destinations, Packages, Blogs, and Pages.
- Missing: Dynamic XML sitemap generator and automated robots.txt generation.

---

## 17. Existing Caching

- `FlightEngine::search` uses `Cache::remember(..., 300)` keyed by MD5 parameter signature.
- `HotelEngine::search` uses `Cache::remember(..., 120)`.
- `SettingsService` caches all settings permanently until updated.
- Currently configured with `database` cache store in `.env`. Production should utilize **Redis** for distributed locking and low-latency cache retrieval.

---

## 18. Existing Queue System

- Configured with `QUEUE_CONNECTION=database` in `.env` and `sync` in `phpunit.xml`.
- Migration `0001_01_01_000002_create_jobs_table.php` is executed and tables (`jobs`, `job_batches`, `failed_jobs`) exist in MySQL.
- **Gap**: There are **zero Queue Jobs** defined in `app/Jobs`. High-latency tasks (emails, PDF generation, supplier synchronization, webhooks) are currently running synchronously on the web worker.

---

## 19. Existing Scheduled Jobs

- `bootstrap/app.php` references `commands: __DIR__.'/../routes/console.php'`.
- `routes/console.php` is missing from disk.
- `php artisan schedule:list` reports `No scheduled tasks have been defined`.
- Missing automated jobs:
  - Expired booking cleanup (for bookings exceeding `booking_expiry_minutes`).
  - Supplier search cache pruning.
  - Unprocessed webhook retry.
  - Abandoned checkout reminders.

---

## 20. Existing Tests

- Test Suite: PHPUnit with 28 automated tests (74 assertions).
- Tests cover:
  - `Feature/AuthTest.php`: Customer registration, login, logout, password validation.
  - `Feature/BookingFlowTest.php`: Booking draft creation, pricing calculation, mock payment execution, invoice/itinerary generation.
  - `Feature/CouponAndWebhookTest.php`: Coupon validation, usage limits, Razorpay webhook signature verification and idempotency.
  - `Feature/SitePagesTest.php`: Public search, package listings, static pages.
  - `Unit/ExampleTest.php`: Basic assertions.
- **Status**: All 28 tests passing (`OK (28 tests, 74 assertions)`).
- **Test Gaps**: Concurrent booking collision test, overbooking test on departure inventory, payment success with supplier failure reconciliation test.

---

# Comprehensive Code Quality & Architecture Findings

## Critical Vulnerabilities & Race Conditions (P0)

1. **Departure Overbooking Concurrency Race Condition**:
   - **File**: `app/Services/BookingService.php` (Line 164-167)
   - **Problem**: `PackageDeparture::where('id', ...)->increment('booked', ...);` occurs inside `createPackageDetail` without validating that `booked + new_seats <= inventory`.
   - **Impact**: Under concurrent load, multiple users can book the last available seats simultaneously, resulting in severe overbooking.
   - **Solution**: Implement atomic conditional update:
     `UPDATE package_departures SET booked = booked + ? WHERE id = ? AND (booked + ?) <= inventory` or acquire `lockForUpdate()` within transaction.

2. **Session Leak in Asynchronous Flight Supplier Booking**:
   - **File**: `app/Services/BookingService.php` (Line 271)
   - **Problem**: In `confirmFlight(Booking $booking)`, code executes:
     `$params = session('flight_search_params', []) + ['result_id' => ...];`
   - **Impact**: If supplier confirmation is triggered from a webhook, background queue job, or admin reconciliation screen, `session()` is completely null/empty! The supplier call will lack departure dates, passengers, and origin/destination, failing silently.
   - **Solution**: Persist complete search and journey parameters directly inside `flight_bookings.journey` JSON at booking creation time and resolve strictly from the model.

3. **Webhook Concurrency Race Condition on Idempotency**:
   - **File**: `app/Services/Payments/PaymentManager.php` (Lines 142-154)
   - **Problem**: `WebhookEvent::firstOrCreate(...)` checks `processed_at`. If two identical webhook deliveries arrive simultaneously, both evaluate `$stored->processed_at === null` before either completes processing.
   - **Impact**: Duplicate payment capture handling and multiple supplier confirmations.
   - **Solution**: Use `Cache::lock('webhook:' . $eventId, 10)->block(...)` or atomic database update `UPDATE webhook_events SET processed_at = NOW() WHERE event_id = ? AND processed_at IS NULL`.

---

## High Priority Issues (P1)

4. **Synchronous Blocking Operations (Email & PDF Generation)**:
   - **File**: `app/Services/NotificationService.php`
   - **Problem**: `Mail::raw()` runs synchronously during the HTTP request. If external SMTP latency spikes to 3-5 seconds, user checkout hangs.
   - **Solution**: Transition notification dispatch to Laravel queued jobs (`SendBookingNotificationJob`) with retries and exponential backoff.

5. **Missing Scheduled Console Tasks & Missing `routes/console.php`**:
   - **File**: `bootstrap/app.php` references `routes/console.php` which is missing.
   - **Impact**: Expired bookings in `payment_pending` status never expire automatically; package departure seats remain blocked if payment abandoned.
   - **Solution**: Create `routes/console.php` with scheduled commands for `bookings:expire-pending`, `webhooks:retry-failed`, and `cache:prune-searches`.

6. **Hardcoded Flight Base Fare Calculation Factor**:
   - **File**: `app/Services/FlightEngine.php` (Line 80)
   - **Problem**: `$result['fare']['base'] = round(($price['supplier_cost'] + $price['markup_amount']) * 0.82);`
   - **Impact**: Hardcodes an assumed 18% tax / 82% base fare ratio regardless of actual airline fare rules or international tax brackets.
   - **Solution**: Source base fare directly from supplier fare quote breakdown and tax engine.

7. **Placeholder Supplier API Implementations**:
   - **Files**: `AmadeusFlightSupplier.php`, `TBOFlightSupplier.php`, `AkbarFlightSupplier.php`.
   - **Problem**: Adapters are copy-pasted skeletons returning `'error' => 'Amadeus adapter is not configured yet.'`.
   - **Solution**: Implement structured HTTP request/response parsing with official schema mapping and sandbox support.

---

## Medium Priority Issues (P2)

8. **N+1 Query Potentials in Admin Listings & Reports**:
   - **Files**: `Admin\PackageController@index`, `Admin\BookingController@index`, `Admin\HotelController@index`.
   - **Problem**: Some relations (e.g. `destination`, `supplier`, `successfulPayment`) are lazily accessed in Blade loops when filtering.
   - **Solution**: Ensure consistent eager loading (`with(['destination', 'supplier', 'successfulPayment'])`) across all admin query builders.

9. **Missing Database Indexes on Frequent Filtering Columns**:
   - `flight_bookings`: Missing index on `pnr` and `booking_id`.
   - `hotel_bookings`: Missing index on `check_in`, `check_out`, `hotel_id`.
   - `package_bookings`: Missing index on `departure_date`, `package_id`.
   - `payments`: Missing index on `gateway`, `status`.
   - `refunds`: Missing index on `status`.

10. **Stateless API Limitations for Mobile Clients**:
    - **Files**: `app/Http/Controllers/Api/FlightController.php`, `HotelController.php`.
    - **Problem**: Search depends on web session state for traveller factor calculation; API clients have no session.
    - **Solution**: Pass traveller parameters explicitly in API requests and calculate price without relying on `session()`.

---

## Low Priority Issues & Enhancements (P3)

11. **Frontend UX Polish & Perceived Performance**:
    - Add skeleton loaders for flight and hotel search result cards.
    - Add real-time departure seat counter indicators on tour package pages.
    - Unified multi-modal trip timeline view for itinerary bookings.

12. **Structured Logging & Observability**:
    - Add unique Request Correlation IDs (`X-Correlation-ID`) across supplier API calls, webhook events, and payments.

---

# Action Roadmap (Prioritized)

- **P0 (Critical)**: Concurrency race condition on package departures, session leak in supplier flight confirmation, webhook concurrency race condition.
- **P1 (High)**: Asynchronous queue jobs for emails/notifications, `routes/console.php` with booking expiration scheduler, dynamic flight fare breakdown.
- **P2 (Medium)**: Database indexing on foreign keys and search criteria, eager loading audits, stateless API improvements.
- **P3 (Enhancements)**: B2B Agent Subsystem (models, wallets, ledgers), CRM Lead Pipeline, Affiliate Tracking.

---

*Audit completed by Antigravity AI Engineering. Awaiting user approval to proceed with Phase 1 execution.*
