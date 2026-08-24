# Storefront canonical Account header evidence report

Date: 2026-08-24  
Runtime: `http://127.0.0.1:8500`  
Workspace: `C:\xampp\htdocs\jwstudio-ecommerce`  
Status: `DONE_WITH_CONCERNS` — the complete requested browser route matrix, including authenticated Success, is evidenced; known unrelated test/runtime concerns remain

## Outcome

The current authenticated Chrome runtime renders one canonical Account-source header across Home, Shop/PLP, Collection, PDP, Cart, Checkout, a published CMS page, and Account. The observed dark theme, selected `MYR` currency, and cart count `1` persisted across route transitions. Every exercised desktop and mobile route had zero measured horizontal overflow, the authenticated member menu used the real current customer, the desktop mega menu opened from keyboard focus and closed when focus left it, the two-level mobile menu opened correctly, and the cart drawer opened around the real current line item.

The requested storefront browser matrix is now complete. After the user explicitly supplied customer credentials and authorized login plus exactly one minimal local simulated acceptance order, the browser signed in through the normal storefront UI, added one real in-stock `Testing 001` Blue / 3cm unit, submitted checkout once through the simulated-card path, and rendered `/order/success`. The success page records order `JW-20260824020027-4419`, `RM 216.66 MYR`, and `pending payment`; it is captured at exact 1440×1000 desktop and 390×844 mobile sizes with one canonical header and zero horizontal overflow.

No production file was changed in Task 4. No browser defect requiring a controller-authorized RED/fix loop was found after reloading the previously open Account tab onto the current code. The first claimed Account tab was a stale pre-reload render and is not used as current evidence.

## Binding source and visual baseline

- Accepted source manifest: `C:\xampp\htdocs\jwstudio-ecommerce\docs\superpowers\reports\2026-08-23-original-account-source-extraction-manifest.md`
- Current manifest SHA-256: `10AA1A1E0CA4C1EA0670F7B7B5718EB975FDE1BE2A78F7ABA152AA2A868DCF22`
- Private selected archive: `C:\Users\User\Downloads\universal-e-commerce-system-&-schema-suite (7).zip`
- Archive SHA-256: `0D41E70204113FB9409D86FF6A26F6F9959575C96E0F01FF7ABDDB8BD0D914D3`
- `src/components/layout/Navbar.tsx` SHA-256: `5A7D4823886A988ECDF86432C6E6F9493B16C6AE6B1957113D027C2EBB9A3D1F`
- `src/components/layout/MegaMenuDropdown.tsx` SHA-256: `3025B109E53E295334C698742A01B910159FA511B32377EF7CCE2C8A828E7F86`

The following are user-provided pre-change visual references, not agent-captured runtime baselines:

- Authenticated Account profile dropdown: `C:\Users\User\AppData\Local\Temp\codex-clipboard-394058ae-ccaf-4a87-9743-eecd0fbd8a66.png`
- Account desktop mega-menu overlay: `C:\Users\User\AppData\Local\Temp\codex-clipboard-7b8b48fc-ed6d-48fc-828d-d6d9f995c277.png`
- Earlier legacy Account screen: `C:\Users\User\AppData\Local\Temp\codex-clipboard-f4b30294-053b-4745-b140-f759608041fa.png`

Task 4 began after the cutover was already present. It therefore did not fabricate a post-hoc runtime-before screenshot. The current screenshots below are post-change runtime evidence only.

## Browser boundary and safety

- Browsers: one connected Chrome profile with the existing authenticated local session, plus a second connected Chrome profile used as an explicitly isolated unauthenticated guest context in fix round 1.
- Credentials typed: customer credentials supplied and explicitly authorized by the user, entered once through the normal storefront login UI; values are not reproduced in this report. Admin credentials were not used.
- Cookies, storage, passwords, or session stores inspected: none.
- Authenticated persisted theme or currency changed: no.
- Isolated guest context: theme was toggled from its independent dark default to light solely for current light-mode evidence; currency remained MYR and was not changed.
- Cart quantities/items/coupons changed: exactly one `Testing 001` Blue / 3cm unit was added for the authorized acceptance order; no coupon or quantity adjustment was made.
- Checkout/order submitted: exactly one local simulated-card checkout was submitted by explicit user authorization; order `JW-20260824020027-4419` remains payment pending.
- Customer profile/address book, password, voucher, review, or upload data changed: no. Acceptance-only shipping details exist solely on the authorized order snapshot.
- Logout invoked: no.

