# Trip Operations & Arrival Management — P0 Deliverables

Module built as an **operational overlay** on the existing Leemroz Travels platform. It never
duplicates bookings, customers, payments, hotels, cabs or CRM data — every trip references its
authoritative `booking_id` and reads customer/product data through that booking. Snapshots are
stored only where operational history must survive later booking edits (day plans, hotel-of-the-night,
driver assignment history, communication log).

Status: **P0 complete.** Migrations must be run manually (see "How to deploy" at the end) because the
sandbox has no shell/artisan access.

---

## 1. Database migrations (`database/migrations/`)

| File | Table | Purpose |
|------|-------|---------|
| `2026_09_24_000200_create_drivers_table.php` | `drivers` | Reusable driver + vehicle profiles (net-new; no driver table existed). |
| `2026_09_24_000201_create_trips_table.php` | `trips` | Operational overlay per booking; status, dates, lead customer snapshot, itinerary version. |
| `2026_09_24_000202_create_trip_days_table.php` | `trip_days` | Day-by-day plan; hotel + meals snapshot per night. |
| `2026_09_24_000203_create_trip_events_table.php` | `trip_events` | Timed events (arrival/transfer/checkin/activity/meal/departure) with coords + visibility. |
| `2026_09_24_000204_create_driver_assignments_table.php` | `driver_assignments` | Entire-trip / day / transfer assignments + pickup details. |
| `2026_09_24_000205_create_driver_assignment_histories_table.php` | `driver_assignment_histories` | Reassignment audit trail (from/to driver, reason). |
| `2026_09_24_000206_create_trip_operation_comms_table.php` | `trip_operation_comms` | Idempotency + audit log for every driver/customer send. |
| `2026_09_24_000207_create_trip_operation_settings_table.php` | `trip_operation_settings` | Key/value automation + channel settings. |

All migrations are guarded with `Schema::hasTable(...)` and carry indexes on foreign keys and the
columns used by the queues (status, arrival_date, unique `(trip_id, day_number)`, unique comm key).

## 2. Models (`app/Models/`)

- `Driver` — `activeAssignments()`, `scopeActive()`, `waNumber()`, `vehicleLabel()`.
- `Trip` — status constants + `deriveStatus()`, scopes `upcoming()/status()/arrivingOn()`, relations
  `booking/days/events/customerEvents/assignments/activeAssignments/comms`, and
  `customerName()/customerPhone()/customerEmail()` (read through the booking).
- `TripDay` — `events()` ordered.
- `TripEvent` — `typeLabel()`, `isCustomerVisible()`, `mapsLink()`, `timeLabel()`.
- `DriverAssignment` — scope constants + `scopeLabel()`, `histories()`, `isActive()`.
- `DriverAssignmentHistory` — from/to driver + reason.
- `TripOperationComm` — cast `meta` array + `sent_at`.
- `TripOperationSetting` — static `get()/allSettings()` (cached 300s) `/put()` with typed DEFAULTS.

## 3. Services (`app/Services/TripOperations/`)

- `TripBuilder` — `ensureTrip(Booking)` and `generateItinerary(Trip, bool $bumpVersion=false)`; builds
  days/events from the authoritative package itinerary / booking data and bumps `itinerary_version`.
- `TripOperationsService` — `syncFromBooking()`, `refreshStatus()`, `refreshAllActiveStatuses()`,
  and the dashboard aggregates `todayKpis()`, `arrivalsToday()`, `todayTimeline()`.
- `DriverAssignmentService` — `assign()`, `reassign()` (writes history), `cancel()`; fires
  `DriverAssigned` (unless `notify=false`).
- `TripCommService` — idempotent sends: `sendDriverAssignment()`, `sendCustomerItinerary()`,
  `sendDriverReminder()`, `sendTomorrowPlan()`. Every send is guarded by a `TripOperationComm` row
  keyed on `(event_key, channel, recipient)` so nothing is sent twice across retries/scheduler runs.

Also extended: `app/Services/PdfDocumentService.php` — `driverSheet(Trip, ?DriverAssignment)` and
`customerItinerary(Trip)`.

## 4. Events / Listeners / hooks

- `app/Events/DriverAssigned.php` — `(DriverAssignment $assignment, bool $isReassign=false)`.
- `app/Listeners/SendDriverAssignmentNotifications.php` — `implements ShouldQueue`; sends the driver
  assignment message then the customer itinerary (both idempotent).
- `app/Providers/AppServiceProvider.php` — registers the listener via `Event::listen(...)` (this app
  has no `EventServiceProvider`).
- `app/Services/BookingService.php` — inside `handlePaymentSuccess()`, after the CRM hook, a guarded
  Trip Operations hook creates the trip + itinerary on confirmation (respects `auto_generate_itinerary`,
  wrapped in try/catch so it can never block a booking confirmation).

## 5. Controllers (`app/Http/Controllers/Admin/`)

