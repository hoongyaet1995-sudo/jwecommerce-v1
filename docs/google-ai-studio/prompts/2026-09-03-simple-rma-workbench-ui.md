# DEPRECATED — Do Not Use

This mixed Storefront/Admin prompt is retired because the two Google AI Studio projects have separate design sources. Use these independent prompts instead:

- `2026-09-03-storefront-rma-customer-experience.md`
- `2026-09-03-admin-rma-order-workflow.md`

Do not submit this mixed prompt to either Google AI Studio project.

---

# Google AI Studio Prompt — Simple Return / RMA / Replacement UI

Use the latest accepted JW Studio Ecommerce Google AI Studio project as the source. Extend the existing UI only in the two bounded regions described below.

## Non-negotiable design boundary

Preserve the current JW Studio visual system exactly: existing dark/light themes, typography, spacing scale, rounded corners, borders, shadows, table density, badges, buttons, Lucide icon style, responsive behavior, navigation, headers, footers, Order Details drawer, Purchase History cards, and all unrelated interactions.

Do not redesign or refactor existing pages. Do not create a new sidebar item, a standalone RMA dashboard, analytics, charts, SLA widgets, warehouse scanner, customer-service chat, or additional navigation. Do not replace existing components with a new component library.

This task is UI/UX source design only. Use typed view models and callback props with isolated mock fixtures for preview. Do not invent Laravel endpoints, database schemas, provider APIs, localStorage ownership, automatic refunds, courier bookings, or fake success responses.

## Product boundary

Create a minimal Return / RMA workflow for delivered order items:

- Customer outcomes: `refund` or `replacement`.
- Replacement means the exact same purchased SKU and quantity only.
- Do not support switching color, size, specification, product, or price.
- Different-variant exchanges are handled outside this UI by customer support.
- A return request never cancels, rewrites, or removes the original order.
- Refunds, replacement shipments, inventory decisions, and provider actions are represented as callback-driven states only.

Use this minimal lifecycle:

1. `requested` — waiting for staff review.
2. `awaiting_return` — approved and waiting for the customer to return the item.
3. `received` — warehouse has received the item and is deciding the resolution.
4. `resolved` — refund recorded or same-SKU replacement created.
5. `rejected` — request declined with a customer-visible reason.

`refund` and `replacement` are resolution types, not separate lifecycle trees.

## Region A — Customer Purchase History

Target the existing expanded order card in the Purchase History view. Preserve the current order card hierarchy, item rows, status badges, review actions, Buy Again controls, payment-proof panel, totals, and metadata.

### Entry action

Replace the existing disabled `Request Return / Exchange (30-Day Studio Trial)` control with a real compact action only when the view model says the order/item is eligible.

- Label: `Request Return / Replacement`
- Icon: Lucide `RotateCcw`
- Keep it in its current footer/action position.
- Ineligible or expired orders should show concise neutral explanatory copy instead of an enabled button.
- Do not expose Admin-only inventory, payment, or audit fields.

### Customer request modal

Create `src/components/returns/RequestReturnModal.tsx`.

The modal must be visually restrained and quick to complete. Use one vertical form, not a wizard.

Include only:

1. Order reference and eligibility deadline.
2. Purchased item selector showing image, name, exact SKU, purchased quantity, and unit price.
3. Quantity selector capped at the remaining eligible quantity.
4. Two compact resolution cards:
   - `Refund`
   - `Replace Same Item`
5. Reason select with:
   - Damaged on arrival
   - Defective / not working
   - Incorrect item received
   - Item not as expected
   - Other
6. Optional short details textarea.
7. Optional evidence uploader for up to 3 JPG or PNG images, maximum 5 MB each. Reuse the accepted dashed upload-zone visual language already present in the project. Show thumbnails, remove controls, and inline validation—never browser alerts.
8. Footer actions: `Cancel` and one primary `Submit Request` button.

Required states:

- default
- validation error
- selected evidence
- submitting with disabled double-submit protection
- submission failure with inline/server message
- submitted success

On mobile, use a full-width bottom sheet or edge-safe dialog consistent with existing mobile modals. Preserve keyboard navigation, focus trapping, visible labels, Escape/close behavior, and touch-friendly controls.

### Customer status panel

Create `src/components/returns/ReturnRequestPanel.tsx` and place it in the same expanded order card after submission. It should replace the request action area, not add another large section.

Show:

- compact status badge
- request number
- affected item and quantity
- requested resolution
- one short state-specific explanation
- latest timestamp

State-specific content:

- `requested`: `Return request submitted — awaiting review.`
- `awaiting_return`: show concise return instructions and tracking/reference if supplied.
- `received`: `Item received — inspection in progress.`
- `resolved/refund`: show refund amount and recorded date.
- `resolved/replacement`: show replacement shipment reference and tracking when available.
- `rejected`: show the staff-provided reason.

Do not add customer editing, negotiation, chat, cancellation, or repeated action buttons after submission.

## Region B — Admin Orders & CRM

Keep the existing Orders & CRM page, table, search, status filter, Details action, polling/reload behavior, and Order Details drawer intact.

### Minimal queue access

