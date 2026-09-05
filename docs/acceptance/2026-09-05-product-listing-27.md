# Product Listing ZIP 27 — local acceptance

## Approved boundary

User approved source-faithful Product Listing carousel, Add Section entry and Inspector, real Category/Collection catalog mapping, and reuse of the existing PLP Quick Add popup. Canonical local workspace only; no production homepage mutation or release approved. Mode: OPERATIONAL_CLOSURE, locally verified for this bounded section (not a whole-CMS closure).

ZIP: `C:/Users/User/Downloads/jw-ecommerce-dashboard (27).zip`

SHA256: `0F52ADB693BC017D88543FB319DBA673B6F97E6D9E3F82E1663765F562D661CC`

Private source: Windows temporary folder `jw-cms-source27-ee7e3cd5a2474378830f8822b405b727`, served with Vite on 8517. Raw ZIP/context/mock catalog remain excluded from the repository.

## Extraction and adapters

| Selected source | Target / dependency |
| --- | --- |
| SectionRenderer.tsx: product_listing, carousel state/effects | Existing CMS React renderer; exact DOM/classes with real-value/event adapters |
| SectionInspector.tsx: product_listing controls | Existing shared searchable source picker, real Admin catalog |
| AddSectionModal.tsx: Product Listing Carousel | Existing section catalog, canonical draft creation |
| types/index.ts: listing fields | Strict canonical CMS document contract and runtime validator |

All other ZIP modules excluded. Existing approved Quick Add Blade, storefront app.js, cart endpoints and currency formatting remain owners of commerce behavior. No redesign of PLP/PDP/cart.

## Mapping / verified status

| Capability | Producer → persisted owner → consumer | Status / acceptance needed |
| --- | --- | --- |
| Listing settings | Inspector → page draft/published documents → CMS renderer | VERIFIED_LOCAL: strict schema, round-trip and publication tests; browser reload retains source, limit 12 and 3s |
| Category/Collection products | Real Admin catalog → products/variants/relations → published section facts | VERIFIED_LOCAL: active sources/products; empty, inactive, hidden and limit tests; no all-products fallback; picker counts exclude drafts |
| Carousel | Persisted settings → source React interaction | VERIFIED_LOCAL: mobile browser auto/next navigation; unit tests for actual item-count wrap and hover pause; preview desktop/tablet/mobile responsive owner |
| Quick Add | Source shopping bag → existing PLP popup → cart service/session → header/drawer | VERIFIED_LOCAL: existing modal delegated trigger, exact SKU and quantity persisted; preview cannot buy; duplicate product renders one modal |

Draft may retain incomplete source selection; publishing requires a valid active source. Empty valid sources show no fabricated products. Prices, discount, brand, SKU and options must be real; source demonstration fallbacks are explicitly excluded by approved mapping. Sections use published configuration while catalog pricing/eligibility stays realtime. No external integrations.

## Source fidelity evidence

Source component SHA256:

- SectionRenderer.tsx: `9BBC3CDF7C4DB04AC988F5A90649617EDD5A1D76092AE6DB249E0D95AC434E84`
- SectionInspector.tsx: `3EA2395C390775A3F4C3D02BFB7E1DC1C83747C02CFB4A95D28BBE2AA5CEA223`
- AddSectionModal.tsx: `2B542B8226B19CDFEA785C4F7B2F7729FED5C867C69082C78981FC46E9238D31`

All 36 literal className attributes in the extracted listing renderer match the source. The source card, wrappers, typography, image surface, arrows and dots remain intact. Source and local Studio mobile screenshots were visually inspected. Screenshot content differs intentionally because the application uses its database, including the current catalog image assigned to Aether; no imagery was silently replaced. Public desktop 1440 and mobile 390 screenshots were also inspected. This is source-region verification, not a claim of identical screenshot pixels with different catalog data.

Behavior-only deviations: valid compare-at discount calculation, no fabricated fallback brand/SKU/options or all-products fallback, actual product-count carousel bounds, Quick Add disabled in editor/preview, existing public Quick Add handlers, and store-owned currency formatting. Fixed a browser-reproduced `MYR (RM)` formatting crash and added its regression test.

## Local browser evidence / cleanup

Artifacts in `output/playwright/product-listing-27/` (private local acceptance evidence):

- `listing27-source-desktop.png`, `listing27-source-mobile.png`
- `listing27-local-editor-mobile.png`
- `listing27-public-desktop.png`, `listing27-public-mobile.png`
- `listing27-selected-variant.png`, `listing27-variant-cart.png`, `listing27-cart-page.png`
- `page-2026-09-05T07-42-56-455Z.png`: existing Collection PLP Quick Add regression

Local QA page ID 24, title **Product Listing QA 27**, slug `new-landing-page-686`. Temporarily published only this isolated local page to test actual commerce; returned it to **draft** afterwards. Authenticated preview: `/admin/pages/24/preview`. Existing homepage (page 21) and its content were not changed. No order, payment or shipping booking was made. Isolated guest cart was emptied after proving `AET-LUN-MAT` × 2 at RM 778.00 in both drawer and cart page, with Header total quantity 3 when the earlier one-unit test line was present.

An existing `/admin/api/shipping/webhook-receipts` 500 was observed on an earlier Admin bootstrap before listing interaction. It is outside this section's acceptance; no shipping implementation was changed.

## Verification commands

