# Trip Operations & Arrival Management — Pre-Build Audit

**Date:** 2026-09-24
**Author:** Engineering (automated audit)
**Scope:** Map the existing booking / package / hotel / cab / customer / payment / CRM / WhatsApp / PDF / notification / RBAC / scheduler / UI subsystems **before** building the Trip Operations module, so the new module *references and reuses* what already exists instead of rebuilding it.

---

## 0. Guiding principles for this module

1. **The `bookings` table is the single source of truth.** Trip Operations is an *operational overlay*, keyed by `booking_id`. It never re-stores pricing, payment, traveller, or product data that the booking already owns — it links to it.
2. **Snapshot only what operational history requires.** A trip's day-by-day plan and driver assignments are operational artifacts that must survive edits to the underlying package template, so they are snapshotted into new tables. Everything else (customer, amount, hotels, flights) is read live off the booking.
3. **Reuse every cross-cutting service:** WhatsApp Cloud API, `NotificationService`, `PdfDocumentService`, `ActivityLogger`, the `admin.permission` RBAC, the admin layout + Blade components, the `routes/console.php` scheduler.
4. **One net-new domain entity is justified:** there is currently **no driver entity** — driver data lives only as ad-hoc JSON on cab bookings. A reusable `drivers` table is additive, not duplicative.

---

## 1. Booking / product data model (authoritative)

### `bookings` (App\Models\Booking) — source of truth
- `STATUSES` = `pending, payment_pending, confirmed, failed, cancelled, refund_initiated, refunded, completed, payment_success_booking_failed`.
- Key columns: `booking_reference`, `user_id`, `agent_id`, `product_type` (`package|hotel|cab|flight`), `product_id`, `status`, `total_amount`, `currency`, `contact` (array cast: `first_name/last_name/email/phone`), `booked_at`, `expires_at`, `notes`, `admin_notes`.
- Relationships: `user()`, `agent()`, `items()`, `travellers()`, `payments()`, `refunds()`, `flight()` (`FlightBooking`), `hotelBooking()` (`HotelBooking`), `cab()` (`CabBooking`), `packageBooking()` (`PackageBooking`), `bookingHotels()` (`BookingHotel[]`), `packageFlights()` (`BookingPackageFlight[]`).
- `productDetail()` resolves the per-type detail row by `product_type`.
- **`hotelStayForDay(int $dayNumber): ?string`** — the key integration point for itinerary generation. Adds `dayNumber-1` days to `packageBooking->departure_date` and finds the `BookingHotel` snapshot whose `check_in`/`check_out` range covers that date; returns `hotel_name_snapshot` or null.

### `package_bookings` (App\Models\PackageBooking)
- `booking_id`, `package_id`, `package_name`, **`departure_date`** (date cast — the anchor for day→date math), `adults`, `children`, `room_count`, `price_breakdown`, `voucher_data`.

### `booking_hotels` (App\Models\BookingHotel) — immutable per-booking hotel snapshots
- `booking_id`, `hotel_id`, `segment_label`, `hotel_name_snapshot`, `address_snapshot`, `star_rating_snapshot`, `room_name_snapshot`, `meal_plan`, `check_in` (date), `check_out` (date), `nights`, `rooms`, `adults`, `children`, `infants`, totals. Helpers `guestSummary()`, `mealPlanLabel()`.

