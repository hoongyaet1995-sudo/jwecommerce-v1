# Checkout Saved Address Operational Closure

## Boundary decisions

| Boundary | Decision |
| --- | --- |
| Convergence mode | `OPERATIONAL_CLOSURE` |
| Phase/scope | P1 checkout saved delivery address selection and editable prefill only |
| Runtime truth | MySQL `customer_addresses`; order address remains a historical snapshot in `order_addresses` |
| Business owner | System-managed customer data, maintained in My Account |
| UI authority | Google AI Studio ZIP `(11)`, `CheckoutPage.tsx`, saved-address selector region; L2 approved source |
| Release surface | Official `/checkout` route |
| Explicit exclusions | No address creation/update/default changes during checkout; no mock persona autofill |

## Capability mapping

| Feature ID | Producer | Laravel/MySQL contract | Storefront consumer | Eligibility/default | Time semantics | Status | Acceptance evidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| checkout.saved-addresses.read | Customer Account address management | `customer` guard -> authenticated user's `customer_addresses` filtered to `type=shipping` | Checkout contact card selector | Signed-in customer with at least one shipping address; default first | Realtime before order placement | SYSTEM_MANAGED | Feature tests prove customer scoping; authenticated browser proof shows the real saved address |
| checkout.saved-addresses.prefill | System-managed selected address | Blade view model + Alpine local display state | Existing editable form fields | Default selected initially; manual option preserves values for editing | UI-only until submit | SYSTEM_MANAGED | Feature tests plus authenticated browser card/manual/default interaction proof |
| checkout.saved-addresses.snapshot | Checkout submission | `customer` guard owns submitted email; `CheckoutService` writes immutable `order_addresses` snapshot | Order history / fulfillment | Submitted fields always win over prefilled fields | Immutable historical snapshot | SYSTEM_MANAGED | Feature test proves authenticated email ownership and edited-field snapshot; no production order was created during browser validation |

## Non-negotiable authorization rule

The Checkout controller never loads an address by arbitrary request ID. It only exposes addresses through the authenticated customer's relationship. Checkout submits editable field values; it does not mutate saved addresses.

## Acceptance evidence

- Authenticated local browser: the real `Home` default address appeared on `/checkout`; name, email and street values visibly prefilled.
- Browser interaction: manual-entry and saved-address cards both changed selected state; reselecting `Home` restored the saved details without a page refresh.
- Responsive smoke check: the selector and manual-entry card rendered at a mobile viewport.
- No order or payment was submitted during browser validation.
- No Admin UI change is needed: My Account is the system producer already.
