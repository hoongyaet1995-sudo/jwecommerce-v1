# Google AI Studio Prompt — Admin Return / RMA Order Workflow

Start from the latest accepted **JW Studio Admin Dashboard version before the mixed Storefront/Admin RMA experiment**. Design only the employee-facing Return / RMA workflow within the existing Admin Orders experience.

## Goal

Help ordinary staff find return requests, review them, record returned goods, and complete the approved resolution with minimal training and without turning the Admin into a complex RMA system.

## What staff need

- A compact way to filter the existing Orders list to orders with return requests.
- A small RMA status indicator on relevant order rows without increasing table clutter.
- A concise Return Request area inside the existing Order Details drawer showing:
  - RMA number and current status;
  - customer and affected order item;
  - exact SKU and quantity;
  - requested outcome;
  - customer reason, notes, and evidence;
  - a short audit timeline.
- One clear primary action for the current state:
  - new request: review and approve or reject;
  - awaiting return: record that the item was received;
  - received: complete the refund record or create the same-SKU replacement;
  - resolved or rejected: view-only summary.
- During receiving, staff should be able to record whether the item can be restocked or must be marked damaged, with an optional internal note.
- Rejections require a customer-visible reason.
- Loading, permission, validation, and operation-failure feedback should be clear and non-technical.

## Business boundaries

- This prompt is for the **Admin Dashboard project only**.
- Work only within the existing Orders list and Order Details drawer.
- Do not design or modify Storefront pages, customer Purchase History, customer request forms, Checkout, or customer account navigation.
- Do not create a standalone RMA dashboard, new sidebar item, analytics, SLA widgets, warehouse scanner, or support chat.
- Keep the interface progressively disclosed: do not show every possible action at once, and do not add unnecessary buttons.
- Do not modify the existing Orders reload behavior, Shipping & Logistics pages, Fulfillment & Waybill actions, AWB controls, payment-proof workflow, or unrelated Admin modules.
- Replacement means the exact original SKU and approved quantity only.
- Do not add visible developer tools, QA switchers, viewport simulators, forced-state controls, or mock-data controls to the employee interface.
- Do not fabricate RMA numbers, tracking numbers, prepaid labels, courier bookings, refunds, inventory changes, emails, or successful backend results. The design should be ready for later Laravel/MySQL callbacks and authoritative server responses.

Preserve the current Admin Dashboard design language, table density, drawer dimensions, and existing information hierarchy. You may decide the best layout and interaction details within this boundary, prioritizing simplicity for ordinary employees.

Return the complete updated Admin Dashboard project as a ZIP and list the files changed specifically for this Admin RMA workflow.
