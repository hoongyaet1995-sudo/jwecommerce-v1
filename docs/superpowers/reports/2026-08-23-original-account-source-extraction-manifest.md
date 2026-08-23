# Original Account source extraction manifest

## Scope and cutover state

- Selected private source root: `C:\tmp\jw-review-ui-source-20260823-v7`.
- Raw source remains outside this repository and is not copied here.
- Official `/account` cutover: not authorized.
- Preview route creation and UI porting are outside this contract-lock task.

## Archive provenance

The selected archive is `C:\Users\User\Downloads\universal-e-commerce-system-&-schema-suite (7).zip`, SHA-256 `0D41E70204113FB9409D86FF6A26F6F9959575C96E0F01FF7ABDDB8BD0D914D3`. Its entries contain all selected Account, direct shell, data, type, and logo files below, and the SHA-256 of every one matches the selected raw v7 tree. This archive and the per-file inventory together are the binding source contract.

## Binding source inventory

| Source file | SHA-256 | Contract role |
| --- | --- | --- |
| `src/components/pages/AccountPage.tsx` | `2D94F855C826A18E39C0E8060A62EB7FF11E6885D6B40A6B9EEF12F73588555B` | Account composition and tab state |
| `src/components/account/AccountSidebar.tsx` | `61AE7139B440BBC92BA82CDDD6460C6DE9ADF876068AA371D15EDAF97F6E6990` | Account navigation and summary card |
| `src/components/account/DashboardTab.tsx` | `29E2E334486779469B9B383DC7A31CF21D5534EB87F61DB1477B3013D7BFA4B3` | Dashboard tab |
| `src/components/account/ProfileTab.tsx` | `F81726ADD273A5ED9D2415114705A5657E055B6FDFF7964EF6223CB12FB80C03` | Profile tab |
| `src/components/account/SecurityTab.tsx` | `9BF34E24CBBD2BDDC297CA00B79A16F3FFD71682BA08226784A050E4466E30A1` | Security tab |
| `src/components/account/AddressTab.tsx` | `96DFE61B8CBD93330AB7F9EC736258F1209171496E814ACD52E3042D351FAFE3` | Address tab |
| `src/components/account/LatestPurchaseTab.tsx` | `7CE7B78A61ABFCC6419EFCE8C101D81C3378A618D4F0DF9C1A3D571C05110577` | Latest purchase tab |
| `src/components/account/PurchaseHistoryTab.tsx` | `DE046053A257FE37D782E0461AB2C694E30F9134438E9DE124B778535E98B67A` | Purchase history and review modal tab |
| `src/components/account/VouchersTab.tsx` | `0C5F123FB077966E944F9E3034FB3EB63CD1C736B65D0940D8F90DED378A8079` | Vouchers tab |
| `src/components/layout/Navbar.tsx` | `5A7D4823886A988ECDF86432C6E6F9493B16C6AE6B1957113D027C2EBB9A3D1F` | Direct announcement and navigation shell |
| `src/components/layout/Footer.tsx` | `7DDE8C695E44B8B31A43A855E5B76183025FF1A766208E10E78530AF3C065C1A` | Direct footer shell |
| `src/App.tsx` | `BF3F0FC95794E79EDE7620AA709422579D5EFD511272E9CB567303E08B61B411` | Source composition proving Navbar and Footer inclusion |
| `src/components/layout/MegaMenuDropdown.tsx` | `3025B109E53E295334C698742A01B910159FA511B32377EF7CCE2C8A828E7F86` | Direct Navbar dependency |
| `src/data/megaMenuData.ts` | `84C8031E9533CC4F5ED4AF2E0A87B2934292A3FEB58555055177B39FA7162393` | Direct Navbar menu data dependency |
| `src/data/mockData.ts` | `A5153A4661DCB6625E0F4BC5E71D6A4D4613517AC59A1E5714026907AD0FC69C` | Account default orders, addresses, vouchers, coupon rules, and products |
| `src/types/ecommerce.ts` | `20E803659EAC0C8AEE610468FA0938D632798ED5148FB3B52FED7AA219173FA8` | Source account, order, address, voucher, and profile shapes |
| `src/assets/images/jwstudio_logo_1786766390670.jpg` | `790054329B27E40821D101CF69C91ACF5EB7C8F3E992876AEB87527B289C6D84` | Direct Navbar and Footer logo dependency |

