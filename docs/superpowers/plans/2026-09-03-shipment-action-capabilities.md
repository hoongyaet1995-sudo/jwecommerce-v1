# Shipment Action Capabilities Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every Fulfillment & Waybills tab, badge, primary action, and dropdown action consistent with the shipment lifecycle.

**Architecture:** Laravel computes a canonical capability policy for each read-model row. React preserves the approved Google AI Studio presentation and renders only the capabilities returned by the API, with a narrow legacy fallback removed after the contract is wired.

**Tech Stack:** Laravel 12, PHP 8, PHPUnit, React 19, TypeScript, Vitest.

**Spec:** `docs/superpowers/specs/2026-09-03-shipment-action-capability-design.md`

## Global Constraints

- Work only in `C:\xampp\htdocs\jwstudio-ecommerce`.
- Preserve existing Google AI Studio DOM, Tailwind classes, icons, spacing, and menu behavior.
- Do not call real courier APIs or mutate real shipment state during acceptance.
- Do not push or deploy without a new synchronization approval.

---

### Task 1: Backend capability policy

**Files:**
- Create: `app/Services/Admin/ShipmentActionPolicy.php`
- Modify: `app/Services/Admin/ShipmentDataService.php`
- Test: `tests/Unit/Services/Admin/ShipmentActionPolicyTest.php`

**Interfaces:**
- Consumes: normalized shipment read-model arrays.
- Produces: `lifecycleStage`, `labelState`, `recoveryState`, and `capabilities`.

- [ ] Write literal table-driven tests for initial pending, replacement pending, Ready to Ship, In Transit, Delivered manual, and exception recovery rows.
- [ ] Run the test and confirm failure because the policy does not exist.
- [ ] Implement the smallest policy satisfying the matrix.
- [ ] Run the unit test and relevant shipment feature tests.

### Task 2: API and TypeScript adapter contract

**Files:**
- Modify: `resources/admin/src/types/shipping.ts`
- Modify: `resources/admin/src/integration/shipmentData.ts`
- Test: `tests/Feature/Admin/ShipmentApiTest.php`
- Test: `resources/admin/src/integration/shipmentData.test.tsx`

**Interfaces:**
- Consumes: Laravel capability payload.
- Produces: a typed `ShipmentParcel` contract without reconstructing business rules in React.

- [ ] Add failing API and adapter assertions for the canonical fields.
- [ ] Run focused PHP and Vitest tests and confirm contract failures.
- [ ] Wire policy output and adapter mapping.
- [ ] Re-run both focused suites.

### Task 3: Capability-driven Google AI Studio UI

**Files:**
- Modify: `resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx`
- Test: `resources/admin/src/components/shipping/ShippingDashboard.test.ts`

**Interfaces:**
- Consumes: `ShipmentParcel.capabilities`, `lifecycleStage`, `labelState`, and `recoveryState`.
- Produces: one valid primary action and a status-appropriate secondary menu.

- [ ] Add failing UI tests proving replacement rows leave Pending, delivered/manual rows never show AWB Pending, and invalid dropdown actions disappear.
- [ ] Run the focused test and verify the expected failures.
- [ ] Replace local status guesses with capability checks while retaining existing markup and classes.
- [ ] Re-run focused UI tests and TypeScript lint.

### Task 4: Local acceptance

**Files:**
- Modify only if a failing acceptance test exposes a defect in the files above.

**Interfaces:**
- Consumes: locally built Laravel/Admin application.
- Produces: automated and browser evidence without live courier mutations.

- [ ] Run PHP focused and regression tests.
- [ ] Run Admin Vitest, TypeScript lint, and production build.
- [ ] Clear Laravel caches and open localhost `/admin/shipping` in an authenticated browser.
- [ ] Verify each tab and representative row action menu without invoking mutation actions.
- [ ] Review `git diff` and confirm no backup directory or unrelated user file was changed.
