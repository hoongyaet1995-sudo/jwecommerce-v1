# EasyParcel environment-truth operational-closure mapping

## Boundary decisions

| Boundary | Decision |
|---|---|
| Convergence mode | `OPERATIONAL_CLOSURE` |
| Phase/scope | EasyParcel connection and environment truth across the existing Admin Shipping and Settings surfaces. No OAuth, quote, booking, pickup, webhook simulation, or database mutation is part of acceptance. |
| Runtime truth | MySQL `shipping_providers` and encrypted `shipping_provider_credentials`, selected through `services.easyparcel.operating_environment`. |
| Admin ownership | Provider authorization is operator-managed; connection/environment presentation is system-managed. |
| External integration | Existing real EasyParcel Open API connection. |
| UI authority | Existing Google AI Studio Admin DOM/Tailwind, L0 data-binding and L1 copy corrections only. |
| Lifecycle | Realtime provider status; immutable shipment/quote history remains unchanged. |
| Delete semantics | None. |
| Release surface | Official protected `/admin/shipping` and Settings shipping summary. |
| Cutover approval | User approved implementation on 2026-09-02. |

## Capability mapping

| Feature ID | Business purpose | Business owner | Current source | MySQL truth | Admin/system producer | Service/API contract | Operational consumer | Eligibility | Empty/default behavior | Time semantics | UI source and level | External dependency | Closure status | Blocking reason | Acceptance evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| easyparcel.connection.overview | Show the same connection/environment truth in Overview and Courier Integrations. | System-managed | Provider API view model. | `shipping_providers` for the operational environment. | EasyParcel OAuth/provider service. | Protected provider-status endpoint -> Admin provider view model. | Shipping Overview provider card and carrier allocation. | Authorized Admin. | Missing provider shows not connected; connected production shows Live Production without wallet claims. | Realtime. | Existing Overview card, L0/L1. | EasyParcel. | `CLOSED` | None. | Connected-production component rendering test; authenticated localhost browser proves the real disconnected/default branch and preserved layout. Live production cutover remains pending release approval. |
| easyparcel.pickup.truth | Keep unsupported pickup scheduling distinct from provider connectivity. | System-managed. | Intentionally fixed dashboard-managed capability statement. | No pickup record by design until an API booking exists. | None by design; EasyParcel Dashboard remains the operator path. | No JW Studio pickup API in this phase. | Overview pickup actions. | Authorized Admin. | Show dashboard-managed/unavailable-in-JW-Studio state without claiming disconnection. | Realtime presentation. | Existing Overview controls, L1 copy. | EasyParcel Dashboard. | `CLOSED` | None. | React interaction test and authenticated localhost browser toast/button proof. |
| easyparcel.webhook.environment | Label sanitized webhook receipts without falsely calling production receipts sandbox. | System-managed. | Real receipt API with an environment-neutral label. | EasyParcel webhook receipt/audit tables. | Webhook ingress and reconciliation. | Protected webhook-receipt endpoint. | Tracking & Webhooks audit feed. | Authorized Admin. | Neutral provider label because receipt environment is not present in the DTO. | Append-only audit. | Existing audit row, L1 copy. | EasyParcel webhook. | `CLOSED` | None. | React receipt rendering test and authenticated localhost browser over real receipt rows. |
| easyparcel.settings.capability | Describe only implemented EasyParcel capabilities. | Presentation-only. | Intentionally fixed capability copy. | None; top-up API is not integrated. | None by design. | None. | Settings courier summary. | Authorized Admin. | Quote/AWB capability only; account funding remains in EasyParcel Dashboard. | Fixed capability description. | Existing Settings card, L1 copy. | EasyParcel Dashboard. | `CLOSED` | None. | Source ownership review and successful Admin build; no wallet/top-up action is present. |
| easyparcel.runtime.environment_errors | Keep quote, booking, and tracking diagnostics truthful in production. | System-managed. | Operational provider environment. | Operational provider environment and shipment/quote records. | Laravel fulfillment/tracking services. | Protected quote/booking/status endpoints and tracking queue. | Admin operational errors and scheduled tracking. | Connected operational environment. | Environment-neutral provider errors; queue uniqueness includes operational environment. | Realtime diagnostics and scheduled polling. | L0. | EasyParcel. | `CLOSED` | None. | Isolated SQLite: 62 EasyParcel fulfillment/tracking tests, 439 assertions. |

## Required trace

```text
EasyParcel OAuth/provider configuration
-> Laravel provider status for operational environment
-> Admin provider view model
-> Courier Integrations, Overview, Fulfil Order, webhook audit and Settings
-> one consistent connected/production presentation
```

## Closure report

- `CLOSED`: Overview connection/environment projection, pickup capability truth, webhook receipt labelling, Settings capability copy, and runtime environment diagnostics.
- `SYSTEM_MANAGED`: provider connection/environment selection and tracking queue identity.
- Blocking gaps: none in this bounded environment-truth batch.
- Explicit exclusions: OAuth mutation, live quote, booking, pickup creation/cancellation, webhook simulation, database migration, and provider credential changes.
- Evidence: 35 targeted Admin tests; TypeScript check; production Admin build; 62 isolated Laravel tests with 439 assertions; authenticated localhost Overview/Webhook/browser-console acceptance; screenshots under `output/playwright/easyparcel-environment-truth`.
- Official-route cutover: localhost accepted; GitHub/Hostinger synchronization awaits explicit release approval.