Add one compact `Returns (N)` quick filter using the existing chip/button pattern in the current order filter row. Do not create a new page or sidebar section.

When selected, it filters the existing order table to orders with return requests. Provide one compact sub-filter using the existing select pattern:

- Open
- Awaiting Return
- Received
- Resolved / Rejected

Rows with an active return should display one small RMA status badge without increasing row height or replacing the existing fulfillment/payment badges.

### Return Request section inside Order Details drawer

Create `src/components/orders/ReturnRequestSection.tsx` and insert it into the existing Order Details drawer near the order items and payment/shipping summary. Do not change the drawer width or its other sections.

The section header shows:

- `Return Request`
- request number
- current status badge
- submitted timestamp

The summary shows only:

- customer name
- affected item, exact SKU, and quantity
- requested outcome
- reason and optional details
- evidence thumbnails with an accessible preview action
- concise event timeline

Use one predictable primary action for the current state:

| State | Primary action | Secondary actions |
| --- | --- | --- |
| `requested` | `Review Request` | View order/evidence only |
| `awaiting_return` | `Mark as Received` | Add or copy return tracking reference |
| `received` | `Resolve Request` | View evidence/history |
| `resolved` | none | View summary only |
| `rejected` | none | View rejection reason only |

### Review interaction

`Review Request` opens a compact inline panel or nested modal with:

- `Approve` or `Reject`
- If approved: `Return Required` toggle, preserving the requested resolution
- If rejected: required customer-visible reason
- primary confirmation button and Cancel

Do not display refund amount editing or replacement SKU selection at this stage.

### Receive interaction

`Mark as Received` asks for only one inventory disposition:

- `Restock`
- `Damaged / Do Not Restock`

Include an optional internal note and one confirmation action.

### Resolve interaction

For `refund`, show the server-provided refundable amount as read-only and expose one `Confirm Refund Resolution` callback.

For `replacement`, show the exact original SKU and quantity as read-only and expose one `Create Replacement Shipment` callback.

Never imply that payment-provider refunds, inventory writes, emails, or courier bookings succeeded unless the callback returns a successful result.

Required Admin states:

- empty Returns filter
- loading
- request awaiting review
- awaiting return
- received/ready to resolve
- resolved refund
- resolved replacement
- rejected
- permission denied
- mutation failure with retry

## Typed presentation contract

Create `src/types/returnRequest.ts` with presentation-only contracts similar to:

```ts
export type ReturnRequestStatus =
  | 'requested'
  | 'awaiting_return'
  | 'received'
  | 'resolved'
  | 'rejected';

export type ReturnResolution = 'refund' | 'replacement';
export type InventoryDisposition = 'restock' | 'damaged';

export interface ReturnRequestViewModel {
  id: string;
  requestNumber: string;
  orderId: string;
  orderNumber: string;
  status: ReturnRequestStatus;
  requestedResolution: ReturnResolution;
  item: {
    orderItemId: string;
    productName: string;
    imageUrl?: string;
    sku: string;
    purchasedQuantity: number;
    requestedQuantity: number;
    unitPriceLabel: string;
  };
  reason: string;
  details?: string;
  evidence: Array<{ id: string; thumbnailUrl: string; fileName: string }>;
  submittedAt: string;
  updatedAt: string;
  eligibilityDeadline?: string;
  rejectionReason?: string;
  returnTrackingReference?: string;
  refundAmountLabel?: string;
  replacementShipmentReference?: string;
  replacementTrackingNumber?: string;
  inventoryDisposition?: InventoryDisposition;
  events: Array<{ id: string; label: string; detail?: string; occurredAt: string }>;
  permissions: {
    canRequest: boolean;
    canReview: boolean;
    canReceive: boolean;
    canResolve: boolean;
  };
}
```

Define callback props for submit, review, mark received, add return tracking, resolve refund, create replacement shipment, evidence preview, retry, and close. Components must remain controlled by these props.

## QA preview requirements

Add a clearly labelled source-only QA state switcher so every customer and Admin state can be reviewed instantly on desktop and 390 px mobile. Keep demo fixtures isolated from production-facing component files. The QA switcher must be easy to remove or disable during Laravel conversion.

Demonstrate:

- customer eligible request modal
- validation/submitting/failure
- all five customer lifecycle states
- Admin empty/loading/permission/failure states
- Admin review, receive, refund resolution, and same-SKU replacement resolution

## Expected files

- `src/types/returnRequest.ts`
- `src/components/returns/RequestReturnModal.tsx`
- `src/components/returns/ReturnRequestPanel.tsx`
- `src/components/orders/ReturnRequestSection.tsx`
- minimal targeted edits to the existing Purchase History component
- minimal targeted edits to the existing Orders & CRM component and Order Details drawer
- isolated mock/QA fixtures only where required for visual preview

## Delivery requirements

Return the complete updated Google AI Studio project as a ZIP. Also list:

1. Every created or changed file.
2. The exact component responsibility of each file.
3. Where each new region is mounted.
4. How to activate every QA state.
5. Any dependency added; avoid new dependencies unless absolutely necessary.

Do not claim backend, database, refund, inventory, email, or carrier integration. The output is an approved visual and interaction source package for later Laravel/MySQL binding.
