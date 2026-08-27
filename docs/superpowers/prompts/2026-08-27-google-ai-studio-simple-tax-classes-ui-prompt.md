# Google AI Studio Prompt — Simple Store-Wide Tax Classes

Copy the text below into the existing JW Ecommerce Admin Google AI Studio project that produced `jw-ecommerce-dashboard (13).zip`.

---

Update the existing JW Studio Ecommerce Admin Dashboard. This is a focused simplification of the current Tax Zones screen, not a redesign.

Preserve the existing Admin shell, sidebar, header, dark/light themes, typography, colors, spacing, Tailwind utility language, icons, responsive behavior, table styling, modal styling, toast patterns, and confirmation patterns. Do not use a third-party UI library. Do not redesign unrelated screens.

This is UI/UX source generation only. Use React + TypeScript and functional mock state. Do not add Laravel, PHP, database code, or real API calls. Return a complete downloadable project ZIP, not snippets.

## Business model

- The store may save multiple Tax Classes, for example `SST`, `GST`, or `Service Charge`.
- Every product automatically uses the one active Tax Class.
- Zero or one Tax Class may be active; never more than one.
- A Tax Class contains only Name, Tax Rate Percentage, and Active status.
- Tax Rate must be from 0 through 100.
- Activating one class automatically deactivates the previous class.
- The active class cannot be deleted. An inactive class can be deleted.
- There are no product tax assignments, Tax Zones, Geo Zones, Tax Rules, priorities, compound taxes, effective dates, address rules, shipping tax, inclusive-price settings, or live tax inspector.

## Replace the Tax Zones page

Create a focused `TaxClassesSettingAdmin` screen using the existing Tax Zones page's visual language. Update the Settings navigation and route registration to show `Tax Classes` instead of `Tax Zones`.

The page contains:

1. Header
   - Eyebrow matching the current settings page style.
   - Title: `Tax Classes`
   - Description: `Configure one store-wide percentage charge. Only one Tax Class can be active at a time.`
   - Existing primary-button pattern: `Add Tax Class`.

2. Unconfigured warning
   - When no class is active, show a compact warning using an existing alert/card pattern.
   - Exact copy: `No active Tax Class. New orders will not be charged tax.`

3. Tax Classes table
   - Columns: Tax Class Name, Tax Rate, Status, Actions.
   - Status badges: Active and Inactive.
   - Tax Rate format examples: `8%`, `6%`, `0%`, `8.125%` without unnecessary trailing zeroes.
   - Actions: Edit, Activate or Deactivate, Delete.
   - Keep desktop and narrow responsive behavior consistent with the existing settings tables.
   - Provide a clear empty state when there are no classes.

4. Add/Edit modal
   - Reuse the existing settings modal DOM and Tailwind patterns.
   - Fields:
     - `Tax Class Name` — required text input; examples may include SST, GST, Service Charge.
     - `Tax Rate (%)` — required numeric input from 0 to 100, supporting up to four decimal places.
     - `Active` — switch/checkbox.
   - Save and Cancel actions.
   - Loading, saving, validation-error, and success interactions.
   - Duplicate-name error.
   - Invalid-rate error.

5. Activation confirmation
   - When another class is already active, confirm activation using the existing confirmation pattern.
   - Explain that the currently active class will become inactive.
   - Exact message structure: `Activate {new name}? {current name} will be deactivated. New carts and orders will use {new rate}%. Historical orders will not change.`

6. Deactivation confirmation
   - Exact meaning: new carts and orders will have no tax until another class is activated.

7. Delete confirmation
   - Inactive classes use the existing delete-confirmation modal.
   - The active class Delete action is disabled.
   - Helper text: `Deactivate this Tax Class before deleting it.`

## Product Editor change

In `src/components/catalog/ProductEditorModal.tsx`, remove the Tax Class field completely. Restore the Basic Info & Pricing flow so Primary Category is followed directly by Base Retail Price and Status, using the surrounding original DOM/classes. Do not add a replacement product-level tax control.

## Navigation and routing

- Settings navigation label: `Tax Classes`.
- Canonical Admin path represented by the UI: `/admin/settings/tax-classes`.
- Remove Tax Zones terminology from active navigation and headings.
- Preserve all unrelated routes and pages.

## Required TypeScript mock contract

Use a focused type:

```ts
export interface TaxClass {
  id: string;
  name: string;
  ratePercent: number;
  isActive: boolean;
  createdAt?: string;
  updatedAt?: string;
}
```

Provide functional mock handlers for create, update, activate, deactivate, and delete. The mock activation handler must enforce one active row.

## Demonstration states

Include mock examples for:

- `SST` at 8%, Active.
- `GST` at 6%, Inactive.
- `Service Charge` at 10%, Inactive.
- No-active warning state.
- Empty list state.
- Duplicate-name validation.
- Invalid percentage validation.
- Activation confirmation.
- Deactivation confirmation.
- Active-delete-disabled state.

## Acceptance requirements

- Preserve the existing JW Studio visual system exactly.
- No Tax Rates tab.
- No Geo Tax Zones tab.
- No Tax Engine Configuration tab.
- No Live Tax Rate Inspector.
- No Product Tax Class selector.
- No country, state, postcode, shipping-tax, priority, compound, date, or inclusive-tax controls.
- Demonstrate dark and light themes.
- Demonstrate desktop and narrow responsive layouts.
- Project must build without TypeScript errors.
- Return the complete updated project as a downloadable ZIP and list every changed file.

---

After generation, download the ZIP and provide its exact local path to Codex.