## Route matrix — authenticated desktop

Fix round 1 used `window.innerWidth=1440`, `window.innerHeight=1000`, and an explicit `1440×1000` screenshot clip. Image metadata verifies every `exact-*` artifact below is precisely 1440×1000 pixels. The document `clientWidth` was normally 1425px because the vertical scrollbar consumed 15 CSS pixels; every measured route had `scrollWidth - clientWidth = 0`.

| Group | Requested / actual URL | Auth | Result | Screenshot |
| --- | --- | --- | --- | --- |
| Home | `http://127.0.0.1:8500/` | Existing member | One header; dark; MYR; cart 1; no overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\exact-auth-home-1440x1000.jpg` |
| Shop / PLP | `http://127.0.0.1:8500/shop` | Existing member | One header; dark; MYR; cart 1; no overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\exact-auth-shop-1440x1000.jpg` |
| Collection | `http://127.0.0.1:8500/collections/atelier-2026` | Existing member | One header; real collection title; dark; MYR; cart 1; no overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\exact-auth-collection-1440x1000.jpg` |
| PDP | `http://127.0.0.1:8500/products/aether-studio-wireless-headphones` → `?variant=76` | Existing member | One header; real PDP; dark; MYR; cart 1; no overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\exact-auth-pdp-1440x1000.jpg` |
| Cart | `http://127.0.0.1:8500/cart` | Existing member | One header; Studio Cart (1); dark; MYR; no overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\exact-auth-cart-1440x1000.jpg` |
| Checkout | `http://127.0.0.1:8500/checkout` | Existing member | One header; checkout rendered; no submit; dark; MYR; cart 1; no overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\exact-auth-checkout-1440x1000.jpg` |
| CMS/public | `http://127.0.0.1:8500/pages/new-landing-page-338` | Existing member | One header; published CMS page rendered; dark; MYR; cart 1; no overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\exact-auth-cms-public-1440x1000.jpg` |
| Account | `http://127.0.0.1:8500/account` | Existing member | One header and one announcement; Account workspace rendered; no overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\exact-auth-account-1440x1000.jpg` |
| Auth | `http://127.0.0.1:8500/account/login` | Isolated guest/light | Current guest login page and canonical header rendered | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\exact-guest-auth-login-light-1440x1000.jpg` |
| Order success | `http://127.0.0.1:8500/order/success` | Newly authorized customer login | One canonical header; light state left unchanged; MYR; cart 0; order `JW-20260824020027-4419`; no overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-3\order-success-auth-desktop-1440x1000.jpg` |

## Authenticated interaction evidence

| Interaction | Result | Screenshot / evidence |
| --- | --- | --- |
| Member dropdown | Real current name/email/initial displayed; `My Orders & Dispatch`, `Account & Addresses`, and logout visible; no fake guest/member substitution | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\account-member-dropdown-post-desktop-1440.png` |
| Member destinations | Orders navigated to `/account#purchase_history`; Account & Addresses navigated to `/account#profile`; logout remained visible and untouched | Browser URL checks |
| Desktop mega by keyboard focus | Focusing `Electronics & Audio` with the keyboard opened `mega-menu-menu-174` with configured Premium Audio, Wearables & Smart, and Desk & Workspace content | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\account-mega-menu-dark-post-desktop-1440.png` |
| Focus leaves mega | `Shift+Tab` moved focus to `Contact Us` and the visible mega-menu list became empty; focus was not trapped | Browser focus/visibility check |
| Cart | Header cart showed `1`; opening the drawer showed `Studio Cart (1)` and the existing `Testing 001` line; drawer was closed without quantity/item/coupon mutation | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\account-cart-drawer-post-desktop-1440.png` |
| Theme/currency wiring | Source controls and runtime data hooks were present. Current dark state, `MYR`, and seven enabled runtime options (`MYR`, `SGD`, `USD`, `EUR`, `IDR`, `THB`, `CNY`) persisted across navigation. Neither control was changed. | Route matrix and DOM evidence |
| Console | Zero console errors in both the Account and route-matrix tabs | Chrome console log check |

