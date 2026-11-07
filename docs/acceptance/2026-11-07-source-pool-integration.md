# Source pool ranges and preview — local acceptance

User approved the isolated source-pool sample and requested integration. Canonical checkout `C:\xampp\htdocs\jwstudio-ecommerce`, branch `codex/social-crm-identity`, baseline HEAD `7890952aa49a29f87d9d5cd61cf7ceaba32d1384`. Existing unrelated dirty changes retained. No commit/push/deployment. Durable issue ledger remains `2026-11-05-marketing-real-draft-minimum.md`.

## Delivered
- Per-kind None / All / Choose items for products, categories, collections, valid promotions and published CMS pages. Any combination is allowed. Global All overrides effective ranges without erasing remembered specific selections.
- New `source_modes` settings preserve legacy `source_scope`/`sources` IDs. Old configurations derive specific-item modes; unavailable selected IDs remain visible, not silently deleted. Versioned save/session restore retained. Automation list now describes ranges instead of saying0 selected for All modes.
- Actual eligible counts and bounded examples (3 products,2 other entries per group), product images/placeholders, category/static collection product counts, dynamic collection membership note, voucher benefit/dates and source-label fallback to voucher code. Other-example rotation, availability timestamp, overlap and not-actual-AI-selection explanation.
- Keyword-only picker with staged selections; cancel/Escape closes without applying. No catalog search before keyword. Preview/search failures retain settings; older responses cannot replace the newest range or keyword, including the debounce gap.
- Source service resolves each mode at generation time and revalidates the saved allowed range. Promotion-only pools never import unrelated products. All resources dynamically include new eligible entries and exclude inactive/expired/unpublished ones.
- Read-only preview POST is permission/CSRF/throttle protected and remains available when publishing execution is disabled. No execution flags enabled or credentials changed.

## Verification
- `php artisan test --compact --filter='MarketingSourcePoolTest|MarketingAutomation|MarketingCreativeIntelligenceTest|MarketingFreePost'`:69 passed402 assertions18.01s. Includes complete mocked promotion-only selection/text/image→Draft workflow, with no product broadening, and all affected existing flows.
- `node --test tests/javascript/marketing-source-pool.test.cjs tests/javascript/marketing-automation.test.cjs tests/javascript/marketing-automation-layout.test.cjs`:15 passed2354.3304ms. Covers range serialization, retained IDs, lazy picker, cancel/commit, escaped labels, stale preview/search handling and read-only retry.
- Syntax passed for6 runtime PHP files/routes and both JS files. `php artisan view:cache` passed. Scoped tracked `git diff --check` passed. Direct Blade/CSS/JS assets require no React/Vite build. Full repository suite not repeated; known unrelated prior failures retained in main ledger.
- Independent read-only review found one stale-search debounce race, reproduced RED then fixed GREEN by invalidating/aborting immediately on input. No other important findings. Also fixed nameless voucher labels/code search and preview preparation allowlist after targeted reds.
- Initial permission fixture used non-admin and expected403; auth middleware correctly returned401. Fixture changed to an authenticated admin without marketing grants; intended permission403 passed. No security behavior weakened.

## Actual browser evidence
- Chrome3 desktop1536 and mobile390×844; mobile document/client385/385 (no horizontal overflow). Console error/warn list empty.
- Actual all-products/all-promotions preview showed54 active products and1 valid voucher; catalog thumbnail pixels inspected. Mixed scope selected JTG Container Range (17 eligible products) plus all valid promotions (WELCOME10). Save/reload retained it; cancel after removing a pending item preserved the saved range. Mobile RAM keyword search worked with no results fetched before keyword.
- Dedicated QA settings only: Automation `d474ed89-0214-4a27-a752-ec255dd93d6e`, “Acceptance · Source pool ranges”. No real generation or paid provider request was made; no Post was created by this phase.
- Screenshots: `tmp/marketing-reference-expansion/source-pool-live-desktop.jpg`, `source-pool-live-mixed-desktop.jpg`, `source-pool-live-mobile.jpg`. Earlier screenshot timeout recovered on a fresh successful browser connection; these are actual captures, not DOM-only claims.

## Changed files
- `MarketingAutomationSources.php`, new `MarketingSourcePoolPreview.php`, `MarketingPostAutomations.php`, `MarketingAutomationController.php`, `MarketingExecutionAvailability.php`, `routes/marketing.php`.
- Automation editor/index/workspace and intelligence-evidence views; new source-pool, source-picker, source-range-summary partials.
- `public/marketing-studio/automation.js`, new `automation-source-pool.js` and `source-pool.css`.
- New `MarketingSourcePoolTest.php`, `marketing-source-pool.test.cjs`; plan and acceptance records. Scratch/sample assets remain separate from deployed runtime.

Remaining limits: dynamic collection matching uses the existing chunked rule engine; live generation under new ranges was mocked rather than paid-tested. No deployment or scheduling. Localhost links work on the project computer. Total elapsed/token usage unavailable.
