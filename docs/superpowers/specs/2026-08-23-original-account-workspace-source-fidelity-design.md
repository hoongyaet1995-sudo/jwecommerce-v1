# Original My Account Workspace Source-Fidelity Design

## Goal

Restore the complete Google AI Studio My Account workspace from
`universal-e-commerce-system-&-schema-suite (7).zip` as an isolated Laravel
preview. It must expose the original information architecture before the
buyer-review flow is cut over to the official customer account route.

The preview route is `/account/preview/original-account`. The official
`/account` route remains unchanged until the user accepts browser evidence and
explicitly authorizes cutover.

## Source boundary

The visual source of truth is the Account page and only its direct UI
dependencies from the supplied ZIP:

- `src/components/pages/AccountPage.tsx`
- `src/components/account/AccountSidebar.tsx`
- `DashboardTab.tsx`, `ProfileTab.tsx`, `SecurityTab.tsx`, `AddressTab.tsx`,
  `LatestPurchaseTab.tsx`, `PurchaseHistoryTab.tsx`, and `VouchersTab.tsx`
- the direct announcement, navigation, and footer dependencies required for
  the full-page source composition.

The adapter preserves the source DOM hierarchy, copy, Tailwind utilities,
responsive layout, tab behavior, modal behavior, and source interaction
states. Laravel/Alpine may supply values and submit events only. No new visible
UI is designed by this work.

## Data adapter map

| Source surface | Runtime truth | Preview behavior |
| --- | --- | --- |
| Dashboard order count and active logistics | `Customer.orders` and status records | Compute from the signed-in customer’s orders only. |
| Dashboard latest dispatch | Latest order with shipment/status history | Use real order, items, amount, carrier and tracking when present. |
| Profile Setting | `User` and `Customer` | Use existing profile values and `customer.profile.update`. |
| My Address | `Customer.addresses` | Use real addresses and existing create behavior. |
| Latest Purchase | Latest real `Order`, items, addresses and shipment fields | Use real order data; source empty state if none exists. |
| Purchase History | All real customer orders and order items | Show delivered-state eligibility from the review service; submit through `customer.reviews.store`. |
| Published/pending/rejected review state | `ProductReview` records | Published and pending prevent duplicate active submission; rejected restores the source action and retains audit history. |
| Sign out | Existing customer logout route | Invoke existing protected logout. |

## Backend contract gaps retained as source mock behavior

- Password mutation endpoint and current-password verification
- Customer-specific eligible/claimed voucher wallet and claim endpoint
- Two-factor enrollment, recovery codes, and device/session management
- Membership points, tier progress, and member benefits

Those source controls remain visually and interactively faithful in the
isolated preview but must not be represented in evidence or documentation as
operational backend capabilities. Their gaps are recorded in the extraction
manifest and closure report.

## Source review integration

`PurchaseHistoryTab` is the canonical customer review surface. Its existing
`Review this product` action/modal is connected to the real eligibility and
submission services. The source-approved optional image field accepts at most
five JPEG, PNG, or WebP images at 5 MB each. The review mutation is not
duplicated in a new customer UI.

## Safety and ownership

- Every adapter query is scoped to the signed-in customer.
- The review eligibility service remains the server-side authority for
  delivered status, ownership, and the single active pending/published review
  constraint.
- Customer review originals remain on the private review disk.
- This preview makes no official navigation or route replacement.

## Validation and acceptance

Before a cutover request:

1. Record the ZIP hash, component hashes, source baselines, and exact direct
   dependencies in the extraction manifest.
2. Capture desktop and mobile screenshots for the source and preview.
3. Browser-test tabs, real latest/purchase history, delivered review opening,
   photo selection/removal, submission, pending state, and customer isolation.
4. Run focused Laravel tests and the relevant frontend build.
5. Report every retained mock and backend contract gap.

The official `/account` route changes only after the user explicitly accepts
the isolated preview evidence.
