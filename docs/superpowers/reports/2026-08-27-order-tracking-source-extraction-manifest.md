# Order Tracking — Source Extraction Manifest

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\universal-e-commerce-system-&-schema-suite (12).zip` (private local source; not copied into the repository).
- ZIP SHA-256: `000C0B8D29156D8CBEC3780D1F8C979657B524C00848975B07F27DAC79DF2F1E`.
- Target Laravel project and preview route: `C:\Users\User\.codex\worktrees\6064\jwstudio-ecommerce`, `/order-tracking/preview`.
- Requested page/region/function: customer-facing order-number and email lookup, code verification, verified order tracking result, and source navigation/footer links to the page.
- Explicitly excluded ZIP regions: landing, PLP, PDP, cart, checkout, auth, account implementation, all schema/demo/mock-data files, and every mock business entity.
- Acceptance intent: retain selected source page structure and interactions while Laravel/MySQL owns lookup, verification, customer ownership and status data.

## Source baseline

- Source startup/build command: `npm install --no-audit --no-fund; npm run build` in private extraction `C:\Users\User\AppData\Local\Temp\jw-order-tracking-source-20260827`.
- Build result: passed on 2026-08-27; Vite `6.4.3` transformed 1,699 modules. Non-blocking warnings: Node `20.17.0` is below the React plugin preference `20.19.0`, and bundle size is above Vite's 500 kB advisory threshold.
- Desktop/mobile baseline: pending source-server browser capture.
- Known source issue: the selected component contains visible demo autofill and demo-result paths. These cannot exist in an operational public route.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/pages/OrderTrackingPage.tsx` | `E47502ED6DC2E1F7B64A29738F2C69D1AC8DC9271772B816CF46AFB6ABDF1FC4` | selected page, lookup/verify/result states | `resources/views/storefront/order-tracking.blade.php` plus narrowly scoped adapter JS | selected; business mock branches excluded |
| `src/components/layout/Navbar.tsx` | `3F90E5FF654D15A17E018ACB73434C83C61A6E03D5D8E25C9D61C63985F5E0A3` | public navigation link dependency | existing `x-storefront.nav` | existing-compatible adapter |
| `src/components/layout/Footer.tsx` | `4A0426D76C3E2F8882D33F1875D797AA1EF5771AF6C0A2289D38E38BC67A975A` | footer tracking-link dependency | existing `x-storefront.footer` | existing-compatible adapter |
| `src/App.tsx` | `F31095B4F349C3E3CC5F4DE289BC578265094AFC1F36EEB821CF0F28C3522380` | generated route switch | Laravel routes | source navigation dependency only |
| `src/data/mockData.ts` | source mock dependency | generated orders/products/presets | no production copy | excluded |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- | --- |
| Existing shared storefront nav/footer | canonical shell and page link | transitive | existing-compatible; no duplicate generated shell |
| Laravel order/status/shipment models | verified result facts | backend contract | adapter needed |
| Laravel mail transport | code delivery | backend contract | adapter needed; release blocked until configured operational mail transport |
| Session + rate limiter | scoped verification capability and enumeration prevention | backend contract | adapter needed |
| CustomerAuth + CheckoutService | email-based customer reconciliation | transitive backend contract | included as L0 behavioral work |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| lookup validation | empty/invalid inputs | exact inline source validation styling | Laravel validation adapter |
| send code | lookup submit | loading then verify screen with masked email | Laravel verification request |
| six code cells | typing, paste, arrows/backspace | source auto-advance and paste interaction | UI-only adapter |
| resend | countdown expiry | source countdown and resend acknowledgement | Laravel resend request |
| generic failure | invalid/expired code | source privacy-safe alert | Laravel generic error contract |
| verified result | successful code | source result layout populated from order/shipment snapshots | Laravel/MySQL view model |

## Approval record

- Initial source boundary approved by: user, by supplying ZIP (12) and requesting source-faithful closure.
- Dependency expansion approved by: earlier discussion approved email verification and My Account reconciliation; source-compatible shared shell is used.
- Official route cutover approved by: not yet. Preview acceptance is required first.
