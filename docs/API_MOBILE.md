# Mobile App API (v1)

Base URL: `https://<your-domain>/api/v1`

This is the JSON API consumed by the **customer** mobile app. It is additive and
isolated from the website/CRM/admin — it reuses the same services, models and
database, but the web app and admin panel continue to work exactly as before.

## Conventions

**Response envelope.** Every endpoint returns the same shape:

```json
// success
{ "success": true, "message": "…", "data": { … } }

// error
{ "success": false, "message": "…", "errors": { "field": ["…"] } }
```

`errors` is only present for validation failures (HTTP 422).

**Auth.** Bearer token (Laravel Sanctum). Send it on protected routes:

```
Authorization: Bearer <token>
Accept: application/json
```

Tokens are returned by the register / login / OTP / social endpoints as
`data.token`. Store securely on device; call `POST /auth/logout` to revoke.

**Rate limits.** All routes are throttled `60/min`. OTP request/verify,
forgot/reset password are additionally throttled `6/min` to prevent abuse.

**Money & prices.** The client NEVER computes payable amounts. Prices,
discounts and payment order amounts are always calculated server-side; the app
displays what the server returns.

---

## Bootstrap

### `GET /app-config` (public)
Everything the app needs on cold start.

```json
{
  "app": { "name","logo","currency","currency_symbol" },
  "support": { "phone","email","whatsapp","address" },
  "features": {
    "auth": { "email_password": true, "phone_otp": bool, "google": bool, "apple": bool },
    "payments": { "enabled": bool, "provider": "razorpay", "currency": "INR" }
  },
  "banners": [ { "id","title","subtitle","image","link_url","button_text","position" } ],
  "sections": [ { "type","title" } ],
  "destinations": [ { "id","name","slug","region","image","is_featured" } ],
  "offers_available": 3
}
```

Use `features` to show/hide login buttons and the pay button — they reflect
what is actually configured in the admin panel.

---

## Authentication

### `POST /auth/register`
Body: `name, email, phone?, password (min 8, confirmed)`.
Returns `{ token, user }`. HTTP 201.

### `POST /auth/login`
Body: `email, password, device_name?`.
Returns `{ token, user }`. 403 if the account is deactivated.

### `POST /auth/logout` (auth)
Revokes the current token.

### `POST /auth/change-password` (auth)
Body: `current_password, password (confirmed)`. Revokes other tokens, keeps the
current one.

### `POST /auth/forgot-password` (public, 6/min)
Body: `email`. Emails a reset code. Always returns success (no account
enumeration).

### `POST /auth/reset-password` (public, 6/min)
Body: `email, code, password (confirmed)`. Verifies the emailed code and sets
the new password.

### Phone OTP — `POST /auth/otp/request` (public, 6/min)
Body: `phone`. Sends an SMS OTP via the active provider (Fast2SMS / MSG91 /
Twilio, controlled from Admin → SMS & OTP). 503 if SMS is disabled, 429 on
cooldown.

### `POST /auth/otp/verify` (public, 6/min)
Body: `phone, code, name?, device_name?`. Returns `{ token, user, is_new }`.
Creates a lightweight account on first login.

### Social — `POST /auth/social/{provider}` (public)
`{provider}` = `google` | `apple`. Body: `id_token, name?, device_name?`.
The server verifies the token with Google/Apple, then matches or creates the
account. Returns `{ token, user, is_new }`.

---

## Catalogue (public)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/destinations` | List destinations |
| GET | `/packages` | List packages (filters/paginated) |
| GET | `/packages/{slug}` | Package detail |
| GET | `/flights/search` | Search flights |
| POST | `/flights/quote` | Price a flight selection |
| GET | `/hotels/search` | Search hotels |
| GET | `/hotels/{slug}` | Hotel detail |
| GET | `/hotels/{slug}/rooms` | Room types & pricing |
| GET | `/cabs/search` | Search cabs |

---

## Content & engagement

### `GET /offers` (public)
Active promotional offers. `GET /offers/{offer}` for a single offer (404 if not
active).

### `GET /reviews?type=package&id=12` (public)
Approved reviews for a product + `summary { count, average }`. `type` =
`package` | `hotel`. Supports `per_page` (1–50).

### `POST /reviews` (auth)
Body: `type, id, rating (1–5), title?, content (10–2000)`. One review per user
per product (409 otherwise). Marked `verified_booking` when the user has a
confirmed/completed booking. Starts as `pending` for admin moderation — it does
not appear publicly until approved.

