# Affiliate sources

1. In **Admin → Brands**, create or select the real brand; in **Products**, set its official product URL and your own affiliate tracking URL. The source sync never overwrites the tracking URL.
2. Open **Affiliate Agent → Sources → Add source**. Enter the brand ID, name, source type, exact HTTPS URL, and comma-separated allowed hostnames. Add separate sources for product pages, features, documentation and the affiliate resource page.
3. Click **Test** to verify host resolution. Click **Sync Now**. Run the `agent-sources` queue worker. Inspect sync status/errors and expand each imported document.
4. Click **Approve factual source** for verified text. Only approved documents from enabled sources feed automatic research. Import CSV using columns `title,body,source_url`, or choose MANUAL for brand notes; imported entries still require approval.
5. For WEBHOOK, set a secret in the source credentials. POST JSON `{ "title": "…", "body": "…", "source_url": "https://…" }` to `/api/v1/affiliate-agent/webhooks/{source_id}` with `X-Agent-Signature` equal to hex HMAC-SHA256 of the raw body using that secret. It creates pending documents.

Website and documentation sync fetch only the configured URL, check robots.txt, pin a public IP, reject redirects, and run at most once per configured frequency. They do not crawl arbitrary links. API sources currently perform a trusted GET with optional bearer token; use manual or CSV import for APIs requiring custom payloads or pagination. Do not approve outdated or unverifiable claims.
