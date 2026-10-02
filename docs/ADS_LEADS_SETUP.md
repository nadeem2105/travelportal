# Meta & Google Ads — Lead Ingestion

Leads from Meta Lead Ads and Google Ads Lead Form Extensions are ingested via webhooks and become CRM leads with full ad attribution (campaign / adset / ad / gclid / fbclid), deduped by phone/email like every other lead.

## Meta Lead Ads

**Webhook URL:** `https://YOUR_DOMAIN/webhooks/meta`

`.env`:
```dotenv
META_LEADS_ENABLED=true
META_LEADS_API_VERSION=v21.0
META_LEADS_VERIFY_TOKEN=<any random string you choose>
META_LEADS_APP_SECRET=<Meta app secret>
META_LEADS_PAGE_ACCESS_TOKEN=<long-lived Page access token>
```

Setup in Meta:
1. In the Meta app → Webhooks, add the callback URL above with `META_LEADS_VERIFY_TOKEN`; subscribe to the **`leadgen`** field of the Page.
2. Subscribe the app to the Page (Page must be connected to the Lead Ads form).
3. The **Page access token** must have `leads_retrieval` permission — the app fetches each lead's full field data from the Graph API when a `leadgen` event arrives.

Field mapping (Meta form field name → CRM): `full_name`/`first_name`+`last_name` → name, `phone_number` → phone, `email` → email, `destination`/`city` → destination. Any other fields are appended to the lead notes. Attribution: source = Meta Ads, `external_lead_id`, `external_campaign_id`, `external_adset_id`, `external_ad_id`, `form_id`, utm_source=meta.

Requests are authenticated by the `X-Hub-Signature-256` HMAC (app secret) and deduped by leadgen id via `WebhookEvent`.

## Google Ads Lead Form Extensions

**Webhook URL:** `https://YOUR_DOMAIN/webhooks/google-leads`

`.env`:
```dotenv
GOOGLE_LEADS_ENABLED=true
GOOGLE_LEADS_KEY=<the Webhook key you set on the Google lead form>
```

Setup in Google Ads: on the Lead Form asset → "Lead delivery / Webhook integration", set:
- **Webhook URL:** the URL above
- **Key:** the same value as `GOOGLE_LEADS_KEY`

Google posts a JSON payload; the app validates `google_key`, maps `user_column_data` by `column_id` (`FULL_NAME`, `PHONE_NUMBER`, `EMAIL`, `CITY`, `FIRST_NAME`/`LAST_NAME`…), and creates a lead. `gcl_id` is stored as the lead's `gclid`, `campaign_id` as `external_campaign_id`, source = Google Ads. Test submissions (`is_test`) are accepted with 200 but not stored; duplicate `lead_id`s are ignored.

## Notes

- Both webhook paths are CSRF-exempt (`webhooks/*`) and authenticated by signature/key.
- Leads created this way flow through the normal `LeadService` — so contact dedup, pipeline stage seeding, assignment, scoring, and **automation workflows** (trigger `lead_created`) all apply.
- After editing `.env`, run `php artisan config:clear`.
