# Fulfilment Correction Source Extraction Manifest

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\jw-ecommerce-dashboard (20).zip`
- ZIP SHA-256: `637b4eda85724ae04c2f60619fcb42da31c43009535041e85a3765d5e97be56c`
- Target Laravel project: `C:\xampp\htdocs\jwstudio-ecommerce`
- Preview surface: authenticated localhost Admin component preview before official `/admin/shipping` and `/admin/orders` cutover
- Requested page/region/function: `CorrectFulfilmentModal`, `AmendDeliveryDetailsModal`, the Shipping table action triggers, and the Admin Order shipping-address trigger
- Explicitly excluded ZIP regions: every Dashboard, Storefront, Catalog, Customer, Report, Marketplace, Settings, and Shipping region not required by the selected components
- Acceptance intent: `OPERATIONAL_CLOSURE`; the ZIP supplies presentation and interaction only, while Laravel and MySQL own every mutation

## Source baseline

- Source startup command: `npm run dev`
- Source verification: `npm run lint` passed; `npm run build` passed with only the existing large-chunk warning
- Correct Fulfilment desktop baseline: `C:\Users\User\AppData\Local\Temp\jw-ecommerce-dashboard-20-637b4eda8572\.playwright-cli\page-2026-09-02T11-04-02-043Z.png`
- Correct Fulfilment 390x844 baseline: `C:\Users\User\AppData\Local\Temp\jw-ecommerce-dashboard-20-637b4eda8572\.playwright-cli\page-2026-09-02T11-04-06-656Z.png`
- Amend Delivery Details desktop baseline: `C:\Users\User\AppData\Local\Temp\jw-ecommerce-dashboard-20-637b4eda8572\.playwright-cli\page-2026-09-02T11-04-41-932Z.png`
- Amend Delivery Details 390x844 baseline: `C:\Users\User\AppData\Local\Temp\jw-ecommerce-dashboard-20-637b4eda8572\.playwright-cli\page-2026-09-02T11-04-46-453Z.png`
- Known source console error: missing `/favicon.ico` only
- Known source integration defect: `ShippingFulfillmentTab` passes `onOpenAmendAddress`, while `CorrectFulfilmentModal` defines `onOpenAmendDeliveryDetails`. The Laravel adapter must use the defined prop name without changing visible UI.
- Type-checking caveat: the ZIP omits React type declarations, so its `tsc --noEmit` does not detect the prop mismatch.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/shipping/CorrectFulfilmentModal.tsx` | `44c4519cc8c5df2976a1e4f0e7aac923f519449f3eaa5df1aabc6c071b2e871e` | component and local interaction states | `resources/admin/src/components/shipping/CorrectFulfilmentModal.tsx` | selected |
| `src/components/orders/AmendDeliveryDetailsModal.tsx` | `06bf93cea2e84ca04be6eca60a74ef6369ace9f33866e527c35552a10390cbad` | component and local interaction states | `resources/admin/src/components/orders/AmendDeliveryDetailsModal.tsx` | selected |
| `src/components/shipping/ShippingFulfillmentTab.tsx` | `6f6fb5b32b30397b3db2be9dc478c93c29922090d4040ce38724e6bec8561e8c` | selected action/button fragments only | existing `resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx` | partial extraction |
| `src/components/orders/OrderAdmin.tsx` | `792ad53a8294f60940a4a4ce787ae1f0c698cfbadc7f5dd2c8d5a1c8d8615083` | selected shipping-address action fragment only | existing `resources/admin/src/components/orders/OrderAdmin.tsx` | partial extraction |
| `src/types/shipping.ts` | `8e656d87ed9bfb331029cd471326b6178754af759e6a53135a9418bb1a78f287` | source view-model additions | existing `resources/admin/src/types/shipping.ts` | adapter-only extraction |
| `src/types/index.ts` | `f24b5db921ba0571d8ae42ae8ee8c9d85b82c0c330d8a03521a7dd4c3f4cf54b` | Order/address source types | existing `resources/admin/src/types/index.ts` | adapter-only extraction |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- | --- |
| React hooks | modal state and form interaction | direct | existing-compatible |
| `lucide-react` icons | exact source icon language | direct | existing-compatible |
| existing `ShipmentParcel`, `Order`, and `CustomerAddress` types | selected component props | direct | adapter-needed |
| existing `ChooseFulfillmentMethodModal` | replacement-method handoff | direct | existing-compatible; preserve unchanged |
| existing Admin context/toasts | mutation feedback and data reload | transitive | existing-compatible |
| ZIP `localStorage` handlers | source mock persistence | direct | excluded; replace with protected Laravel APIs |
| ZIP mock orders and shipments | screenshot/demo data | direct | excluded from repository and runtime |
| every unrelated ZIP component/style/data file | whole-app dependencies | transitive | excluded |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| Open correction | `Correct Fulfilment` row action | current shipment snapshot and two recovery modes | backend contract gap until correction API exists |
| Void and replace | primary correction action | validated acknowledgement, success, then existing method chooser | backend contract gap |
| Correct details only | second modal mode | method-specific fields, before/after-safe mutation feedback | backend contract gap |
| Resume correction | `Resume Fulfilment` row action | reopen existing method chooser | backend contract gap |
| Amend destination | Shipping row or Order detail action | validated address form and before/after diff | backend contract gap |
| Delivered/cancelled hard block | open modal for ineligible state | approved blocking alert and no mutation action | available adapter after API eligibility projection |
| Permission/loading/validation/failure | mutation lifecycle | approved source states | available adapter after API integration |
| Prototype permission toggles | source-only developer controls | simulate errors locally | excluded from official UI; real HTTP 403 drives the same visible error state |

## Approval record

- Initial source boundary approved by: user, through the confirmed design and supplied ZIP (20)
- Dependency expansion approved by: no expansion required
- Official route cutover approved by: not yet; localhost isolated preview acceptance is required before exposing the new actions on official Admin routes
