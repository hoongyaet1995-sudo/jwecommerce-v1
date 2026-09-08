# Marketplace ZIP 28 Isolated Preview Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrate the approved Marketplace UI into a localhost-only isolated preview, then report source defects and operational gaps without replacing official Admin routes.

**Architecture:** Extract selected Marketplace components and their minimum presentation dependencies. A dedicated mock provider owns preview state; existing AdminContext and Laravel business APIs remain untouched. Keep source presentation intact and label the preview as simulated outside its design canvas.

**Tech Stack:** Existing React 19, TypeScript, Vite, Tailwind, Laravel Admin host; browser acceptance on localhost.

**Spec:** User-approved scope in this task: complete local isolated UI preview, no official cutover, no real marketplace writes, no GitHub or Hostinger synchronization.

## Global Constraints

- Development root: `C:/xampp/htdocs/jwstudio-ecommerce`.
- ZIP: `C:/Users/User/Downloads/jw-ecommerce-dashboard (28).zip`.
- ZIP SHA256: `28EE2CB5CA5A8C4618F97E2501E3FA722742EBBAEAF9EC2C045086600EDD158A`.
- Raw archive/extraction remains outside repository and deployment output.
- Preserve selected source DOM, classes, English copy, responsiveness and mock interactions.
- No real product, stock, order, store connection or publication mutations.
- Do not copy the ZIP's whole AdminContext over the operational AdminContext.
- No developer/QA controls in the employee-facing design canvas. Synthetic-volume fixtures belong to test tooling only.
- Do not commit, push or deploy without synchronization approval.

## Selected source and target boundary

Selected source under `src/components/marketplace/`: `MarketplaceMappingPage.tsx`, `ProductMappingModal.tsx`, `PublishProductModal.tsx`, `ChannelMappingModal.tsx`, `MarketplaceHub.tsx`, and Marketplace sub-navigation changes in Auth/OMS/Inventory/Sync.

Targets under `resources/admin/src/previews/marketplace28/`: selected components, `PreviewProvider.tsx`, `PreviewApp.tsx`, and preview-specific fixtures/types. Shell integration is preview-only. Reuse existing styling only after comparing it to the source. Extract only required source state functions and fixture values, not unrelated settings, orders or catalog behavior.

### Task 1: Capture and verify the source baseline

**Files:** private extracted source outside repository; `docs/acceptance/2026-09-06-marketplace-28-fidelity.md`; screenshots under `output/playwright/marketplace-28/source/`.

- [ ] Hash selected files and record direct/transitive dependencies in the extraction manifest.
- [ ] Run the unmodified source on loopback with separate browser storage; capture Mapping, matching modal, publishing modal and Hub at 1440x900 and 390x844.
- [ ] Exercise source mapping, unmapping, suggestion acceptance, import and publish. Record source defects rather than silently fixing them.
- [ ] Stop if broken source prevents a truthful baseline; ask for the smallest necessary behavior correction.

### Task 2: Isolate source state and mount the preview

**Files:** `resources/admin/src/previews/marketplace28/PreviewProvider.tsx`, `PreviewApp.tsx`, selected source components and fixtures; `resources/admin/src/previews/marketplace28/preview-isolation.test.ts`.

**Interfaces:** Provider supplies the source component's `useAdmin` view model, including `products`, `categories`, `connections`, `marketplaceStoreProducts`, `marketplaceInventory`, `marketplaceOrders`, `syncLogs`, source mapping/import/publish callbacks, navigation and toasts. No callbacks delegate to operational AdminContext.

- [ ] Write isolation assertions before implementation: preview actions never invoke `fetch` or XMLHttpRequest and never write existing `aurora_admin_*` storage keys.
- [ ] Run the new test to establish the missing-preview failure.
- [ ] Extract source presentation verbatim using apply_patch; adapt import boundaries only.
- [ ] Preserve source mock actions inside memory-only preview state, including state changes needed by imported JW products and external listings.
- [ ] Mount via a separate local preview entry served by the existing frontend development environment; do not alter official Laravel routes or production build entrypoints. If a Laravel-hosted mount requires an additional host change, report the precise change before widening scope.
- [ ] Run isolation tests and TypeScript checks.

### Task 3: Browser fidelity and volume acceptance

**Files:** `resources/admin/tests/marketplace28-preview.spec.ts`; `output/playwright/marketplace-28/preview/`; acceptance report above.

- [ ] Capture the same desktop/mobile states as the source. Compare layout, copy, icons, spacing, modal scroll and overflow.
- [ ] Verify entry navigation, Connect Store wording, platform/store filters, search, grouping, flat view, pagination, mapping, unmapping, suggestions, import and publishing simulation.
- [ ] Inject a test-only catalog with at least 350 products, multiple variants and multiple stores using unique IDs. Test filtering, pagination boundaries, page changes after filtering, selection across pages and bulk action scope.
- [ ] Inspect console and network activity; confirm no operational mutation requests.
- [ ] Run `npm --prefix resources/admin run lint`, `npm --prefix resources/admin run test -- --run` and a preview build to a private output directory, leaving production artifacts unchanged.
- [ ] Report every failure as source behavior, conversion regression or backend contract gap; do not redesign source to conceal defects.

## Known operational gaps to assess, not implement

- Real store authentication and paginated catalog retrieval.
- Store-scoped stable external product/variant identity and persistent mappings.
- Import deduplication, JW draft status, category/variant normalization and independent physical stock ownership.
- Per-platform publication validation, pending/failure states and safe retries.
- Batch partial failure and idempotency.
- Source promises of instant stock sync conflict with the approved mapping-only boundary.
- Unlisted-product computation currently checks whether any variant of a product is mapped; partial listings need explicit assessment.

## Handoff

Provide preview URL, source and converted screenshots, test commands/results, mock-retained behavior, missing UI/backend capabilities and explicit `Official route replaced: no`. Cutover and release remain separate approvals.
