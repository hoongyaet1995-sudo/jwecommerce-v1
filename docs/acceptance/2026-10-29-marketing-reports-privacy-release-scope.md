# Pending release: conversion reports and privacy closeout

Date: 2026-10-29 Asia/Kuala_Lumpur. Baseline f01c7756cb5ff7d3c54b202e37bf7410af7304cf. NOT committed/pushed/deployed; user sync approval required.

Approved implementation: existing three Analytics tabs and unified icon Conversion Events; owner/merchant-protected paginated enquiries/orders/source drilldown; per-currency gross/refund/net and CSV. Privacy choices Save/Cancel/expiry, session context withdrawal, current-URL-safe cleanup; daily retention of optional events (90 days) / raw redirects (7 days), retire old visitor linkage without deleting business facts. Legacy explicit session choice gets bounded validity; no default-on consent.

Validation: 36/281 report regression; 34/281 privacy boundary regression; final 9/83 privacy + reports in 2.845s. Normal local actual enquiry and privacy UI, fake paid/refund/failure rendering. Existing DB retention dry-run 0/0 only. No real charge/refund/publication.

Release has no database migrations and no compiled frontend build. Do not copy .env, tmp diagnostics, work/ previews, unrelated dirty app/inventory/theme changes or missing homepage build assets. Preserve existing accounts, Hub callback, old project/version/order data. On release recheck config defaults and scheduler lock support; preserve environment-specific collection switch. Verify local/GitHub/Hostinger exact SHA and HTTPS after approval.

## Exact source scope (SHA-256 before release)

| File | SHA-256 |
| --- | --- |
| app/Http/Controllers/Admin/MarketingAnalyticsController.php | 6b4799b269a0f17afe11f5cf7e70f787d4068ca078b7873634b64af495eb80a7 |
| app/Http/Controllers/MarketingTrackingController.php | 3179134f71b69fe6089ffae1fa3540d5db7526bd30f580108280413169fe09ad |
| app/Http/Middleware/MarketingStorefrontTracking.php | c56e6881f5d2c0ce003e336896b7b59e2816bfed8f344fe7f706a0b1802b544e |
| app/Services/Marketing/MarketingAnalytics.php | 6da9765abee523312fe7b23855fbc0ff6941c8551782d83ceb024648f4282bda |
| app/Services/Marketing/MarketingAttribution.php | a21678ce9183c808851b7fe87fa5cd371d687024b08624392188fd590f311184 |
| app/Services/Marketing/MarketingConversionDetails.php | df804115c75d48d102422fab4455c29978513493c577fba1690906600978d1c2 |
| app/Services/Marketing/MarketingTrackingPrivacy.php | ab496a01a4a5d94460e9593135342aa90e38c7ca83171f9266495b83431c769e |
| config/marketing.php | 92f0fd94d6b8a8d154e6d79237c78048763f0d3d68ee9a97b6212d49aea39c8f |
| routes/marketing.php | 255e1fcfe213c2eab4be3ae62b62c9503477815a4d581ca2b3b02162940b1a66 |
| routes/console.php | 7a8be0ec082a1d96aec1151f45439eb32bfefcdd89b9651d05c62a43ea06fac7 |
| public/marketing-studio/campaigns.js | 86da0c4b620e9a80355b538fcb163da577472b52d3d4e74cc8e587008a3897c7 |
| public/marketing-studio/conversion-reports.js | 7f126c977bc5ee39a78f2141713bbd479e7159a145e18120cfedfd6c49189c6f |
| public/marketing-studio/conversion-reports.css | 85dbb7d56fa4aad6a03ee42cac2561d615c1d84060f4de7219f0646c9800d349 |
| public/marketing-studio/tracking.js | fac07c7da804e966c73c260b9e9aa14eee0859141d482033e778b086a60f6513 |
| resources/views/marketing/campaigns/index.blade.php | 72bca81fdcfc9c619d7f8f4c81b22b5ecf7419f6a37d84f1726d8b4e5a04810f |
| resources/views/marketing/tracking-consent.blade.php | 12218ecb185a6fab49d6a1880ced3dc8fc6818b3c1e65d3cc696761eb804c5f1 |
| tests/Feature/MarketingConversionDetailsTest.php | acbf8cf51fdec606d2428f912d72f2227610363605b2f99b2b1a8fe9c6dc095e |
| tests/Feature/MarketingPrivacyCloseoutTest.php | 928dd352a8bbb45b70b29180363a46c6cf30e59b651407dcfe3febf69372a7ad |
| docs/acceptance/2026-10-29-campaign-integration-readiness.md | 0bb38ff1abb51893f23001287501bbd1a31287113e1bf7f9551580c726c96ede |
| docs/superpowers/plans/2026-10-29-campaign-integration-implementation.md | c3b5a13d94dd04a8037a4934d4c639618df757de4fa8bfa467b7ca1ff1d5ef5a |
| docs/superpowers/plans/2026-10-29-conversion-report-integration.md | e10a2f644fb4ed6c3e9480e9474d45c4bead06ad5c2b01c2acbdfcb024e2af32 |
| docs/superpowers/plans/2026-10-29-marketing-privacy-closeout.md | 663dec7517a91f1e8b93b8109655da27af77335a7ea4374b2bf3c271e6e96990 |
| docs/acceptance/2026-10-29-campaign-integration-ledger.md | 15fd8bb91dff3562105d20a152227d5eb28d86a7bf1687c782cd380c38f4a1cb |


## Approved release and live verification — 2026-10-29
User approved synchronization. Scoped 24-file code release 2e1dcd9f4b9d11673ce6878b930d32509ee05713 pushed to GitHub main and deployed to Hostinger by ff-only pull and optimize:clear. All 23 source hashes matched the accepted manifest before copying; .env, generated fixtures, inventory/theme and unrelated dirty source excluded. No migration or frontend build.
Normal authenticated live Analytics renders three tabs and unified icon Conversion Events: existing qualified session 1, enquiry 1, quotation sent 1, accepted 1 and paid orders 0; no paid revenue. Live enquiry ENQ-20261029-4LTZLFI8H6 drill-down resolves original Facebook JW Studio campaign/post source, product and QT-20261029-JUD4EGUE, with existing Admin enquiry link. No record edits. Live product page retains variant=160, has Privacy choices dialog and previous choice; opened/read then Cancel only, no preference change. Same existing account connections and Hub callback source untouched.
Live report JS/CSS and storefront HTTPS return 200; unauthenticated Analytics remains login-protected 302. Online cleanup was --dry-run only (events 0 / retired 0, event days 90 / request days 7); no manual deletion. Daily retention command registered, not manually executed. No publication, financial action or paid API. Production collection setting preserved; local remains disabled. Prior local homepage asset 503 is separate; production homepage returns 200.
This evidence-only update follows the code release; final local/GitHub/Hostinger SHA parity is checked after deploying this record. All prior pending-sync statements describe earlier checkpoints and are superseded by this release section. Next work is Email/WhatsApp CRM audit/spec; no automatic recipient/credential import or sends authorized.