### `GET /wishlist` (auth) · `POST /wishlist/toggle` (auth)
Shared with the website. Toggle body: `type` (`package`|`hotel`|`destination`),
`id`. Returns `{ status: added|removed, in_wishlist }`.

### Notifications (auth)
| Method | Path | Notes |
|--------|------|-------|
| GET | `/notifications` | `unread_only`, `per_page` query params; returns list + `unread_count` |
| GET | `/notifications/unread-count` | Badge count |
| POST | `/notifications/{id}/read` | Mark one read |
| POST | `/notifications/read-all` | Mark all read |

### `POST /coupons/apply` (public or auth)
Body: `code, amount, product_type` (`package|hotel|flight|cab`). **Preview
only** — returns `{ code, discount, description, payable }`. The authoritative
discount is re-applied server-side at booking/payment time; the client cannot
fake a discount.

### `POST /leads` (public or auth)
Enquiry / get-a-quote / callback. Body: `name, phone, email?, destination?,
product_type?, travel dates, pax counts, budget?, message?, marketing_opt_in?,
whatsapp_opt_in?`. Funnels into the CRM (dedup, attribution, assignment,
scoring, automation) exactly like the website. Returns `{ lead_number }`.

---

## Bookings (auth)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/bookings` | User's bookings |
| GET | `/bookings/{booking}` | Booking detail (ownership enforced) |
| POST | `/bookings` | Create a booking |
| POST | `/bookings/{booking}/cancel` | Cancel |

## Native payment — Razorpay (auth)

In-app payment (no web handoff). All amounts are server-authoritative and every
call is ownership-scoped to the logged-in user.

### `POST /bookings/{booking}/payment/create-order`
Creates a Razorpay order for the booking's payable amount. Returns
`{ gateway, order, booking_reference }`. The `order` contains the public
`key_id` only — used to open the Razorpay SDK in-app. 409 if already
confirmed/completed, 422 if not payable.

### `POST /bookings/{booking}/payment/verify`
Body: `razorpay_order_id, razorpay_payment_id, razorpay_signature` (from the
Razorpay SDK callback). Server verifies the HMAC signature and confirms the
order belongs to this booking, then finalizes. Returns booking status.

### `GET /bookings/{booking}/payment/status`
`{ booking_status, payment_status, paid_at, total }`.

---

## User profile (auth)

| Method | Path |
|--------|------|
| GET | `/me` |
| GET | `/user/profile` · PUT `/user/profile` |
| GET | `/user/saved-travellers` · POST `/user/saved-travellers` |

---

## Server setup & operations

**No new migrations are required** — the API reuses existing tables (`users`,
`bookings`, `payments`, `otps`, `wishlists`, `reviews`, `offers`,
`user_notifications`, `banners`, `homepage_sections`, `destinations`,
`integration_settings`).

### SMS / OTP providers
Configure from **Admin → Settings → SMS & OTP** (encrypted DB store) or via
`.env` fallback:

```
SMS_ENABLED=true
SMS_PROVIDER=fast2sms          # fast2sms | msg91 | twilio
# Fast2SMS
FAST2SMS_API_KEY=…
# MSG91
MSG91_AUTH_KEY=…
MSG91_TEMPLATE_ID=…
# Twilio
TWILIO_SID=…
TWILIO_AUTH_TOKEN=…
TWILIO_FROM=+1…
```

### Social login
```
GOOGLE_CLIENT_IDS=<android-id>,<ios-id>,<web-id>   # comma-separated audiences
APPLE_CLIENT_IDS=<bundle-id>,<service-id>
```

### Commands to run (on the Windows host)
Only needed after pulling these changes; no schema changes:

```
php artisan config:clear
php artisan route:clear
php artisan optimize:clear
```

If `.env` was edited, also run `php artisan config:cache` in production.
No `npm run build` is needed — the API adds no frontend assets.

### Verify routes are registered
```
php artisan route:list --path=api/v1
```

---

## Security notes

- Protected routes require `auth:sanctum`; ownership is enforced inside booking,
  payment, wishlist and notification handlers (a user can only touch their own
  records).
- OTP and password-reset endpoints are throttled `6/min` on top of the global
  `60/min` to blunt SMS/email abuse and brute force.
- Only the **public** Razorpay `key_id` is ever sent to the client; the secret
  key and all provider credentials stay server-side (encrypted at rest in
  `integration_settings`).
- Payable amounts are always recomputed server-side — frontend prices are never
  trusted.
- Forgot-password does not reveal whether an email exists (no account
  enumeration).