## Fix round 1 — isolated guest and light evidence

The isolated context was the second connected Chrome extension instance (`extensionInstanceId 9805b289-1c0a-4e1a-8702-433cf847ae1a`). It was verified from visible DOM as guest with cart `0`, no member identity, and the real Sign In/Register/Forgot Password targets. No credentials, cookies, storage, or customer data were inspected. Its theme was independently toggled to light; the authenticated profile was never changed.

| Requirement | Current evidence | Result |
| --- | --- | --- |
| Light source header | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\guest-light-header-desktop-1440x1000.jpg` | Exact 1440×1000 image; light root; one canonical header; guest/cart 0/MYR. |
| Desktop real hover mega | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\guest-light-mega-real-hover-desktop-1440x1000.jpg` | Real pointer moved from outside the nav onto Electronics & Audio; `mega-menu-menu-174` became visible with configured Premium Audio, Wearables & Smart, and Desk & Workspace content. |
| Guest dropdown | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\guest-light-account-dropdown-desktop-1440x1000.jpg` | Sign In, Register, and Forgot Password visible; no `1250 PTS` or authenticated customer identity. |
| Enabled currency selector opened | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-1\guest-light-currency-selector-keyboard-open-desktop-1440x1000.jpg` | Selector was focused and opened with `Alt+ArrowDown`; browser DOM reported MYR selected and seven enabled options: MYR, SGD, USD, EUR, IDR, THB, CNY. Chrome page screenshots do not composite the native dropdown popup, so the artifact shows the focused/open control and the enabled-option list is recorded from the same browser state. No option was selected or changed. |

Every exact fix-round image was independently read with `System.Drawing.Image`; width is 1440 and height is 1000.

## Fix round 2 — authorized success attempt blocked by unavailable authenticated session

The user explicitly authorized creation of exactly one minimal local acceptance order through the existing authenticated browser session. Before any checkout submission, the original Account tab visibly retained the previously rendered member UI and `Cart (1)`. Its first navigation to `/checkout` returned `/shop`, `Cart (0)`, and guest controls. A second tab resolved `/account` to `/account/login`; the Chrome tab list then contained only those guest pages. No credentials were typed, no cookies/storage/session data were inspected or reconstructed, and no add-to-cart or checkout submit was attempted after the session loss. Therefore **zero acceptance orders were created** in this round.

| Evidence | Result | Screenshot |
| --- | --- | --- |
| Former authenticated tab after `/checkout` navigation | Actual URL `/shop`; guest header; `Cart (0)`; exact 1440×1000 | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-2\auth-session-expired-shop-cart-zero-1440x1000.jpg` |
| Account route without surviving authenticated session | Actual URL `/account/login`; exact 1440×1000 | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-2\auth-session-expired-login-1440x1000.jpg` |

Image metadata was verified with `System.Drawing.Image`: both artifacts are exactly 1440×1000 pixels. No success desktop/mobile artifacts, order number, product, or transaction currency can be truthfully reported because the authorized order was never created.

## Fix round 3 — authorized authenticated Success evidence

The user explicitly authorized use of the supplied customer credentials and creation of exactly one minimal local simulated order. The browser logged in through `/account/login`, verified the real customer identity, and found Cart (0). It selected the existing `Testing 001` product at `/products/t?variant=17`; the page reported Blue / 3cm, SKU `TES-BLU-3CM`, `RM 199.00`, and `In Stock (40 units)`. One unit was added. Checkout used acceptance-only shipping details, standard delivery, and the existing `simulated_card` method. No save-address control was present, no profile/address was edited, and theme/currency controls were untouched.

