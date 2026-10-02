# Connecting Google Ads & Meta Ads

Credentials are managed entirely in the admin UI and stored **encrypted in the database**
(`marketing_provider_settings`) — nothing goes in `.env`. Per-connection OAuth tokens are
stored (also encrypted) in `marketing_connections`. Secrets are never shown back in full
and are never exposed in the UI.

Two screens are involved:

- **Admin → Marketing → Ad Accounts → Manage credentials** (`/admin/marketing/settings`) —
  enter the provider app credentials.
- **Admin → Marketing → Ad Accounts** (`/admin/marketing/accounts`) — connect, check, and
  sync accounts.

> **What works today vs. later**
> - **Credential storage + OAuth connect** (both providers): fully implemented.
> - **Meta account import** and **connection check** (real Graph call): implemented.
> - **Google account import** and **campaign publish/activate/pause/metrics**: require the
>   official Google Ads API SDK / Meta advanced access; these raise a clear "not wired yet"
>   message instead of faking success. See [Current limitations](#current-limitations).

---

## 1. Get provider credentials

### Google Ads

| Item | Where to get it |
|------|-----------------|
| OAuth **Client ID** & **Client secret** | [Google Cloud Console](https://console.cloud.google.com/) → APIs & Services → Credentials → *Create OAuth client ID* (Web application) |
| **Developer token** | [Google Ads API Center](https://ads.google.com/) → Tools → API Center (under your manager/MCC account) |
| **Login customer ID** | Your Google Ads manager (MCC) account ID, digits only |

Setup steps in Cloud Console: enable the **Google Ads API**, configure the OAuth consent
screen (add yourself as a Test user), create a **Web application** OAuth client, and add
your redirect URI (shown pre-filled on the settings screen), e.g.:

```
https://YOUR_HOST/admin/marketing/google/callback
```

Google allows `http://localhost:8000/...` as a redirect URI for local development.

### Meta Ads

| Item | Where to get it |
|------|-----------------|
| **App ID** & **App secret** | [Meta for Developers](https://developers.facebook.com/) → My Apps → *Create App* (Business) → Settings → Basic |
| A **Facebook Page** + **Ad account** | [Meta Business Suite](https://business.facebook.com/) |

In the app: add the **Facebook Login** and **Marketing API** products, and under Facebook
Login → Settings add to **Valid OAuth Redirect URIs** (must be **HTTPS**):

```
https://YOUR_HOST/admin/marketing/meta/callback
```

Requested scopes: `ads_management`, `ads_read`, `business_management`, `leads_retrieval`,
`pages_show_list` (advanced access needs Meta App Review before it works for non-admins).

---

## 2. Enter credentials in the admin UI

1. Go to **Ad Accounts → Manage credentials** (`/admin/marketing/settings`).
2. Fill the provider's fields. The **Redirect URI** field is pre-filled with the correct
   callback for your host — copy it into the provider console exactly.
3. Tick **Enabled**.
4. Click **Save**. Secrets are encrypted before storage. Re-opening the form shows a
   "(saved ••••1234)" hint for secret fields — leave them blank to keep the stored value,
   or type a new value to replace it.

No `php artisan config:clear` is needed — settings are read live from the database.

---

## 3. Connect an account

1. Go to **Ad Accounts** (`/admin/marketing/accounts`). Once credentials are saved and
   enabled, the provider card shows **Configured** and the **Connect** button is active.
2. Click **Connect Google Ads / Connect Meta Ads**. The app generates a CSRF `state`,
   stores it in session, and redirects you to the provider's consent screen.
3. Approve access. The provider redirects back to the callback URL; the app verifies the
   `state`, exchanges the `code` for tokens, and saves an encrypted `marketing_connections`
   row with `status = connected`. For Meta it also imports your ad accounts immediately.

---

## 4. Check whether an account is connected

On the **Ad Accounts** page each connection shows a live status line:

- **● Connected** (green) — token stored and not expired.
- **● Connected (token expired)** (amber) — reconnect to refresh.
- **○ disconnected** — not usable; reconnect.

It also shows token expiry ("expires in …") and last sync time.

Two buttons per connection:

- **Check** — runs a real verification. For **Meta** it calls the Graph API
  (`me/adaccounts`) and reports how many ad accounts are visible; for **Google** it
  validates token freshness / refresh-token presence (a full API call needs the Ads API
  SDK). The result appears as a success or error banner — it never fakes a positive.
- **Sync accounts** — re-imports the ad-account list for that connection.

---

## 5. Exposing HTTPS locally (for Meta)

Meta's OAuth redirect must be HTTPS. Use a tunnel in dev:

```bash
ngrok http 8000
```

Put the resulting `https://xxxx.ngrok-free.dev/admin/marketing/meta/callback` in both the
settings screen's Redirect URI field and the app's Valid OAuth Redirect URIs, and set
`APP_URL` to the same host. Google Ads can use `http://localhost:8000` directly.

---

## 6. Troubleshooting

| Symptom | Cause / fix |
|---------|-------------|
| Card still says "Not configured" | Required secrets missing or **Enabled** not ticked on the settings screen. |
| "OAuth state mismatch" | Session/cookie lost between redirect and callback — retry; ensure `APP_URL` matches the host you're browsing. |
| "redirect_uri_mismatch" (Google) | The Redirect URI in settings must **exactly** equal one registered in Cloud Console. |
| "URL blocked" (Meta) | Redirect URI not in Valid OAuth Redirect URIs, or not HTTPS. |
| Connected but "0 accounts imported" | Expected for Google today (see limitations); for Meta, ensure the user has an ad account and granted permissions. |
| Check says token expired | Reconnect. Google stores a refresh token; Meta long-lived tokens last ~60 days. |

---

## Current limitations

The credential store, OAuth connect, Meta account listing and the connection check are
live. The following still return a clear "not wired yet" message rather than pretending to
work:

- **Google account import** — needs `googleads/google-ads-php` with the developer token +
  `login-customer-id`.
- **Campaign publish / activate / pause / budget update / metrics fetch** (both providers)
  — needs the platform write clients and (for Meta) advanced access via App Review.

Until then you can: store credentials, connect accounts, import Meta ad accounts, check
connection health, build campaign **drafts**, and use the AI creator. Publishing a draft to
the live platform surfaces the "not available yet" message by design — no fake spend.
