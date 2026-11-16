# Formal Add Page Section real previews — local acceptance

Date: 2026-11-16. Canonical dev root C:/xampp/htdocs/jwstudio-ecommerce. Branch codex/social-crm-identity, baseline HEAD ea7ce7a041bf8409cd114fe1fbf02742a94918e1. Existing unrelated dirty work preserved.

## Implemented

- Existing 173 cards, category order and matchesSectionSearch retained.
- Cards show the real SectionRenderer using createNewSection unified defaults and Neutral placeholders, with nearby-viewport mounting/unmounting.
- Every card opens a full preview before insertion. Shared families use existing VisualChoiceSelect diagrams; Desktop/Mobile previews use isolated 1280/390 viewports. Explicit confirmation inserts the currently selected shared layout through the existing factory. Ordinary cards retain existing collection/category mapping at insertion.
- Production preview HTML is a Vite multi-entry output at /build-admin/src/section-preview/index.html. It ships with normal npm run build; no sample URL dependency. Preview-only catalogue/service fixtures are isolated from canonical saved documents; existing test import re-exports this shared fixture provider.
- Existing Passport, save contract, defaults, employee documents and page renderer behavior are unchanged.

## Verification

- Intentional TDD red: old picker had no preview iframe before confirmation.
- Updated real picker test covers all173 preview -> confirm -> actual new section -> canonical import and renderer; defaults/search suite total9 tests pass (95.17s).
- Additional mounted A -> B -> A, mobile viewport and final selected-layout insertion test passes.
- PHP SectionStarterDefaultsAcceptanceTest passes, 1046 assertions (12.20s), SQLite in-memory canonical create/save/read/runtime and old-page preservation.
- npx tsc --noEmit passes. npm run build and SSR pass. Existing unrelated missing-source-font/image warnings and bundle-size warnings remain.
- Real Chrome localhost Admin: 173 visible card definitions; hero search14; shared Hero preview -> Split Hero -> Mobile -> explicit insert; ordinary Values List preview -> insert. Inspector title edited, autosave flushed by Exit, browser refreshed, draft reopened. Title, two sections, hero-split discriminator and neutral-v1 markers preserved. Actual /admin/pages/343/preview shows saved content, desktop and mobile checked.
- Production iframe audit desktop1280 173/173 and mobile390 173/173; no reported renderer errors or broken images. Evidence output/add-section-preview-integration-20261116/production-frame-audit.json.
- 145 preexisting page_documents complete-row snapshots unchanged. Only QA draft343 added; not published or Home. Evidence data-preservation.json. This draft is retained for user acceptance.

## Scope

Local only. No commit, GitHub sync, deployment, production data write or homepage/navigation change. Other unfinished work excluded. Elapsed/token totals unavailable.
