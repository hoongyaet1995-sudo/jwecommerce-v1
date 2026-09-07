# ZIP 32 merchant import release

User approved synchronization on 2026-09-07 after the association/import release scope was proposed.

## Enabled scope

- Official mapping entry uses ZIP 32 reviewed product/batch import and variant creation/linking adapters.
- Import remains controlled by the existing server import capability. New products are drafts and physical inventory starts at zero.
- Mapping conflicts and repeated imports are validated server-side; import receipts retain identity.
- Existing OAuth, catalog and order readers remain unchanged in purpose.

## Not enabled

Publishing, AI suggestions, fulfillment and inventory writes are not enabled by this release. Publishing task/metadata infrastructure remains incomplete. The metadata-only wizard entry is restricted to localhost with journeyPreview=32; it cannot submit a listing.

## Local evidence

- Laravel Marketplace/Shopee regression: 154 tests, 972 assertions passed.
- Frontend selected journey/adapter regression: 24 tests passed; targeted TypeScript check passed.
- Vite production build succeeded (2867 modules); existing large-chunk warning remains.
- Actual local MySQL two-process new-parent import test: one variant, receipt, mapping and audit event; zero stock. Its disposable schema was removed; application data was unchanged.
- Authenticated localhost official mapping route loaded after the new build. It has zero connected stores, so this browser check proves shell/empty-state behavior, not a complete real-store import journey.
- Source/converted isolated import screenshots were captured earlier under output/playwright/marketplace32-*.png. Real connected-store browser acceptance remains required after deployment.

## Release checks remaining

Confirm committed source and generated assets, push main, deploy the exact SHA, apply the three additive Marketplace migrations and verify live HTTPS. Do not report release completion before those checks. No seller-side write is necessary for the initial live smoke check.
