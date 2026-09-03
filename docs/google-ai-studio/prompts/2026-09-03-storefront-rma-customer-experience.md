# Google AI Studio Prompt — Storefront Return / Replacement Experience

Use the latest accepted **JW Studio Storefront** Google AI Studio project as the visual and interaction source. Design only the customer-facing Return / Replacement experience inside the existing Purchase History area.

## Goal

Allow a customer to request a return for an eligible delivered order item during the 30-day return period, then understand the progress of that request without contacting staff.

## What the customer needs

- An obvious but compact `Return / Replace` entry action on an eligible delivered order.
- A simple request experience where the customer can:
  - select the purchased item and quantity;
  - choose `Refund` or `Replace Same Item`;
  - select a reason and optionally add details;
  - optionally upload a few photos as evidence;
  - review and submit the request.
- Clear validation, submitting, success, and failure feedback.
- After submission, a compact status area showing the RMA number, affected item, requested outcome, latest update, and what happens next.
- Customer-readable states for:
  - waiting for staff review;
  - approved and waiting for the item to be returned;
  - item received and under inspection;
  - resolved by refund;
  - resolved by same-item replacement;
  - rejected with a reason.
- A suitable responsive mobile experience consistent with the existing Storefront.

## Business boundaries

- This prompt is for the **Storefront project only**.
- Work only within the existing customer account Purchase History experience.
- Do not design or modify Admin Dashboard pages, staff controls, inventory controls, shipping management, refund administration, or Order Details drawers.
- Replacement means the exact same purchased SKU and approved quantity. Do not design variant, colour, size, product, or price exchanges.
- A return request must not cancel or rewrite the original order.
- Do not create a separate Return Portal, new account navigation, chat flow, analytics, or other unrelated features.
- Do not add visible developer tools, QA switchers, viewport simulators, forced-state controls, or mock-data controls to the customer interface.
- Do not claim that a refund, courier booking, prepaid label, replacement shipment, email, or inventory action succeeded unless later confirmed by the real backend.

Preserve the current Storefront design language and existing Purchase History hierarchy. You may decide the best layout and interaction details within this boundary.

Return the complete updated Storefront project as a ZIP and list the files changed specifically for this customer-facing RMA experience.