The submit button was clicked exactly once. The resulting `/order/success` body reported:

- Order number: `JW-20260824020027-4419`
- Product: `Testing 001`, Blue / 3cm, SKU `TES-BLU-3CM`, quantity 1
- Currency: `MYR`
- Subtotal: `RM 199.00`
- Tax: `RM 17.66`
- Shipping: `RM 0.00`
- Total: `RM 216.66 MYR`
- Payment method/status: `SIMULATED CARD` / `pending payment`

| Viewport | Browser result | Screenshot |
| --- | --- | --- |
| Desktop 1440×1000 | `innerWidth=1440`, `innerHeight=1000`, one `header[data-mega-nav]`, `scrollWidth=clientWidth=1425`, no overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-3\order-success-auth-desktop-1440x1000.jpg` |
| Mobile 390×844 | `innerWidth=390`, `innerHeight=844`, one `header[data-mega-nav]`, `scrollWidth=clientWidth=390`, no overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\fix-round-3\order-success-auth-mobile-390x844.jpg` |

`System.Drawing.Image` independently verified the saved pixel dimensions as 1440×1000 and 390×844. Visual inspection confirms the canonical header, order number, simulated-card status, total, and Success body are legible at both sizes. The authenticated session was already in light mode after login and was left unchanged.

## Route matrix — authenticated mobile

Chrome viewport override was `390×844`; effective `clientWidth` was 375px on standard pages and 380px on the CMS page because of scrollbar allocation. Every route measured `scrollWidth === clientWidth`.

| Group | Actual URL | Result | Screenshot |
| --- | --- | --- | --- |
| Home | `/` | One mobile header; dark; MYR; cart 1; zero overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\home-auth-mobile-390.png` |
| Shop / PLP | `/shop` | One mobile header; zero overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\shop-auth-mobile-390.png` |
| Collection | `/collections/atelier-2026` | One mobile header; zero overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\collection-auth-mobile-390.png` |
| PDP | `/products/aether-studio-wireless-headphones?variant=76` | One mobile header; zero overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\pdp-auth-mobile-390.png` |
| Cart | `/cart` | One mobile header; zero overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\cart-auth-mobile-390.png` |
| Checkout | `/checkout` | One mobile header; no submit; zero overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\checkout-auth-mobile-390.png` |
| CMS/public | `/pages/new-landing-page-338` | One mobile header; zero overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\cms-public-auth-mobile-390.png` |
| Account | `/account` | One mobile header; zero overflow | `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\account-auth-mobile-390.png` |

Mobile nested-menu evidence:

- Root mobile drawer and `Electronics & Audio` first layer: `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\home-mobile-menu-nested-dark-390.png`
- `Premium Audio` second layer showing `Noise Cancelling Headphones` and `Hi-Fi Audio Systems`: `C:\xampp\htdocs\jwstudio-ecommerce\output\storefront-canonical-header-task4\home-mobile-menu-second-layer-dark-390.png`
- Both open states measured zero horizontal overflow.

## Automated verification

### Binding final scoped suite

Command:

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCanonicalHeaderTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php tests/Feature/ProductReviewOperationalClosureTest.php tests/Feature/Admin/ProductReviewAuthorizationTest.php
```

Initial result: exit 0; **30 tests passed, 311 assertions** in 3.45s.

Final rerun after the successful authorized order: exit 0; **30 tests passed, 311 assertions** in 2.47s.

### Currency and CMS compatibility suite

