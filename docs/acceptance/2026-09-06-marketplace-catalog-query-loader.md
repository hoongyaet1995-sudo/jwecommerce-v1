# Marketplace catalog query loader — local checkpoint

- Added `useMarketplaceCatalog` for grouped-product and flat-variant server pagination.
- Preserves server totals and groups only the returned flat-page variants for the source view model.
- Discards stale responses when filters change or the consumer unmounts.
- Clears stale rows while loading and exposes failures explicitly; failures are not an empty-catalog success.
- Direct/self-store channels are rejected instead of silently broadening the query to all marketplaces.
- Verification: four Vitest files, six tests passed (loader, view adapter, source pagination and async mapping modal). TypeScript and diff checks passed after narrowing the source channel union.
- This hook is not yet mounted in the official Mapping page. Remaining: operational source context, server-side conflict presentation, unsupported-action guards, browser acceptance. No new deployment or remote write was performed at this checkpoint.
