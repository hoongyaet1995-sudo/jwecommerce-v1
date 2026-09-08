# Shopee console read-only readiness observation

Observed 2026-09-06 in the user's existing authenticated Chrome tab:
`https://open.shopee.com/console/app/230736`.
No edits, confirmation, authorization, save, API calls or seller login were performed.
No key values are recorded in this report.

## Current console evidence

- Application: JW Studio; status Online; category ERP System.
- Test Partner ID: 1232619; Live Partner ID: 2033707.
- Test Redirect URL Domain: `https://ecom.jwaistudio.online`.
- Live Redirect URL Domain: `https://ecom.jwaistudio.online`.
- Business Product URL: `https://ecom.jwaistudio.online`.
- Live key expiry displayed: 2026-10-26 23:59 UTC+08:00.
- Access to Sensitive Data: No access. Exact endpoint/field consequences remain unverified.
- IP whitelist: Disabled; declared IP assets carry a more-than-90-days confirmation reminder.
  Declared IPs were not verified against the Ecommerce deployment and were not confirmed or changed.

## Remaining real-authorization gate

The local `.env` uses sandbox credentials but APP_URL is localhost while callback points to HTTPS production.
Local state/session/database cannot be presumed shared with production. The authorization readiness guard therefore blocks local initiation.
An approved, tested same-instance HTTPS deployment and its own encrypted-token/state storage must be ready before Seller authorization.
Console domain configuration alone does not prove callback deployment, credential validity, granted API permissions, or successful store authorization.

Previous uncertainty about whether the console redirect domains had been migrated is resolved by this observation.
Real OAuth, catalog, order, publish, fulfillment and inventory acceptance remain incomplete.
