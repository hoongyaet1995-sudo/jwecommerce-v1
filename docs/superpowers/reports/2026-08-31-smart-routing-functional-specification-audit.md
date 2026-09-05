# Smart Routing Engine Functional Specification & Closure Audit

**Date:** 2026-08-31
**Mode:** `AUDIT_ONLY`
**Decision scope:** Determine whether the current Admin Shipping `Smart Routing Engine` should remain in the operational product.
**Code/runtime truth inspected:** `C:\xampp\htdocs\jwstudio-ecommerce`

## 1. Executive decision

**Recommendation: do not retain the current Smart Routing Engine as an exposed production function.**

The current screen is a UI prototype, not an operational routing engine:

- Routing rules have no Laravel API, service, MySQL table/document, evaluator, audit trail, checkout consumer, quotation consumer, or booking consumer.
- The Admin context initializes the rule list as empty and every save/delete/toggle/reorder action is an intentional error-toast no-op.
- The “Multi-Carrier Rate Comparison Engine” calculates hard-coded prices in the browser. It does not call EasyParcel or any courier API and is not consumed by checkout or fulfillment.
- The real checkout shipping source is MySQL `store_settings.shipping_rates`.
- The real EasyParcel fulfillment flow requests provider quotations, stores a quote snapshot, requires staff to select a returned service, then books that exact service code. Smart Routing is not called anywhere in that flow.

Keeping the current tab visible creates a larger operational risk than removing it: staff can reasonably believe that rules affect checkout/carrier assignment and that displayed prices are live, while neither claim is true.

Recommended closure action for a later approved implementation phase:

1. Remove/hide the Smart Routing tab, rule modal, inert context state/actions, orphan fixtures, and smoke-only test references.
2. Preserve the real EasyParcel quote/service-selection/booking workflow unchanged.
3. Keep the future routing concept as a backlog specification, and only rebuild it when there are multiple operational carrier/provider choices or sufficient shipment volume to justify automatic selection.

## 2. Business purpose stated by the UI

The screen presents two separate capabilities under one tab.

### 2.1 Routing Rules Manager

Intended purpose:

- Assign a preferred courier and fallback courier automatically.
- Evaluate destination region/state, parcel weight, COD/prepaid mode, order value, and battery/liquid restrictions.
- Select a service type, signature requirement, priority, and operational note.
- Enable, disable, edit, delete, and reorder rules.

### 2.2 Multi-Carrier Rate Comparison

Intended purpose:

- Compare courier prices by destination, actual/volumetric weight, COD amount, and cargo flags.
- Show estimated transit time and a “Smart Recommendation.”
- Help staff choose a carrier or service.

These are legitimate future business concepts, but the current implementation does not satisfy their operational contracts.

## 3. Current implementation trace

### 3.1 UI render path

```text
ShippingDashboard
-> Smart Routing tab and rule count
-> SmartRoutingTab
   -> rule list / add-edit-delete controls
   -> browser-only rate calculator
-> SmartRoutingRuleModal
```

Relevant source:

- `resources/admin/src/components/shipping/ShippingDashboard.tsx`
- `resources/admin/src/components/shipping/SmartRoutingTab.tsx`
- `resources/admin/src/components/shipping/SmartRoutingRuleModal.tsx`
- `resources/admin/src/context/AdminContext.tsx`
- `resources/admin/src/types/shipping.ts`
- `resources/admin/src/data/shippingData.ts`

### 3.2 Rule state and mutation behavior

```text
AdminContext smartRoutingRules = []
-> ShippingDashboard renders “0 Rules”
-> Add/Edit modal constructs a client object
-> onSaveRule()
-> AdminContext displays “Smart routing unavailable” error toast
-> no state mutation, no network request, no MySQL write
```

All four mutation functions—save, delete, toggle, and reorder—are no-ops. The declared local-storage key `aurora_admin_smart_routing_rules` is not used by this rule state, and the fixture `INITIAL_SMART_ROUTING_RULES` is not imported by any consumer.

The modal also contains an incomplete frontend contract:

- It reads and displays `rule.description`, but `SmartRoutingRule` has no `description` field.
- The submitted `newRule` omits the entered description, so the value would be discarded even if persistence existed.
- The delete summary reads `rule.courierCode`, but the rule model stores the courier under `rule.action.preferredCourier`.
- `onReorderRules` is accepted as a prop but is never invoked by the tab.

### 3.3 Browser-only rate calculator

The calculator:

- computes volumetric weight as `(L × W × H) / 5000`;
- treats only Sabah and Sarawak as East Malaysia;
- constructs five hard-coded quote rows for J&T, Ninja Van, Pos Malaysia, Flash Express, and EasyParcel;
- calculates base rate, fixed SST, fuel surcharge, COD fee, transit text, and recommendation entirely in React memory;
- sorts those rows by the computed total.

