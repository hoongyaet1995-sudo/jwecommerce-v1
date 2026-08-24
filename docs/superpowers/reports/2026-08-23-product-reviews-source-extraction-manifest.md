# Product review UI source extraction manifest

## Request boundary

- Storefront ZIP: `C:\Users\User\Downloads\universal-e-commerce-system-&-schema-suite (6).zip`
- Storefront ZIP SHA-256: `0395F83B6D5B1BFBE46D8D379E3E0F222EC7C1F3A660408E23816077AF226549`
- Admin ZIP: `C:\Users\User\Downloads\jw-ecommerce-dashboard (10).zip`
- Admin ZIP SHA-256: `F37BFA1490A75C89E2A02F245AF807F920D3D6B1753B1081F36F477F63907977`
- Target Laravel project: `C:\xampp\htdocs\jwstudio-ecommerce`
- Preview route: to be assigned; no official route is replaced by this manifest.
- Requested regions: customer Account purchase-history review submission, Admin Product Reviews moderation surface, and the existing Product PDP-content reviews subsection.
- Excluded regions: all other storefront and Admin screens; raw ZIP source, assets, and mock-data modules remain private/local.
- Acceptance intent: source-faithful isolated UI preview first, followed by a separate Laravel/MySQL operational-closure implementation.

## Source baseline

- Source startup command: `npm run dev -- --port <port> --host 127.0.0.1`
- Storefront source was available at local port 3101. Its unauthenticated baseline rendered; the supplied account component requires its demo-auth state to reach Purchase History.
- Admin source required the undeclared temporary dependency `react-is` because `recharts` imports it. The dependency was installed only in the temporary source directory; it is not an application change.
- Browser source baselines remain pending because the browser surface cannot reach the separately elevated temporary Admin listener. The supplied Admin screenshot is retained as visual source evidence.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/account/PurchaseHistoryTab.tsx` | `B45CA8D7739208E7D24A7E6416F3CBF8A9AB01F72D1741D0F98B26A3E74B50FE` | order-item review action, modal, validation, pending/published states | account order-history region | selected |
| `src/components/account/AccountSidebar.tsx` | source dependency | customer account tab selection | account page tab state | adapter needed |
| `src/types/ecommerce.ts` | source dependency | order-item and mock review-state shapes | Laravel review view-model | adapter needed |
| `src/components/catalog/ProductReviewsAdmin.tsx` | source dependency | moderation list, filters, detail view, moderation actions | Admin Product Reviews region | selected |
| `src/components/catalog/CatalogHub.tsx` | source dependency | Product Reviews catalog card and navigation entry | Admin Catalog Hub | selected dependency |
| `src/components/catalog/ProductExtraInfoEditor.tsx` | source dependency | read-only rating summary and moderation deep link | Admin product editor reviews subsection | selected dependency |
| `src/App.tsx` and `src/components/layout/AdminSidebar.tsx` | source dependency | Admin section registration and Catalog sub-navigation | Admin routing shell | adapter needed |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- | --- |
| delivered order and immutable order-item snapshot | review eligibility and purchased variant/product context | direct | existing-compatible, requires server-side policy |
| authenticated customer identity | review ownership and one-review-per-order-item enforcement | direct | existing-compatible, requires server-side policy |
| persisted review status and moderation actor/time | pending, published, rejected states | direct | backend contract gap |
| aggregate rating/count from published reviews only | PDP/Product Editor summary | direct | backend contract gap |
| review photos | present in the Admin ZIP mock surface | direct | excluded from P0 unless separately approved with upload/storage policy |
| bulk moderation and audit-history panels | present in the Admin ZIP mock surface | direct | excluded from P0 unless separately approved with authorization/audit policy |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| eligible review | Delivered order item action | open the existing source modal | backend contract gap |
| required-field validation | submit empty rating/title/comment | source validation messages without layout change | adapter needed |
| pending review | successful customer submission | neutral pending state in the item row | backend contract gap |
| published review | moderator publishes | compact published state; public rating may aggregate | backend contract gap |
| Admin status filters | Pending/Published/Rejected/All | source list filtering | backend contract gap |
| moderation | Publish or Reject | source moderation action and status feedback | backend contract gap |

## Approval record

- Initial business rule approved by user: only `delivered` order items may be reviewed; public `Verified Buyer` follows real order linkage and publication.
- Google AI Studio UI source supplied by user on 2026-08-23.
- Official-route cutover: not approved. An isolated Laravel preview is required before official-route replacement.
