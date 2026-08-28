# Source Extraction Manifest — EasyParcel Shipping UI binding

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\jw-ecommerce-dashboard (14).zip` (private local source)
- ZIP SHA-256: `BB91B130B021C5D9DEE6D5A6EB952A9316FBFF799CAF290A444366E045410088`
- Target Laravel project and preview route: `C:\xampp\htdocs\jwstudio-ecommerce`, existing protected `/admin/shipping`
- Requested page/region/function: Shipping dashboard courier configuration, EasyParcel quote/booking, fulfilled-shipment truth, label action gating, and tracking-state copy.
- Explicitly excluded ZIP regions: all other courier provider implementations; wallet top-up; billing reconciliation; pickup booking; smart routing; production-provider cutover.
- Acceptance intent: `REAL_DATA_BINDING` moving toward local `OPERATIONAL_CLOSURE`; only EasyParcel Sandbox can become connectable. Existing official route is already accepted; no new layout/cutover is authorized.

## Source baseline

- Source startup/build command: not run; direct component extraction is used because the requested Laravel Admin surface already contains the same selected ZIP component structure.
- Desktop viewport and baseline path: supplied Google AI Studio shipping screenshots and ZIP component source.
- Mobile viewport and baseline path: ZIP responsive Tailwind source; browser comparison required before local closure.
- Known source errors or inert controls: ZIP mock courier cards/wallet, quote, booking, tracking, and label actions previously became unavailable/inert in Laravel while real provider APIs were absent.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/shipping/CourierConfigModal.tsx` | `9763F9167371A440A8F60BAA29A4A5EA7FF29E46CB5DF25E799A12FF9C05D7EE` | EasyParcel configuration modal | `resources/admin/src/components/shipping/CourierConfigModal.tsx` | selected |
| `src/components/shipping/CourierConfigTab.tsx` | `6BB1F811A9725821894DCEB8311717773911B137B753B60510AF91E99E5B0F58` | courier provider card/grid | `resources/admin/src/components/shipping/CourierConfigTab.tsx` | selected |
| `src/components/shipping/ShippingDashboard.tsx` | `A0F7D4F45CA34F9973B0845541BE216BDE2E2D2F9A482A9260437DA0966B8172` | Shipping dashboard owner/wiring | `resources/admin/src/components/shipping/ShippingDashboard.tsx` | selected |
| `src/components/shipping/ShippingFulfillmentTab.tsx` | `C8C2D226EA6033EEDC81EF49020AFE890124C5025424FAED22799BE2C178F6A7` | Pending AWB and shipment workflow | `resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx` | selected |
| `src/components/shipping/ThermalAWBModal.tsx` | `B529F25B48DD6C509899893C1AA004EEF64637DCCD1D3A8F4115832CA57E7004` | label/waybill presentation | `resources/admin/src/components/shipping/ThermalAWBModal.tsx` | selected |
| `src/components/shipping/TrackingAndWebhooksTab.tsx` | `CDCF0F4E91170BC9B0D72F8151C262D31252B2EC91529B6BFC523FA7B2775A13` | tracking status/audit copy | `resources/admin/src/components/shipping/TrackingAndWebhooksTab.tsx` | selected |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- | --- |
| `resources/admin/src/context/AdminContext.tsx` | owns action callbacks and authenticated bootstrap state | transitive | adapter-needed |
| `resources/admin/src/types/shipping.ts` | view-model types for courier and shipment state | transitive | adapter-needed |
| protected Laravel `/admin/api/shipping/providers/easyparcel` API | safe provider configuration and OAuth start/state | existing-compatible | included |
| protected Laravel EasyParcel quote/booking APIs | real rates and durable booking | existing-compatible | included |
| protected Laravel shipment collection/events APIs | refreshable fulfillment and tracking state | existing-compatible | included |
| `resources/admin/src/data/shippingData.ts` | former mock business entities | transitive | remove from EasyParcel state ownership; retain only presentation-only structures where needed |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| provider configuration | EasyParcel card/configure modal save and connect actions | existing modal and controls remain; safe state refreshes from Laravel | available adapter |
| quote | Pending AWB action after package confirmation | existing fulfillment layout displays returned provider services only | available adapter |
| booking | selected returned service confirmation | same UI refreshes from persisted shipment; no random AWB/local state | available adapter |
| tracking | fulfillment filters and tracking/audit panel | persisted shipment events/status after reload | available adapter |
| label | existing AWB print control | remains disabled/fails closed unless a real permitted label reference is available | available adapter |
| other courier cards | source card controls | truthfully unconfigured/inactive; no mock connectivity | backend contract gap intentionally excluded |

## Binding disposition

- EasyParcel configuration is bound to the protected Laravel safe-status and credential endpoints. The browser receives only configuration/connection state; it never receives saved credentials or OAuth tokens.
- The existing provider-card health signal is derived from the Laravel connection state. The EasyParcel wallet is explicitly unavailable and no longer renders a mock balance; all non-EasyParcel cards are presentation-only and inactive.
- Shipment rows and tracking events already use the persisted shipment collection endpoint. Thermal label printing remains fail-closed because the redacted collection response has no authorized label payload.
- The ZIP contains no quote-result, returned-service selection, or booking-confirmation component/state. The Laravel quote/booking client adapters are present and covered by tests, but they are intentionally not connected to `Gen AWB`: automatically selecting a provider service or adding a new modal would violate the source-fidelity boundary. An approved source component is required before this interaction can close.

## Approval record

- Initial source boundary approved by: user, for the ZIP shipping UI and EasyParcel Open API closure.
- Dependency expansion approved by: user approved the prior shipping architecture and the Laravel API work consumed here.
- Official route cutover approved by: already accepted `/admin/shipping`; this task binds it, not replaces it.