### `package_itineraries` (App\Models\PackageItinerary) — the day-by-day *template*
- `package_id`, `day_number`, `title`, `description`, `meals` (array), `activities` (array), `overnight_stay` (text fallback for the day's hotel), `sort_order`.
- **This is the source for auto-generating a trip itinerary**, combined per-day with `Booking::hotelStayForDay()`.

### `cab_bookings` (App\Models\CabBooking) — where driver data lives *today*
- `booking_id`, `vehicle_id`, `vehicle_name`, `pickup_location`, `drop_location`, `pickup_datetime` (datetime), `trip_type`, `distance_km`, `fare_breakdown` (array), **`driver_details`** (array cast).
- `driver_details` keys (set by `BookingController::assignDriver()`): `driver_name`, `driver_phone`, `vehicle_number`, `vendor_name`. **There is NO drivers table, no Driver model, no reusable driver record.** → Trip Operations introduces one.

### Customer linkage
- Registered customer: `bookings.user_id` → `users`.
- Guest: no user row; identity lives in `bookings.contact` JSON.
- CRM reverse link: `crm_leads.converted_booking_id` → `bookings.id`.

### Flights on packages
- `booking_package_flights` (`BookingPackageFlight`) — snapshot rows with `label_snapshot`, `airline_snapshot`, route/cabin/trip helpers.

---

## 2. WhatsApp Cloud API (reuse — do not rebuild)

Two layers under `app/Services/WhatsApp/`:

**`WhatsAppCloudClient`** (transport). Recipient is a raw E.164-digits string `$to`.
- `sendText(string $to, string $body, bool $previewUrl=false): array`
- `sendTemplate(string $to, string $template, ?string $lang=null, array $components=[]): array`
- `sendMedia(string $to, string $type, string $link, ?string $caption=null): array`
- `uploadMedia(string $binary, string $mime, string $filename): array` → `['success'=>bool,'id'=>mediaId]`
- `sendDocument(string $to, string $mediaId, string $filename, ?string $caption=null): array`

**`WhatsAppService`** (orchestration + persistence + 24h-window enforcement). **Use these:**
- **`notifyEvent(string $event, ?string $phone, array $params=[], ?string $name=null, ?array $document=null): array`** — the transactional entry point. Looks up the template name from `config("services.whatsapp.templates.{$event}")`, resolves/creates a conversation from a bare phone, maps ordered `$params`, and if `$document = ['bytes'=>..., 'filename'=>...]` uploads the PDF and prepends a document header component. **Never throws** — safe to call inline. Returns an array with a `success` key.
- `notifyTemplate(...)` — same, but you pass the template name directly.
- `sendDocumentPdf(WhatsAppConversation $c, string $pdfBytes, string $filename, ...)` — for inbox-context sends.

**Config:** `config/services.php` → `whatsapp` block. Env: `WHATSAPP_ENABLED`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_DEFAULT_TEMPLATE_LANG`, template names `WHATSAPP_TEMPLATE_*`. Credentials can be DB-overridden via `IntegrationSettings`.

**Persistence:** `whatsapp_conversations` (incl. `window_expires_at`, `isWindowOpen()`), `whatsapp_messages`.

**Idempotency note:** `WhatsAppService` does not itself dedupe transactional sends across events. **Trip Operations must implement its own idempotency** (see `trip_operation_comms` below) so a driver/customer is never messaged twice for the same trigger.

**24h / opt-in rule:** template messages are allowed outside the 24h window; free-form text is not. Trip Operations sends **templates** for proactive customer/driver notifications, so it complies. New template names will be added under `config('services.whatsapp.templates.*')`: `trip_driver_assigned`, `trip_customer_itinerary`, `trip_driver_reminder`, `trip_tomorrow_plan` (each must be created & approved in Meta; until then sends are a silent no-op and email/SMS carry the message).

---

## 3. NotificationService (reuse)

`app/Services/NotificationService.php`. Relevant methods:
- `send(string $key, array $variables, string $toEmail, ?string $toPhone=null, bool $queue=true): bool` — generic templated email (+SMS) dispatch driven by `NotificationTemplate` rows keyed by `key` + `channel`.
- `sendBookingConfirmation(Booking $b, bool $confirmed): void` — already fires email + SMS + WhatsApp (`notifyEvent('booking_confirmed', ...)`) and attaches a PDF. **This is the confirmation hook** — Trip Operations will generate the itinerary here (via an event listener) rather than modifying this method's body destructively.
- `sendUserNotification(int $userId, string $title, string $body, ...)` — in-app notification row.

Channel logic: email gated on `MailConfigService::isEnabled()`; SMS whenever a phone exists; WhatsApp only when enabled + template configured (else silent).

Trip Operations adds its own thin dispatch (via a service) that composes these primitives with idempotency, rather than piling more methods onto `NotificationService`.

---

## 4. PDF generation (reuse `PdfDocumentService`)

- Library: **barryvdh/laravel-dompdf**, facade `Barryvdh\DomPDF\Facade\Pdf`.
- Canonical render: `Pdf::loadView($view, $data)->setPaper('a4','portrait')->output()` → **raw bytes**.
- `PdfDocumentService` already returns bytes for `invoice/flightTicket/hotelVoucher/cabVoucher/quotation/itinerary/packageHotelVoucher`. **Add two builders:** `driverSheet(Trip $trip, ?DriverAssignment $a=null): string` and `customerItinerary(Trip $trip): string`.
- Blade PDF views live in `resources/views/pdf/`, all extend `pdf/layout.blade.php` (font **DejaVu Sans** — no color emoji; use text/`★` glyphs only) and `@include('pdf.partials.brand-header')` (company name/tagline/address from `settings()`).
- Delivery patterns: `$pdf->download(name)`, `$pdf->stream(name)`, or `response()->streamDownload(fn()=>print($bytes), $name, ['Content-Type'=>'application/pdf'])` for pre-rendered bytes.

---

## 5. RBAC (reuse — one RBAC only)

- Tables: `admins` (`is_super_admin`), `roles`, `permissions` (`slug`, `module`, `description`), pivots `admin_role`, `permission_role`.
- Middleware alias `admin.permission` → `App\Http\Middleware\EnsurePermission` (registered in `bootstrap/app.php`; Laravel-12 style, no `Http/Kernel.php`). Guard `admin`.
- Check: `$admin->can($slug)` — `is_super_admin` bypasses; otherwise slug lookup across the admin's roles' permissions.
- **To add permissions:** append `[$slug, $module, $description]` rows to `database/seeders/PermissionSeeder.php` (idempotent `updateOrCreate`), optionally add to a role in `RoleSeeder.php`, then `php artisan db:seed --class=PermissionSeeder`. They auto-surface in role management (`RoleController::index` → `Permission::all()->groupBy('module')`).
- **New permissions (module "Trip Operations"):** `view_trip_operations`, `manage_trip_operations`, `assign_drivers`, `manage_drivers`, `send_trip_communications`.

---

## 6. Admin UI conventions (reuse)

- Layout `resources/views/layouts/admin.blade.php`; pages `@extends('layouts.admin')`, `@section('content')`, `@section('pageTitle', ...)`. Flash + `$errors` handled by layout.
- **Sidebar** is a single flat data-driven `@foreach` array in the layout: `'group_x' => [Label, null, svg]` for section headers, `'admin.route.*' => [Label, 'route.suffix', svg]` for items (href = `route('admin.'.$suffix)`, active = `str_starts_with($current, str_replace('.*','.',$key))`). **No permission gating in the nav** (cosmetic) — enforcement is at the route layer. → Add a `group_trip_ops` section with children.
- Components: `<x-admin.filters>` (search + selects + sorts + per-page + count + slot), `<x-admin.sort-header column= label= align=>`.
- CSS (`resources/css/app.css`): `.admin-card`, `.admin-table`, `.input`, `.label`, `.btn / .btn-primary / .btn-ghost / .btn-white / .btn-danger / .btn-sm / .btn-md / .btn-lg`, `.alert-error / -success / -info`, `.status-pill`, `[x-cloak]`.
- Helpers (`app/Support/helpers.php`): `status_pill_class(string): string`, `label_case(string): string`, `money(...)`, `settings(key, default)`.
- Alpine.js 3 available (`x-data/x-show/x-model/x-cloak/@click.outside`), loaded via Vite.

---

## 7. Events / Jobs / Queue / Scheduler

- **No `app/Events`, `app/Listeners`, or `app/Jobs` exist yet**, and nothing implements `ShouldQueue`. Work is done synchronously in services + scheduled console closures.
- Queue default: `database` (`QUEUE_CONNECTION`, `jobs` table). Queued jobs are available but not currently used.
- **Scheduler + console live in `routes/console.php`** (Laravel 12, no `Console/Kernel.php`). Commands are **closures** via `Artisan::command('name', fn)->purpose(...)`; scheduled via `Schedule::command('name')->everyFiveMinutes()` etc. Existing jobs: `bookings:expire-pending` (5 min), `bookings:reconcile-failed` (10 min), `searches:prune` (daily), `whatsapp:dispatch-campaigns` (every min), `automations:run-scheduled` (09:00).
- Trip Operations follows the **same closure pattern** for `trip-ops:driver-reminders` and `trip-ops:tomorrow-plan`, resolving services with `app(...)`. To fire customer/driver comms on driver assignment we use a real Laravel **Event + Listener** (`DriverAssigned` → `SendDriverAssignmentNotifications`), introducing the first entries under `app/Events` + `app/Listeners`.

---

## 8. Audit log (reuse)

`ActivityLogger::log(string $action, ?string $module=null, ?string $description=null, array $properties=[], ?Request $request=null): void` → writes `activity_logs` (`admin_id`, `action`, `module`, `description`, `properties` json, `ip_address`, `user_agent`). Surfaced at `admin.activity.index`. Trip Operations logs with module `trip_operations`.

---

## 9. Trip Operations data model (new — additive)

Keyed on the authoritative `bookings.id`. Snapshot only operational artifacts.

| Table | Purpose | Key columns |
|---|---|---|
| `drivers` | reusable driver directory (net-new) | name, phone, alt_phone, license_number, vehicle_number, vehicle_type, vendor_name, photo_path, notes, rating, is_active |
| `trips` | operational overlay, 1:1 with a booking | booking_id (unique FK), trip_reference, status, arrival_date, departure_date, total_days, lead_customer_name, lead_customer_phone, destination_label, itinerary_version, notes(internal), created_by |
| `trip_days` | snapshotted day rows | trip_id, day_number, date, title, summary, hotel_snapshot, sort_order |
| `trip_events` | timeline items within a day | trip_id, trip_day_id, day_number, event_type, time, title, location, lat, lng, maps_url, description, visibility(customer/internal/both), sort_order |
| `driver_assignments` | driver ↔ trip/day/transfer | trip_id, driver_id, scope(entire_trip/day/transfer), trip_day_id, trip_event_id, pickup_location, drop_location, pickup_datetime, notes, status |
| `driver_assignment_histories` | reassignment audit | driver_assignment_id, from_driver_id, to_driver_id, reason, changed_by |
| `trip_operation_comms` | **idempotent** send log | trip_id, driver_assignment_id, channel, recipient_type(customer/driver), recipient, event_key (unique w/ channel+recipient), status(sent/failed/skipped), meta, sent_at |
| `trip_operation_settings` | singleton config | reminder offsets, nightly-plan toggle, auto-generate toggle (JSON `value`) |

**Operational trip statuses:** `upcoming`, `arriving_today`, `in_progress`, `completed`, `cancelled` (derived from booking status + arrival/departure dates; `cancelled` mirrors a cancelled/refunded booking).

**Idempotency:** every proactive send inserts/checks a `trip_operation_comms` row with a unique `(event_key, channel, recipient)`; a duplicate trigger short-circuits. `event_key` examples: `driver_assigned:{assignment_id}`, `customer_itinerary:{trip_id}:v{version}`, `driver_reminder:{assignment_id}:{offset}`, `tomorrow_plan:{trip_id}:{date}`.

---

## 10. What is explicitly NOT rebuilt

Bookings, payments, refunds, invoices, travellers, packages/hotels/cabs/flights catalog, hotel-per-day resolution, CRM leads/quotations, WhatsApp transport/inbox/templates/campaigns, notification templates, PDF engine, RBAC, settings, admin layout/components. Trip Operations **consumes** all of these.

---

## 11. Build order

**P0 (this pass):** migrations → models → services (TripBuilder, TripOperationsService, DriverAssignmentService, TripCommService) → `DriverAssigned` event/listener + auto-itinerary on confirmation → driver-sheet + customer-itinerary PDFs → controllers/routes/permissions/sidebar → responsive views (Today, Upcoming, Active, Timeline + builder, Driver Assignments, Drivers, Completed, Settings) → scheduler (driver reminders + tomorrow's plan) → verify + deliverables.

**P1:** operations calendar (month/week/day), reports + Excel/CSV/PDF export, itinerary versioning UI + change management, Customer-360 deep links, global search.

**P2:** drag-drop refinements, map embeds (not just links), SMS provider polish, per-driver portal.
