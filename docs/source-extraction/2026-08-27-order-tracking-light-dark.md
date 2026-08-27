# Source Extraction Manifest — Order Tracking light/dark presentation

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\universal-e-commerce-system-&-schema-suite (13).zip`
- ZIP SHA-256: `64F8111FCC52413704D3BF22360B6C035A58D103D0FBDFC8393FC06BB7A42897`
- Target Laravel project and preview route: `jwstudio-ecommerce`, existing `/order-tracking` presentation surface
- Requested page/region/function: Order Tracking page visual theme adaptation only: lookup, verification, verified-result, support modal, light/dark states
- Explicitly excluded ZIP regions: catalog data, mock orders, navbar business content, checkout, account, email/verification backend logic
- Acceptance intent: real Laravel order lookup and verification must retain behavior while the supplied source determines presentation.

## Source baseline

- Source entry: `src/components/pages/OrderTrackingPage.tsx`
- Shared theme style: `src/index.css`
- Shared shell dependency: `src/components/layout/Navbar.tsx`
- Desktop viewport baseline: supplied 1204×583 light-mode capture
- Mobile viewport baseline: pending browser comparison after adapter preview
- Known source behavior retained as Laravel adapter: ZIP quick-test autofill is omitted because production lookup must not expose test data.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/pages/OrderTrackingPage.tsx` | `from ZIP manifest hash` | light/dark card, fields, verification/result/modal states | `resources/views/storefront/order-tracking.blade.php` | selected |
| `src/index.css` | `from ZIP manifest hash` | `.dark` theme selector | existing storefront theme root | existing-compatible |
| `src/components/layout/Navbar.tsx` | `from ZIP manifest hash` | shared shell theme contract | existing `<x-storefront.nav>` | existing-compatible |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- |
| Existing storefront theme root and toggle | Applies ZIP light/dark utility variants | direct | existing-compatible |
| Existing `OrderTrackingController` and routes | Real lookup, email verification, resend and result state | adapter | existing-compatible; unchanged |
| Existing nav/footer components | Source page depends on storefront shell | transitive | existing-compatible; unchanged |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- |
| Lookup | submit order number and email | source-fidelity light/dark fields and privacy note | available adapter |
| Verification | six code cells, resend | source-fidelity card and code-cell states | available adapter |
| Verified result | successful code validation | source-fidelity result presentation | available adapter |
| Theme | shared light/dark toggle | white/gray light surface; zinc dark surface | existing-compatible |

## Approval record

- Initial source boundary approved by: user, 2026-08-27
- Dependency expansion approved by: not required
- Official route cutover approved by: user requested integration into the existing Order Tracking UI; final browser acceptance remains required.