It does **not**:

- call EasyParcel or direct-courier APIs;
- read connected-provider status, provider account, wallet, sender profile, or available service codes;
- validate postcode coverage;
- persist a quote or its expiry;
- feed a selected result to shipment booking;
- feed a recommendation to checkout;
- record who evaluated or selected a result.

The UI wording “live shipping rates” is therefore inaccurate. Postcode does not affect the calculation. The battery/liquid flag only alters one recommendation flag; it does not enforce a carrier/service restriction.

## 4. Actual operational consumers

### 4.1 Checkout shipping

```text
Admin shipping-rate configuration
-> MySQL store_settings.shipping_rates
-> ShippingConfigurationService::checkoutOptions()/optionFor()
-> address eligibility + CartPricingService calculation
-> CheckoutService server-side revalidation
-> immutable orders.shipping_method / shipping_label / shipping_amount / shipping_snapshot
```

**Smart Routing consumption:** none.

Checkout does not inspect `SmartRoutingRule`, the Smart Routing React context, or the browser calculator. Deleting a UI routing rule cannot affect checkout, despite the delete dialog claiming that it will.

### 4.2 EasyParcel quotation and fulfillment

```text
EasyParcelBookingModal
-> POST /admin/api/shipping/orders/{order}/easyparcel/quotes
-> ShipmentApiController::quoteEasyParcel()
-> EasyParcelFulfillmentService::quote()
-> provider quotation API
-> MySQL shipment_quotes request/response snapshot
-> staff selects one returned serviceCode
-> POST .../easyparcel/bookings
-> EasyParcelFulfillmentService::book()
-> exact selected quote/service is validated and booked
```

**Smart Routing consumption:** none.

The operational flow is currently manual selection from real provider quotation results. The Smart Routing tab neither recommends nor auto-selects one of those returned services.

### 4.3 Other possible consumers

| Candidate consumer | Current Smart Routing use |
|---|---|
| Cart / Checkout | None |
| Order authorization | None |
| Admin order detail | None |
| EasyParcel quotation | None |
| EasyParcel booking / AWB | None |
| Manual dispatch | None |
| Webhook status updates | None |
| Shipping analytics / reporting | None |
| Database / audit history | None |
| Background jobs / scheduler | None |

The only current consumer is the Admin React view itself.

## 5. Closure matrix

| Capability | Producer | Durable truth | Runtime consumer | Current status | Blocking gap |
|---|---|---|---|---|---|
| Create/edit routing rule | Admin modal | None | None | `MOCK_ONLY` | Save closes the modal but only triggers an error toast. |
| Enable/disable/delete rule | Admin rule list | None | None | `MOCK_ONLY` | Mutation callbacks are no-ops. |
| Priority/reordering | Declared UI prop | None | None | `MOCK_ONLY` | No reorder interaction or evaluator exists. |
| Evaluate routing conditions | None | None | None | `BACKEND_CONTRACT_GAP` | No server-side deterministic evaluator. |
| Assign carrier at checkout | None | None | None | `BACKEND_CONTRACT_GAP` | Checkout consumes `shipping_rates`, not routing rules. |
| Recommend a quoted service | None | None | None | `BACKEND_CONTRACT_GAP` | Real EasyParcel quote results never enter the rule engine. |
| Auto-book selected service | None | None | None | `BACKEND_CONTRACT_GAP` | No safe booking policy, authorization, idempotency, or audit contract. |
| Multi-carrier price comparison | Browser formula | React memory | Admin view only | `MOCK_ONLY` | Hard-coded prices presented as live data. |
| EasyParcel quote + staff selection | Provider API + Admin | MySQL quote/shipment records | Fulfillment booking | Operationally separate | Existing real workflow; not powered by Smart Routing. |
| Checkout delivery methods | Admin shipping settings | MySQL `shipping_rates` + order snapshot | Checkout/order | `CLOSED` | Existing operational flow; not powered by Smart Routing. |

## 6. Operational and product risks if retained as-is

| Risk | Severity | Why it matters |
|---|---:|---|
| False automation belief | Critical | Staff may assume a rule has assigned a courier while fulfillment still requires manual quote selection. |
| False price/ETA belief | Critical | Hard-coded fees and transit estimates may be mistaken for provider quotations. |
| Silent rule loss | High | A completed rule form closes without persistence. |
| Incorrect checkout expectation | High | Delete copy claims checkout impact that does not exist. |
| Unsupported carrier choices | High | Rules can target direct couriers that have no operational provider integration. |
| No decision audit | High | No matched-rule/version/candidate/reason snapshot exists for disputes or reconciliation. |
| Cargo/COD compliance gap | High | Flags do not validate the capabilities or restrictions of actual quoted services. |
| Maintenance ambiguity | Medium | Orphan fixtures, unused storage key, unused reorder callback, and inconsistent fields resemble unfinished production code. |

