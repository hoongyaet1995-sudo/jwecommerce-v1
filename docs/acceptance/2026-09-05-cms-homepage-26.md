# CMS homepage selector — ZIP 26 local acceptance

## Scope and authority

- User requested the ZIP 26 Admin homepage selector and actual storefront root binding; local implementation only. No current GitHub/Hostinger release approval.
- Canonical workspace: `C:/xampp/htdocs/jwstudio-ecommerce`. Backup copies untouched.
- Selected UI: PageAdmin homepage hub, exclusive card badges/actions, Studio homepage controls, PagePreviewModal homepage controls.
- Retained the previous approved ZIP 25 fullscreen editor and existing canonical CMS persistence/public renderer.
- Excluded other ZIP modules, seeded pages/products, context/localStorage simulation and developer tools. Raw source remains private in the Windows temporary directory.
- Source fidelity governs presentation; the separately requested Laravel homepage service supplies the real adapter. Homepage selection is not written into page-document JSON.

## Source extraction manifest

Archive: `C:/Users/User/Downloads/jw-ecommerce-dashboard (26).zip`

SHA256: `BCDB1AA33227B97DD170690EDBBE049605B84C21E72B5F9B8290432F8F6D8EC1`

| Source | SHA256 | Target / role |
| --- | --- | --- |
| src/components/pages/PageAdmin.tsx | 925CAF4C772F75748E911C2BE42382A2745B88BD955D122BBA5AE42E1ADC0C3A | Same path under resources/admin; selected homepage regions only |
| src/components/pages/PagePreviewModal.tsx | BF758AE6B3C498D6C23E3C1653D8078F2EE45A2398D56A1C428C3F7ED725E728 | Homepage badge/action only; real saved-preview iframe retained |

Existing compatible dependencies: lucide-react Home/Check icons, Tailwind, AdminContext page state and toasts, CMS autosave/publish adapters. New available adapter: POST `/admin/api/pages/{page}/homepage` with CSRF, admin authorization and optimistic draft version.

Source app served with Vite on `127.0.0.1:8516`, private extracted source. Local targets: `/admin/pages`, `/`.

## Real behavior

- One locked store_settings singleton (`storefront_homepage.pageId`) owns homepage identity. List payloads derive isHomepage; duplicates are never implicitly homepage.
- Switching to a draft or archived page uses existing publication validation and publishes transactionally. Failed validation or stale version leaves the old selection unchanged.
- Switching to an already published page uses its existing published snapshot, not unpublished edits.
- Active homepage cannot be deleted, archived or unpublished; select another first. Service guards apply even when bypassing UI.
- Root `/` delegates to the official published renderer (public audience, not canary). Existing page slug remains available. Before any selection, the old storefront home remains the migration fallback.
- No schema migration, no database-wide seeding, no payment/shipping effects.
- Local selected page: **New Landing Page**, slug `new-landing-page-338`, three sections. It was already published. Existing copy was used for a switch-and-restore browser check; final selection is the non-copy page.
- `/index.php` also returned HTTP 200 with title `New Landing Page | JW Studio` in the current local server.

## Evidence matrix

All screenshot paths below are relative to the canonical workspace, private local evidence under `.playwright-cli`.

| Check | Source | Local | Result |
| --- | --- | --- | --- |
| Desktop directory 1440x900 | page-2026-09-05T05-21-38-231Z.png | page-2026-09-05T06-02-52-166Z.png | Source hub, cards, icons and classes retained; real titles/counts replace mocks |
| Mobile directory 390x844 | home26-source-mobile.png | home26-local-mobile.png | Matching stacked control hub; title/section count are actual data |
| Studio homepage badge | page-2026-09-05T06-06-27-473Z.png | page-2026-09-05T06-03-14-994Z.png | Same controls; header/sidebar hidden, exit restored directory |
| Actual storefront root | N/A, backend request | page-2026-09-05T06-02-07-690Z.png | Selected published landing page renders with existing storefront shell |
| Browser persistence | Source uses mock context | Set as Home then reload; Switch Home to copy and back then reload | Active identity and disabled delete persisted, success toast confirmed |

Known inherited source limitation: Studio toolbar at 1440px overlaps part of the homepage path badge. Both source and local screenshots reproduce it. No unsolicited styling correction applied. Source development render also logged favicon/network failures; local Admin console reported zero errors/warnings during this check.

Adaptations are value/event-only except a disabled placeholder option when no homepage is configured, preventing the browser from falsely displaying the first page as selected. Hub Preview opens real `/`; draft preview remains the existing saved-draft preview, not the live home snapshot.

## Automated verification

- HomepageSelectionTest: 2 passed / 30 assertions (exclusive selection, auto publish, rollback, stale version, guest denial, retirement guards, clone identity, draft/public isolation).
- Existing PageApi/PageDraftApi/PagePublicationApi/SharedPageRenderer/PageRuntimePayload suite: 86 passed / 806 assertions, one environment-dependent symlink test skipped.
- Final targeted PHP rerun: 6 passed / 67 assertions.
- Frontend final Vitest run: 51 files, 343 tests passed, including homepage adapter CSRF/version/error test.
- TypeScript lint and production build passed. Existing large-bundle warning remains.
- git diff --check passed.

## Release handoff

Local root cutover implemented per user request; no commit, push or Hostinger deployment performed. Prior pending branding/favicon and ZIP 25 editor work preserved. When release is approved, deploy the exact accepted commit, then select the intended Hostinger CMS page through its own Admin UI; do not copy local numeric page IDs or overwrite production settings/database wholesale.
