# Checkout saved address source extraction manifest

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\universal-e-commerce-system-&-schema-suite (11).zip` (private local source)
- ZIP SHA-256: `B266864A97D05302FD45BDFB483E0F9ED467808D84BF160B7F74760DD4500198`
- Target Laravel project and official route: `C:\xampp\htdocs\jwstudio-ecommerce`, `/checkout`
- Requested page/region/function: **Contact & Shipping Address** saved delivery-address selector, default prefill, editable manual entry.
- Explicitly excluded ZIP regions: payment selection, order summary, account-address editing, address creation/default management.
- Acceptance intent: show only the authenticated customer's My Account shipping addresses; hide the selector for guests and customers without addresses.

## Source baseline

- Source startup/build command: private ZIP source inspected locally; no source application was started.
- Desktop viewport: checkout desktop layout; Laravel browser acceptance recorded against the official route.
- Mobile viewport: selector and manual-entry affordance remain within the source responsive one-column layout.
- Known source demo behavior excluded by approved operational scope: mock persona/address data is not carried into Laravel.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/pages/CheckoutPage.tsx` (state lines 86-111, handlers 269-288, selected DOM 598-690) | `9C07E6589C27016C22BA35100AB130A06048746373858CA23B732462B18B22AD` | Saved-address selection and local prefill interaction | `resources/views/storefront/checkout.blade.php` | selected and adapted |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- | --- |
| `customer` guard -> `Customer` -> `CustomerAddress` | Correctly scopes saved addresses to the signed-in shopper | existing-compatible | adapted in `CheckoutController` |
| `CheckoutService` -> `order_addresses` | Retains the editable checkout address as an immutable order snapshot | existing-compatible | retained |
| Alpine already loaded by storefront shell | Source-faithful client-side card selection and input updates | existing-compatible | retained |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| Saved default | Checkout render | Default address is selected and form values are prefilled | available adapter |
| Select a saved address | Address-card click | Selection styling and editable form values update immediately | available adapter |
| Manual entry | Manual-entry card click | Card becomes selected; existing fields remain editable | available adapter |
| Guest or no address | Checkout render | Selector omitted; normal editable form remains | available adapter |

## Approval record

- Initial source boundary approved by: user confirmation in this task.
- Official route cutover approved by: user confirmation in this task.
- Browser login/test authority: user supplied customer credentials and explicitly authorized local browser testing; no order or payment was submitted.