## 7. Decision options

### Option A — Remove/hide the current function now (**recommended**)

Scope:

- Remove the Smart Routing navigation tab and its `0 Rules` badge.
- Remove `SmartRoutingTab`, `SmartRoutingRuleModal`, rule types/state/no-op actions, unused storage key, orphan fixtures, and smoke-only UI assertions if they have no other consumers.
- Preserve EasyParcel integration, real quotes, manual service selection, booking, AWB, and checkout shipping rates.
- Preserve this report as the future product specification seed.

Pros:

- Eliminates misleading production behavior immediately.
- Reduces Admin complexity without changing operational shipping.
- Avoids investing in automation before the carrier/provider boundary is mature.

Cons:

- No automatic carrier recommendation in the short term.
- Removing an existing Google AI Studio tab is a visible UI change and requires explicit approval before implementation.

Risk: low, provided removal is dependency-checked and the real EasyParcel flow is regression-tested.

### Option B — Keep it visible but label it “Coming Soon”

Pros: preserves visual roadmap awareness.

Cons: still occupies production navigation, still confuses operators, and retains dead code. A disabled future-feature card is not an operational capability.

Risk: medium. Not recommended.

### Option C — Operationalize it now

Pros: could reduce manual decisions at higher shipment volume.

Cons: substantial backend, policy, safety, provider-normalization, audit, permissions, testing, and production-observability work. Current single operational aggregator flow does not yet justify this complexity.

Risk: high until the contract below is implemented and accepted.

## 8. Required specification if Smart Routing is rebuilt later

The future engine should route **real provider quote candidates**, not invent courier/service codes before quotation.

### 8.1 Functional contract

1. Admin creates a versioned routing policy in MySQL.
2. System obtains real quotation candidates from every connected, enabled provider.
3. System normalizes each candidate: provider, courier, service code, price/currency, ETA, COD capability, pickup/drop-off mode, and cargo restrictions.
4. A deterministic server-side evaluator applies active rules by explicit priority.
5. Default mode returns a recommendation with matched-rule reasons; staff still confirms booking.
6. Optional auto-book mode is a separate, explicitly enabled permission and policy—not the default.
7. Booking revalidates quote ownership, expiry, order state, provider configuration version, and exact service availability.
8. The shipment stores an immutable routing-decision snapshot: rule/version, candidates, selected service, reasons, actor/mode, timestamps, and fallback path.
9. If no rule matches or a preferred service is unavailable, the flow returns to manual selection; it never silently chooses an unquoted service.

### 8.2 Minimum data ownership

- `shipping_routing_rules` or an equivalent versioned MySQL document.
- Durable rule conditions/actions with validation.
- Routing decision/audit record linked to order, quotation, and shipment.
- Provider-neutral normalized quote candidate schema.
- Permission and feature-flag ownership.

### 8.3 Safety and acceptance requirements

- Recommendation mode cannot create a production booking.
- Auto-book requires separate authorization, idempotency, retry/reconciliation behavior, and an explicit spend/control limit.
- COD and restricted-cargo rules must use actual service capabilities returned or documented by the provider.
- Automated and manual choices must be distinguishable in the audit trail.
- Unit tests cover rule ordering, overlap, fallbacks, no-match behavior, quote expiry, and unavailable services.
- Feature tests prove Admin -> MySQL -> evaluator -> real quote candidate -> immutable decision snapshot.
- Browser acceptance proves rules survive refresh and that the displayed recommendation matches the persisted decision.

## 9. Recommended closure boundary

For the current JW Studio operating model—one EasyParcel merchant profile, real provider quotations, and staff-confirmed booking—the valuable function is **real quotation comparison inside the EasyParcel Booking modal**, not a separate fake multi-carrier calculator or unconsumed rule editor.

Therefore:

- **Remove now:** current Smart Routing tab and mock calculator/rule manager.
- **Keep:** checkout shipping configuration and the real EasyParcel quotation/booking/AWB lifecycle.
- **Defer:** provider-neutral automatic recommendation/routing until multiple operational providers or measurable routing volume exists.

## 10. Audit evidence and limitations

- Repository-wide searches found no Smart Routing route, controller, service, model, migration, job, or operational test.
- Existing test coverage only opens the tab/modal; it does not prove persistence or runtime effect.
- This audit did not trigger a production booking, payment, label purchase, or carrier action.
- No application source was changed as part of this audit; this report is the only added artifact.
