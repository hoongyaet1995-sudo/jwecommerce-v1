# Google AI Studio Prompt — JW Ecommerce Tax Lifecycle UI

Copy everything below into the existing JW Ecommerce Admin Google AI Studio project that produced `jw-ecommerce-dashboard (13).zip`.

---

You are updating the existing JW Studio Ecommerce Admin Dashboard. Do not redesign the application. Preserve the current `TaxZonesSettingAdmin.tsx`, `OrderAdmin.tsx`, Admin shell, DOM hierarchy, dark/light theme, spacing, typography, colors, Tailwind utility language, icons, tables, responsive behavior, modal proportions, button styles, and interaction patterns.

This task is UI/UX source generation only. Use React + TypeScript and the existing project conventions. Keep mock state and callbacks functional so every control can be demonstrated locally. Do not add a backend, API client, database code, Laravel code, third-party UI kit, or new visual system.

## 1. Tax Engine Configuration lifecycle controls

Extend the existing `Tax Engine Configuration` tab inside `src/components/settings/TaxZonesSettingAdmin.tsx` using the same cards, borders, labels, switches, selects, badges, explanatory copy, and responsive stacking already present.

Add these merchant controls:

1. `Tax Collection Status`
   - Master Enabled / Disabled switch.
   - Published / Draft switch or explicit Publish / Unpublish action.
   - Show a compact status badge: Draft, Published & Active, or Published but Disabled.
   - Warning copy must explain that disabling stops new quotes but does not alter historical orders.

2. `Calculate Tax by Address`
   - Preserve the current options: Delivery / Shipping Destination, Customer Billing Address, Store Origin Warehouse.
   - When Billing Address is selected, display concise helper text that Checkout must collect or reuse a billing address.
   - When Store Origin is selected, show a Store Origin selector/state sourced from mock data.

3. `Display Prices with Tax Included`
   - Preserve the existing checkbox/switch.
   - Add a compact explanation of gross catalog price versus itemized tax extraction at checkout.
   - Add a warning/confirmation interaction before switching an already-published shop between tax-exclusive and tax-inclusive price display.

4. `Shipping Tax Class`
   - Select one Tax Class for shipping or `Shipping is not taxable`.
   - Explain that a Tax Rate Rule must also have Applies to Shipping enabled.

5. `Cart and Receipt Tax Display`
   - Select `Single tax line` or `Itemized by tax rule`.

6. `Merchant Tax Metadata` as optional fields
   - Legal tax display name.
   - Registration number.
   - Invoice footer note.
   - Explicitly label these fields optional; missing values must not block publication.

Provide Save, Publish, Unpublish, success, validation-error, dirty-state, and destructive-confirmation interactions using existing JW Studio patterns.

## 2. Tax Rate Rule modal lifecycle fields

Extend the existing Add/Edit Tax Rate Rule modal without replacing its current DOM/class system. Retain Description, Tax Class, Geo Tax Zone, Rate Percentage, and Priority.

Add:

- Enabled switch.
- Applies to Shipping switch.
- Compound Tax switch, with helper copy: when enabled, this rule is calculated after lower-priority matched tax and includes that earlier tax in its base.
- Optional Effective From date/time.
- Optional Effective Until date/time.
- Inline validation state when Effective Until is earlier than Effective From.
- Inline conflict error region for: `An enabled rule with this class, zone, and priority already overlaps the selected effective period.`

Update the Tax Rates table with compact lifecycle visibility while keeping the table style:

- Status: Active, Disabled, Scheduled, or Expired.
- Effective period summary.
- Shipping badge when applicable.
- Compound badge when applicable.
- Edit and Delete actions remain. If a rule has historical order references, Delete should be disabled and the UI should direct the operator to Disable instead.

## 3. Tax Class lifecycle

Extend the existing Add/Edit Tax Class interaction with:

- Active / Inactive status.
- Warning that making a class inactive stops it from producing new tax quotes but does not remove it from historical orders.
- Existing Assigned Products link/count remains unchanged.
- A class referenced by products or rules cannot be deleted; show the existing delete-confirmation pattern with the blocking reason.

## 4. Live Tax Rate Inspector becomes an authoritative preview UI

Preserve the current inspector’s outer DOM/classes and visual position. Expand its mock interaction contract so it can display a server-style preview result rather than only multiplying one percentage locally.

Inputs:

- Item base price.
- Tax Class.
- Country.
- State/Region.
- Postal Code.
- Shipping amount.
- Effective date/time preview value.

Output states:

- Chargeable with total tax and final amount.
- Matched zero rate.
- No matching rule.
- Tax configuration draft/disabled.
- Itemized matched rule lines showing label, rate, taxable base, and tax amount.

Keep the output compact and responsive; do not turn it into a separate page.

## 5. Order tax evidence and refund UI

Extend `src/components/orders/OrderAdmin.tsx` using its existing order-detail panels, modal language, tables, and typography.

Add a read-only `Tax Evidence` section containing:

- Total order tax.
- One row per immutable tax line: merchandise/shipping, tax label, class, jurisdiction zone, rate, taxable amount, tax amount, and quoted timestamp.
- A clear `Historical snapshot` badge explaining that later Tax Settings changes do not rewrite this order.

Upgrade the existing refund interaction:

- Allow selecting order items and quantities, shipping adjustment, or a custom goodwill amount.
- Show calculated merchandise refund, shipping refund, tax refund, and total refund separately.
- For custom goodwill refunds, default Tax Refund to zero and make that explicit.
- Require a reason.
- Show remaining refundable merchandise, shipping, and tax balances.
- Confirmation screen must state that the refund creates immutable refund and tax-adjustment records.
- Support partial and full refund states using the existing modal and status badge patterns.

## 6. Required TypeScript contracts

Update `src/types/oscommerceSettings.ts` and existing order types so the UI is fully typed. Include:

- Tax configuration enabled/published status.
- Shipping tax class.
- Receipt display mode.
- Optional merchant metadata.
- Rule `appliesToShipping`, `compound`, `isEnabled`, `effectiveFrom`, `effectiveUntil`, and historical-reference/delete eligibility.
- Tax preview request/result with status, reason, total tax, and itemized lines.
- Immutable order tax evidence lines.
- Refund request allocation and refund tax adjustment lines.

## Acceptance requirements

- Preserve the current routes and page shell.
- Preserve the current source DOM/Tailwind design language; only extend matching patterns.
- Every new control must have working mock interaction, validation, loading, error, success, disabled, and empty states where relevant.
- Demonstrate both dark and light themes and desktop plus narrow responsive layout.
- Do not hardcode Malaysia SST rates, tax exemptions, product classifications, or legal conclusions.
- Do not claim that registration metadata is required to enable tax collection.
- Return a complete downloadable ZIP containing the full updated project, not isolated snippets.

---

After generation, download the ZIP and provide its exact local path. Codex will extract only the approved regions and bind them to Laravel/MySQL without changing the generated DOM/classes.