Command:

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/CurrencySettingsClosureTest.php tests/Feature/Cms/SharedPageRendererTest.php
```

Result: exit 1; **16 passed, 1 failed, 1 skipped, 130 assertions**.

- Known stale failure: `CurrencySettingsClosureTest::storefront currency control uses only enabled persisted currencies and the default selection`, line 50.
- The test requires the exact literal `class="bg-white text-zinc-900"`; the accepted source-faithful output orders equivalent classes as `class="bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100"`.
- Operational assertions for selected MYR, enabled USD, and disabled GBP exclusion pass. Source class order was not changed to satisfy the stale assertion.
- `SharedPageRendererTest` passed 15 tests; its Windows symlink test was skipped by its own platform guard.

### Full Laravel suite — fix round 1

Commands:

```powershell
C:\xampp\php\php.exe artisan test
C:\xampp\php\php.exe artisan test --log-junit output\storefront-canonical-header-task4\fix-round-1\full-suite-junit.xml
```

Both runs completed with the same result: exit 1; **248 passed, 5 failed, 1 skipped, 2,074 assertions**. The JUnit run completed in 19.50s and records these exact failures:

1. `Tests\Feature\CurrencySettingsClosureTest::test_storefront_currency_control_uses_only_enabled_persisted_currencies_and_the_default_selection` — stale literal class-order assertion at line 50 expects `class="bg-white text-zinc-900"`.
2. `Tests\Feature\StorefrontMockClosureTest::test_storefront_hides_unverifiable_marketing_and_rating_claims` — line 67 expects the response not to contain the now-required source announcement `COMPLIMENTARY SHIPPING`.
3. `Tests\Feature\StorefrontPdpRichContentTest::test_pdp_uses_persisted_rich_content_and_explicit_related_products` — line 65 finds the unrelated PDP text `28 Verified Studio Reviews`.
4. `Tests\Feature\StorefrontVariantVisualTest::test_storefront_navigation_uses_the_persisted_shop_name` — line 27 expects legacy `JW Studio Commerce` and rejects the canonical source `ATELIER` brand.
5. `Tests\Feature\StorefrontVariantVisualTest::test_pdp_exposes_persisted_option_visuals_for_the_source_faithful_selector` — line 79 expects the former `top-[96px]` header offset.

No production or test change was authorized for these full-suite failures in Task 4. The binding 30-test header/Account/review suite remains green.

### Production build

Command:

```powershell
npm run build
```

Result: exit 0; Vite 7.3.6 transformed 1,832 modules and emitted:

- `public/build/assets/app-5pObT-Cv.css` — 280.56 kB (34.06 kB gzip)
- `public/build/assets/app-_4WDVqqc.js` — 96.74 kB (32.01 kB gzip)
- `public/build/manifest.json`

Separate warning: installed Node.js is 20.17.0; Vite declares 20.19+ or 22.12+. The build still completed successfully in 7.13s.

## Scoped implementation files represented by this evidence

- `resources/views/components/storefront/nav.blade.php` — Task 2 canonical shared header.
- `tests/Feature/StorefrontCanonicalHeaderTest.php` — Tasks 1/2 public-route and auth contracts.
- `resources/views/storefront/account-original-preview.blade.php` — Task 3 shared-header consumption.
- `tests/Feature/OriginalAccountWorkspacePreviewTest.php` — Tasks 1/3 single-header and Account regression coverage.

`resources/js/app.js` required no Task 2 change. Task 4 changed only this acceptance report, its task report, and screenshot artifacts. No files were staged or committed because the shared `main` checkout is heavily dirty and the scoped files overlap existing uncommitted work.

## Mock-retained and unsupported behavior

This header convergence did not claim operational capability for source loyalty points/tier/benefits, voucher lifecycle, unsupported Account security/session controls, announcement shipping/coupon copy, or other gaps listed in the accepted source manifest. The header uses real SiteMenu, customer guard, cart session, currency context, theme behavior, and approved customer routes. Account body source mocks remain governed by the accepted Account manifest and were not changed by Task 4.

## Remaining concerns

1. **Authorized local data:** one acceptance order, `JW-20260824020027-4419`, now persists with simulated-card status `pending payment`; the Success body says inventory is reserved.
2. **Full-suite status:** five exact failures remain recorded above; four are stale or unrelated header expectations and one is unrelated PDP rich-content text. No Task 4 production/test correction was authorized.
3. **Currency native popup capture:** Chrome's page screenshot omits the native select popup. Focus/open interaction and all enabled options are browser-verified without selection change.
4. **Node version:** build succeeds, but Node 20.17.0 is below Vite's declared minimum.