- `npm test -- --run` in `resources/admin`: **53 files / 346 tests passed**.
- `npm run build` in `resources/admin`: passed; existing large-chunk warning remains.
- `npm run build` at repository root: passed; current Node 20.17 emits Vite's upgrade warning (20.19+ / 22.12+ recommended).
- `C:/xampp/php/php.exe artisan test --filter='ProductListingTest|PageRuntimePayloadTest|CmsPageDocumentSchemaTest|SharedPageRendererTest|QuickAdd'`: **43 passed, 404 assertions, 1 existing Windows symlink test skipped**.
- `git diff --check`: clean.

No commit, GitHub push or Hostinger deployment performed in this phase. Future approved release must include rebuilding the root storefront assets for the delegated Quick Add handler as well as the Admin build. Do not include private source ZIPs, source node_modules, or unrelated runtime evidence in release staging.

## Approved Nordic Clean correction (2026-09-05)

User explicitly authorized PLP light colors for Nordic Clean cards, while retaining the source carousel geometry and other palettes. Nordic persists `backgroundColor=bg-white`; listing cards on that canvas now use PLP `bg-white`, `border-zinc-200`, `shadow-sm`, hover border/shadow, zinc text, emerald SKU, amber variant and shopping-bag colors. Dark source classes remain unchanged. This approved color exception supersedes literal-class parity for the light branch only; DOM tags/order remain equal in the regression test.

Browser computed-style comparison against the real PLP matched exactly for card background (`rgb(255, 255, 255)`), border (`oklch(0.92 0.004 286.32)`), box shadow and title (`oklch(0.21 0.006 285.885)`). Desktop and 390px screenshots: `nordic-light-desktop.png`, `nordic-light-mobile.png` in the same evidence folder.

Also identified and repaired a genuine shared-modal CSS cascade conflict: the later CMS stylesheet generated base `w-full` but lacked the Blade modal's `md:w-5/12`. At a 896px desktop modal width, the image column incorrectly occupied 894px. Admin/CMS Tailwind now scans the existing Quick Add Blade as an explicit CSS source, producing its full responsive utility set without changing the modal markup or business logic. Browser verification after rebuild confirms the image column is approximately 5/12 of the modal, with the original two-column presentation. See `nordic-light-quick-add.png` and `nordic-quick-add-mobile.png`. Real Lunar Silver selection and quantity 2 yield the existing `Add 2 Items • RM 778.00` action. No cart submission was made during this correction.

Verification: 53 frontend test files / **347 tests passed**, Admin build passed (existing bundle-size warning), `git diff --check` clean. QA page 24 returned to draft after isolated public acceptance. No homepage configuration changes, commits, pushes or deployment.

## Approved PLP image geometry correction (2026-09-05)

User requested removal of the Product Listing image spacing to match the current PLP. Replaced the padded, fixed-height/4:3 `object-contain` image region with an unpadded `aspect-square` region and `object-cover`, across listing palettes. This is an explicit source-fidelity exception for image geometry only; carousel, card information and shared Quick Add remain unchanged. Non-square images crop centrally; whitespace embedded in an image file is not removed.

Regression verified RED before the change, then all 3 Product Listing tests passed. Admin build passed with the existing bundle-size warning. Authenticated localhost preview of draft QA page 24 verified desktop images and containers both 390x390, padding 0px and object-fit cover; 390px mobile screenshot also inspected. Evidence: `edge-to-edge-desktop.png` and `edge-to-edge-mobile.png` in `output/playwright/product-listing-27/`. No page data or publication status changed, and no GitHub/Hostinger synchronization performed.

## PLP interaction parity and release scope (2026-09-05)

User approved completing the remaining PLP card interactions and synchronizing the complete Product Listing phase. Scope is existing PLP business interaction binding, not replacement of the approved carousel with PLP filters/grid/pagination.

- Public image and title link to the real product handle; editor/preview links remain inert.
- Real ordered variants replace noninteractive option-label placeholders. Four visible chips select actual variants; overflow opens the shared Quick Add. Selection updates SKU, price, compare-at, discount, optional image and Quick Add variant ID. Disabled variants cannot be selected.
- One-variant items submit the existing CSRF-protected cart endpoint directly and disable the action for unavailable inventory. Multiple variants keep the existing shared Quick Add modal, including its stock/quantity constraints and error handling.
- Prices remain formatted by StorefrontCurrencyContext; no invented prices, discounts or catalog fallback. Backend cart validation remains authoritative. Source carousel, CMS controls and approved light/image corrections retained.

RED tests demonstrated missing links, selectable variants and variant runtime facts, then passed after binding. Frontend full suite: 348 tests passed; focused final rerun: 20 passed. Backend ProductListingTest, PageRuntimePayloadTest and StorefrontQuickAddTest: 26 passed / 281 assertions. Both builds succeeded with existing Node-version/bundle-size warnings.

Local real-browser evidence: product image navigated to `/products/aether-studio-wireless-headphones`; Lunar Silver selection opened existing Quick Add at AET-LUN-MAT, RM389, stock40. Add-to-cart produced the same SKU and quantity1 in the drawer with Header count1, without checkout/payment. Test line then removed. Screenshot evidence is private/local under `output/playwright/product-listing-27/` (`plp-parity-quick-add.png`, `plp-parity-mobile.png`). No homepage content, publication configuration or inventory changed.

Release includes application changes, tracked Admin build and separately copied local root Vite build (Hostinger has no npm). Raw source exports, test-session files and unrelated acceptance output are excluded. Production commit parity and HTTPS smoke results are reported after deployment, not inferred from this pre-release record.