- `TripOperationsController` — `today`, `upcoming`, `active`, `completed`, `show`, `regenerate`,
  `updateNotes`, `driverSheet` (inline PDF), `customerItineraryPdf` (inline PDF), `sendItinerary`,
  `export` (CSV stream), `settings`, `updateSettings`.
- `DriverController` — `index`, `store`, `update`, `toggle`, `destroy` (blocks delete when active
  assignments exist).
- `TripAssignmentController` — `index` (global queue), `store` (assign to a trip), `reassign`, `cancel`.

## 6. Views (`resources/views/admin/trip-ops/`)

All use the existing UI kit (`.admin-card`, `.admin-table`, `.status-pill`, `.input`, `.label`,
`.btn-*`, `<x-admin.filters>`, `label_case()`, Alpine) and are **fully responsive** — desktop tables
collapse to stacked cards below `md`.

- `today.blade.php` — 5 KPI cards, Arrivals Today, live Today's Timeline.
- `upcoming.blade.php` — shared list for Upcoming / Active / Completed (search, product/driver filters,
  arrival date range, per-page, CSV export, table + mobile cards).
- `show.blade.php` — trip timeline: customer + booking summary, internal notes editor, driver
  assignments (assign/reassign/cancel + history), day-by-day itinerary with Google Maps links and
  internal-event flags, communications log, and PDF/send/regenerate actions.
- `drivers/index.blade.php` — driver directory with add/edit modal, activate/deactivate, delete.
- `assignments.blade.php` — global active-assignment queue with reassign/cancel.
- `settings.blade.php` — itinerary automation, tomorrow's plan, driver reminder offsets, channels.

PDF/email templates: `resources/views/pdf/driver-sheet.blade.php`,
`resources/views/pdf/customer-itinerary.blade.php`, `resources/views/emails/trip-itinerary.blade.php`.

## 7. Routes (`routes/admin.php`)

`Route::prefix('trip-ops')->name('trip-ops.')` inside the `admin.auth` group. Static paths precede the
`{trip}` wildcard. Route names: `today, upcoming, active, completed, export, drivers.index/store/
update/toggle/destroy, assignments.index/reassign/cancel, settings, settings.update, show, driver-sheet,
itinerary, regenerate, notes, send-itinerary, assign`. Each carries the appropriate permission
middleware.

## 8. Permissions (`database/seeders/PermissionSeeder.php`, module "Trip Operations")

`view_trip_operations`, `manage_trip_operations`, `assign_drivers`, `manage_drivers`,
`send_trip_communications` — integrated into the existing RBAC (no second system).

## 9. Navigation (`resources/views/layouts/admin.blade.php`)

New `Trip Operations` sidebar group: Today, Upcoming Arrivals, Active Trips, Driver Assignments,
Drivers, Completed Trips, Trip Ops Settings.

## 10. Scheduler / console (`routes/console.php`)

- `trip-ops:driver-reminders` — every 15 min; sends the reminder for whichever enabled offset band
  (1 day / 12 h / 2 h) the pickup currently falls into. Idempotent per `(assignment, offset)`.
- `trip-ops:tomorrow-plan` — hourly; runs only in the configured `tomorrow_plan_time` hour (or with
  `--force`); sends the next-day plan to customers on active trips with events tomorrow.

## 11. Config / env (`config/services.php`)

WhatsApp templates (fall back to silent no-op when unset):
`WHATSAPP_TEMPLATE_TRIP_DRIVER_ASSIGNED`, `WHATSAPP_TEMPLATE_TRIP_CUSTOMER_ITINERARY`,
`WHATSAPP_TEMPLATE_TRIP_DRIVER_REMINDER`, `WHATSAPP_TEMPLATE_TRIP_TOMORROW_PLAN`, and
`WHATSAPP_ATTACH_TRIP_ITINERARY` (attach PDF; default on).

## 12. Backend/API work required before go-live

- Approve the four WhatsApp message templates in Meta and set the env names above (until then WhatsApp
  is a graceful no-op and email/SMS still work).
- Ensure a queue worker is running (`queue:work`, default `database` connection) for the assignment
  notification listener, and that `schedule:run` is on cron for the reminder/tomorrow-plan commands.
- SMTP must be configured/enabled (existing `MailConfigService`) for itinerary emails.

## 13. Known limitations / deferred (P1/P2)

- Drag-and-drop Timeline Builder, operations calendar (month/week/day), and Excel (xlsx) report export
  are P1/P2 — current export is CSV; itinerary regeneration rebuilds from the booking.
- Driver reminders key off `pickup_datetime` (falling back to `arrival_date`); assignments with no
  pickup time and no arrival date are skipped.
- Map links are best-effort (explicit URL → coords → location text search).

## How to deploy (manual — no shell in this environment)

```
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\PermissionSeeder
php artisan queue:work        # or ensure your existing worker picks up the new listener
# ensure cron runs: php artisan schedule:run every minute
```

Assign the new `Trip Operations` permissions to the relevant roles in Admin → Staff → Roles.

