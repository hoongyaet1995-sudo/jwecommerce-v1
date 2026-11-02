# Catalog dual-site release — 2026-11-02

User approved synchronization of the accepted Catalog phase to GitHub, Ecom and JW Corporate.
Scope: Category/Collection full-page editors, responsive banner controls and all-theme rendering, maximum three-level category hierarchy, paginated product linking/unlinking, storefront URL and copy actions. Existing business data and site configuration retained. No seeders or .env changes.
Local release gate: 14 PHP tests / 185 assertions; 5 frontend checks; Admin build passed. Existing sample-asset and bundle-size warnings remain. Normal localhost browser evidence is in the linked phase records. QA draft categories and product assignment tests are local-only; no data import.
Baseline SHA 2d215a2b049adbd9c59ad9f848de4a6f8585e755 matched GitHub main and both servers before release. No tracked server modifications detected. Existing untracked server files retained.
Migration: only database/migrations/2026_11_02_000002_add_catalog_banner_settings.php, nullable JSON columns; preserve existing desktop image fields.
Deployment verification to be recorded after synchronization. Authenticated production checks depend on existing browser session; no paid calls or content publication.