## Adapter and capability matrix

Each listed source field is either an available adapter, mock retained, or backend contract gap. An available adapter is restricted to the authenticated `customer` guard user and that customer's relations.

| Source region and fields | Classification | Current Laravel evidence and boundary |
| --- | --- | --- |
| Sidebar profile: `id`, `name`, `email`, `phone`, initials | available adapter | `User` and its `Customer` relation provide identity, email, name, and phone. `CustomerAccountController::show` already resolves the customer through `user('customer')`. |
| Sidebar profile: `avatar` display | available adapter | `customers.avatar` is available for display only; no avatar mutation is introduced. |
| Sidebar membership: `role`, tier label, `loyaltyPoints`, member-since year | mock retained | No membership or loyalty ledger is represented as operational account state. |
| Sidebar counts: order count and eligible voucher count | available adapter; mock retained | Order count can derive from the signed-in customer's orders. Eligible voucher count remains source mock data because customer voucher eligibility does not exist. |
| Dashboard identity: name and total order count | available adapter | Map only the signed-in customer and that customer's orders. |
| Dashboard active logistics: order status, carrier, tracking number, shipment label | available adapter | `orders.status`, `tracking_carrier`, `tracking_number`, `shipping_method`, and `shipping_label` can be mapped customer-scoped. |
| Dashboard logistics timeline, delivery estimate, route map, shipment events | backend contract gap | The order schema has no carrier-event or delivery-estimate contract. |
| Dashboard points, tier milestones, dollar-credit conversion, benefits, account-verified claim | mock retained | These source values have no approved loyalty, tier, entitlement, or verification service. |
| Dashboard active-voucher list and voucher expiry values | backend contract gap | Coupons exist, but no customer-issued, claimed, used, or eligible voucher record exists. |
| Dashboard recent product imagery and product selection | available adapter | Customer wishlist and order-item product/variant/image relations are available for read-only source-shaped payloads. |
| Profile: first name, last name, full name, phone | available adapter | Existing customer-guard `PATCH /account/profile` persists user name and customer phone. |
| Profile: immutable email and member identifier | available adapter | Render the customer-guard user's email and customer identifier; do not add email mutation. |
| Profile: avatar-change control | mock retained | The source control has no operational upload contract in this preview scope. |
| Profile: birthday, language, preferred currency, gender | backend contract gap | Customer storage and approved profile endpoint do not model these preferences. |
| Profile: order updates, promotions, newsletter, and SMS preferences | backend contract gap | There is no customer preference persistence contract. The source local toggle behavior may remain visual only. |
| Security: current password, new password, confirmation, password strength and success state | backend contract gap | Password change is not an approved capability for this preview. No password mutation is added. |
| Security: two-factor status, authenticator setup, recovery codes, device sessions, sign-out-other-devices | backend contract gap | No two-factor, session-device, or recovery-code backend capability is approved. |
| Address read fields: label, recipient names, phone, line 1, line 2, city, state, postal code, country, default shipping | available adapter | `customer_addresses` and the customer relation supply these fields; `is_default` maps to default shipping. |
| Address creation fields: label, recipient names, phone, line 1, line 2, city, state, postal code, country, default shipping | available adapter | Existing customer-guard `POST /account/addresses` accepts and persists the corresponding source values. |
| Address edit, delete, default billing, and address email | backend contract gap | Current customer routes contain no update/delete endpoint, only one `is_default` field, and no address email column. |
| Latest purchase: order number, date, status, item title, variant, SKU, image, unit price, quantity, line total, currency totals | available adapter | Customer-owned `orders`, `order_items`, and `order_addresses` contain the required commerce data. |
| Latest purchase: payment method, transaction identifier, gateway status, payment last-four | available adapter | Customer-owned `order_payments` can provide source-shaped read data when present. |
| Latest purchase: carrier tracking events, route milestones, estimated delivery, external tracking URL | backend contract gap | Only carrier and tracking-number fields exist; no tracking-event or delivery-estimate source exists. |
| Latest purchase reorder action | available adapter | Existing cart item route is the only permitted target; no new order mutation is introduced. |
| Purchase history: order number, date, status filters, search, items, prices, totals, currency, expand state | available adapter | Customer-owned order relations provide records; filtering, search, and expanded state are source-local interactions. |
| Purchase history review: delivered-order eligibility, rating, title, comment, approved image files, pending/published state | available adapter | Existing buyer-review domain and customer-scoped media route are the approved follow-on adapter boundary. |
| Purchase history: rejected review state restoration and source visual error/success treatment | available adapter | Existing review state is the only authority; no new review status model is introduced. |
| Vouchers: code, title, description, discount type/value, minimum spend, restriction, expiry, days remaining, eligible/used/expired, claim action | backend contract gap | Catalog coupons are not customer voucher issuance or lifecycle. Source voucher values and claim feedback remain mock-only until such a contract is separately approved. |
| Navbar announcement: free-shipping threshold, coupon code, dispatch promise | mock retained | Preserve exact source copy and visual behavior; do not represent the claim as account capability. |
| Navbar: current customer name/avatar/auth state and sign-out | available adapter | Map authenticated customer display and existing customer logout only. |
| Navbar: cart count and cart open action | available adapter | Existing cart routes are the permitted operational boundary. |
| Navbar: theme, search open state, mobile-menu state, currency selector, mega-menu hover and local navigation state | mock retained | Preserve source-local interaction only; no account-specific persistence or new navigation contract is introduced. |
| Footer: logo asset, static copy, trust statements, local navigation | mock retained | Copy, layout, and visual interactions remain source-faithful. |
| Footer newsletter email and join action | backend contract gap | Newsletter subscription must use an already-approved endpoint only after a source-faithful adapter task; no new account route or form contract is introduced here. |

