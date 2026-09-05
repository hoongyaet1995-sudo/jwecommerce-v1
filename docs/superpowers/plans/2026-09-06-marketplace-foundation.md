# Marketplace Operational Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Marketplace demo-backed official Admin surfaces with the approved ZIP 28 presentation, durable local settings and mappings, real JW catalog data, and truthful unavailable provider actions.

**Architecture:** A dedicated Marketplace runtime provider consumes protected Laravel endpoints and existing catalog data. Laravel owns validation and MySQL ownership. Platform-specific authentication, catalog retrieval, import and publication adapters remain unimplemented and fail closed; no demo success handlers are used by official routes.

**Tech Stack:** Laravel/PHP/MySQL, existing React Admin, TypeScript, Tailwind, Vitest and browser acceptance.

**Spec:** User-approved six-point scope in this task (2026-09-06): official entry, remove demo controls/data, real JW catalog and durable store/mapping records, truthful empty states, gate provider actions, final readiness assessment. Existing source component presentation remains authoritative except explicitly approved demo removal and availability states.

## Global constraints

- Work first in `C:/xampp/htdocs/jwstudio-ecommerce`.
- Preserve real JW products, orders and warehouse inventory. No broad reset or destructive seeding.
- No commits, GitHub pushes or Hostinger deployment without separate synchronization approval.
- No Shopee/Lazada/TikTok/Shopify API calls in this phase.
- Do not interpret saved store metadata or credentials as successful authorization.
- Do not mark mapping changes as price/stock synchronization.
- Credentials must not be reflected into list APIs, logs or browser storage.
- Remove demo controls from official employee UI; test fixtures may remain isolated in test/preview tooling.

## Data contract

1. Store configuration: internal ID, platform allowlist, external shop identity (nullable before auth), display name, region, environment, requested preferences, authoritative connection state. Initial connection state is `pending_auth`; no connected state can be supplied by a client. Unique external identity is scoped to platform and environment. Credential management is provider-specific and remains unavailable until the corresponding adapter is designed; do not offer save-success for unsupported credential fields.
2. External catalog records: store FK, immutable external product/variant IDs, observed listing data and retrieval timestamps. No public endpoint fabricates or manually creates fetched listings. Empty until a provider adapter supplies real data.
3. Variant mappings: external variant FK, JW product variant FK, timestamps and staff actor. Unique external variant constraint. Multiple external variants may point to one JW variant; no uniqueness constraint on JW variant. Parent JW product must match selected variant.
4. Mapping mutations run in a transaction, validate live referenced entities and record an audit transition. Unlinking removes the association, never the products or historical order line snapshots.
5. Overview counts are computed from these records; no fake health percentages, sync timestamps, products or order counts.

## Task 1 — Durable backend and authorization

**Files:** new migration under `database/migrations/`; `app/Models/MarketplaceStore.php`, `MarketplaceListingVariant.php`, `MarketplaceVariantMapping.php`; `app/Services/Marketplace/MarketplaceFoundationService.php`; `app/Http/Controllers/Admin/MarketplaceApiController.php`; `tests/Feature/MarketplaceFoundationTest.php`; protected routes in `routes/web.php`.

- [ ] Add tests first for guest rejection, empty initial data, store metadata persistence, client-forged connected state rejection, cross-product variant rejection, one external variant/one mapping, multiple external variants/one JW variant and unlink safety.
- [ ] Run `C:/xampp/php/php.exe artisan test --filter=MarketplaceFoundationTest` and observe expected missing-endpoint failures.
- [ ] Implement protected store CRUD and variant mapping endpoints with database-backed state and transactions.
- [ ] Provide read contracts for stores, overview and paginated external listings. With no provider imports, external listing collections are truthfully empty.
- [ ] Reject unsupported remote operations with a stable `provider_not_available` error; never fall back to a simulated success.
- [ ] Rerun the feature tests and check database constraints with two stores containing identical external SKU text.

## Task 2 — Source-faithful official Admin integration

**Files:** selected components under `resources/admin/src/components/marketplace/`; new `resources/admin/src/integration/marketplaceData.ts`; new Marketplace runtime provider; `resources/admin/src/App.tsx`; `resources/admin/src/integration/adminRoutes.ts`; sidebar/header mapping entry and route type; `routes/web.php`.

- [ ] Add failing tests for the new `/admin/marketplace/mapping` path and absence of fixture fallback in official runtime.
- [ ] Move the approved selected component presentation into official ownership, preserving source structure and styling except authorized demo removal and provider availability controls.
- [ ] Bind JW products/categories to the existing catalog endpoint/provider; do not copy preview catalog fixtures.
- [ ] Bind stores/mappings to Task 1 APIs. Await successful saves before closing forms or presenting success; render failures through existing error/toast patterns.
- [ ] Remove Reset Demo, simulated incoming orders and fake metrics from official Marketplace surfaces.
- [ ] Stop reading old `aurora_admin_mp_*` demo storage. If removing keys, use only the exact Marketplace allowlist; do not call localStorage.clear or remove shared catalog keys.
- [ ] Keep Connect Store metadata separate from provider authorization. Disable unavailable remote operations with clear English explanation using existing control patterns.
- [ ] Include unmapped/conflict/invalid variants in Action Required without changing the approved page structure. Do not invent a new bulk-selection layout; missing flat-view bulk UI stays a documented gap unless approved source is supplied.
- [ ] Test reload, deep-link navigation, no-store empty state, API error state and save persistence.

## Task 3 — Local acceptance and Open API readiness report

**Files:** `docs/acceptance/2026-09-06-marketplace-foundation.md`; evidence under `output/playwright/marketplace-foundation/`.

- [ ] Run relevant PHP tests, Admin TypeScript, frontend regression suite and Admin build.
- [ ] Apply the migration to the canonical local MySQL runtime only after checking the existing migration status and confirming additive schema changes.
- [ ] In an authenticated localhost browser, verify all official Marketplace entries, real JW catalog values, empty external catalog, durable metadata, blocked provider operations and no false connected/synced status.
- [ ] Verify desktop and 390px mobile rendering, request failures, reload/re-login and another browser session.
- [ ] Keep seeded external variants confined to automated test databases; no production/demo listing records are inserted to make the UI look populated.
- [ ] Report readiness per capability: local configuration/mapping foundation, provider authentication, external catalog retrieval, import, publication, inventory and orders. Foundation acceptance does not imply completed platform integration.
- [ ] List remaining compound UI gaps for a single Google AI Studio follow-up, if needed. Do not claim all functionality works merely because unavailable buttons are disabled.

## Execution and release

Use inline execution in the current task as previously selected. Work is not yet implemented by this document. Review this contract before starting backend/schema work. Final GitHub/Hostinger synchronization remains separately approved.
