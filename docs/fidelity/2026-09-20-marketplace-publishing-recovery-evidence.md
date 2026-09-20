# Marketplace publishing recovery evidence — 2026-09-20

## Scope

- Existing publishing attempts expose their saved task ID, status, creation certainty, safe failure reason, stage, and server-approved next actions.
- Product selection opens the saved task instead of disabling the employee at `Review existing task`.
- New failures persist an allowlisted explanation. Historical failures without diagnostics say that the evidence was not saved.
- An uncertain outcome can start a new full review only after an employee checks Seller Center, accepts duplicate risk, records a reason, and receives a one-use 30-minute authorization.
- A recovery attempt preserves `previous_task_id`; the old task and mappings remain unchanged.
- Marketplace publishing remains restricted to Shopee and Lazada. EasyParcel is not read or invoked by this flow.

## Safety properties

- Task/status GET endpoints make no provider request and do not dispatch a task.
- Raw provider bodies, credentials, tokens, and exception messages are not returned in task diagnostics.
- A consumed, expired, wrong-actor, changed-revision, or superseded recovery authorization cannot create another task.
- Provider creation is not called by automated acceptance tests.

## Local verification

- `php artisan test tests/Feature/MarketplacePublishingRecoveryTest.php tests/Feature/MarketplacePublishingStatusTest.php tests/Feature/MarketplacePublishingTaskTest.php`
- `npm run test -- --run src/previews/marketplace32/PublishProductModal.test.tsx src/integration/submitLazadaReview.test.ts src/integration/submitShopeeReview.test.ts`
- `npm run build` in `resources/admin`
- Real MariaDB migration applied after shortening explicit foreign-key names to fit the 64-character limit.

The scoped tests and production build pass. The repository-wide `npm run lint` remains red on pre-existing Admin type drift (AI guardrails, catalog/review models, marketplace preview model mismatches, shipping preview types, and motion wrapper props); the build reports no new compile failure from this phase.

Browser acceptance of the protected localhost route remains unavailable without an authenticated local admin session. No real marketplace publication was used as a test action.