## Route isolation evidence

The official route remains `GET /account`, named `customer.account`, inside the existing `auth:customer` and `customer` middleware group. Its controller action remains `CustomerAccountController::show`, rendering `storefront.account`. The dedicated test signs in through the `customer` guard, requests the named official route, expects HTTP 200, and confirms the preview marker is absent. No preview route, controller action, or view exists in this task.

## Acceptance boundary for later tasks

1. Keep the raw source private and retain all listed source DOM, classes, copy, responsive behavior, and local interactions.
2. Mount any future preview only under a separately named customer-guard route.
3. Read and mutate only customer-owned profile, addresses, cart, orders, reviews, and logout state through existing approved interfaces.
4. Keep password, vouchers, two-factor, sessions, points, tier, benefits, and unsupported address operations visibly non-operational rather than inventing backend behavior.
5. Do not alter the official `/account` route without explicit approval after preview acceptance.

## Task 3 runtime prerequisite and accepted mock ruling

The embedded Purchase History review adapter depends on the separately owned buyer-review operational-closure work that is currently present but intentionally uncommitted in the shared XAMPP worktree. Task 3 does not introduce or own that runtime. At minimum, the dependency includes the `OrderItem::reviews` and product-variant relations, the `ProductReview` domain models/table, the customer-scoped `customer.reviews.store` route, `CustomerProductReviewController`, and `ProductReviewSubmissionService`. A clean checkout containing only the Task 3 commits cannot boot the preview or its focused test until that prerequisite work is merged.

Current-runtime mitigation evidence: on 2026-08-23, the customer-guard preview rendered successfully at `http://127.0.0.1:8000/account/preview/original-account` with the prerequisite worktree present, and the existing cart drawer opened from the restored source header. This proves only the shared XAMPP runtime, not clean-checkout independence.

Acceptance item 4 above is superseded for visible source fidelity by the explicit review ruling: password, voucher, two-factor, session, points, tier, and benefits surfaces preserve the exact original source mock visual copy and local interaction. Task 3 adds no Laravel persistence for those capabilities; unsupported capability truth remains documented in this manifest and the Task 3 evidence report rather than through self-authored visible disclaimers.
