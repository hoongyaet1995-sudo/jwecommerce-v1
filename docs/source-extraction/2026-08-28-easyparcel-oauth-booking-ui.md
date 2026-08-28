# Source Extraction Manifest — EasyParcel OAuth and booking UI

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\jw-ecommerce-dashboard (15).zip` (private local source)
- ZIP SHA-256: `17FD0C8750A1701BE7895921AD2F81237C05A734F98C3D0850C774F7F99D8465`
- Target Laravel project and official route: `C:\xampp\htdocs\jwstudio-ecommerce`, protected `/admin/shipping`
- Requested page/region/function: EasyParcel courier card OAuth connection and Pending-AWB rate quote/service selection/booking modal.
- Explicitly excluded ZIP regions: direct APIs for J&T, Ninja Van, Pos Laju, Flash, DHL; wallet top-up; pickup; reconciliation; public webhooks; unrelated Shipping tabs.
- Acceptance intent: `OPERATIONAL_CLOSURE` for Demo Sandbox only. Live is visually retained as a future authorization choice but is not enabled or represented as operational until separately authorized and browser-proven.

## Source baseline

- Source startup/build command: not run; ZIP source was inspected directly. The supplied source simulates OAuth, rate retrieval and AWB booking with timers and generated local values, so it cannot be used as operational evidence.
- Desktop baseline: user-supplied OAuth modal screenshot.
- Mobile baseline: selected components' responsive Tailwind source; local browser comparison is required.
- Known source mock behavior: `EasyParcelOAuthModal.tsx` uses timer-driven fake connection data; `EasyParcelBookingModal.tsx` generates courier rates and tracking numbers locally.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/shipping/CourierConfigModal.tsx` | `F57DA3DE79ECE4D59F7D41AC9272169522BBA4D3A8BED5ADF38FF4CE655E7009` | legacy courier-config dependency | `resources/admin/src/components/shipping/CourierConfigModal.tsx` | dependency only |
| `src/components/shipping/CourierConfigTab.tsx` | `8537B0B9A5E809CFE37AD78B12DF9DD2D953EB9FBCB58B760E0427B46BF6EA92` | courier card integration trigger | `resources/admin/src/components/shipping/CourierConfigTab.tsx` | selected |
| `src/components/shipping/ShippingFulfillmentTab.tsx` | `07A4BD54B3B21F0D4517E49A4CC840E97963CC4799060D2EC5E47A1E7AFDAA7E` | Pending AWB trigger | `resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx` | selected |
| `src/components/shipping/ShippingDashboard.tsx` | `A596D4316CEEAEB9E9783EDFDAC6C9AD459F6A620284D26B49E20AF7123DE192` | selected component owner | `resources/admin/src/components/shipping/ShippingDashboard.tsx` | selected |
| `src/components/shipping/EasyParcelOAuthModal.tsx` | `AA62976338B1BFBB6B025F3BBEC311F96D5F5D3742314A65409DE1572CE62FF6` | OAuth connection UI/states | new `resources/admin/src/components/shipping/EasyParcelOAuthModal.tsx` | selected |
| `src/components/shipping/EasyParcelBookingModal.tsx` | `C8F11D87CD33D3A628D5924D6BB4EE93F6FF3F474B3769C882DA88303D9B4552` | quote and booking UI/states | new `resources/admin/src/components/shipping/EasyParcelBookingModal.tsx` | selected |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- |
| `AdminContext` / shipping view-model types | owns authorized request callbacks and current selected shipment state | transitive | adapter-needed |
| protected provider OAuth start/callback/status endpoints | platform-owned OAuth and persisted encrypted token connection truth | existing adapter | included |
| protected quote/booking endpoints | actual package quote and atomic booking truth | existing adapter | included |
| shipment collection endpoint | persisted Pending AWB/Ready to Ship refresh | existing adapter | included |
| `.env` platform OAuth credentials | server-owned Client ID/Secret only; never Admin/browser data | existing backend configuration change | required implementation boundary |
| provider wallet/capability endpoint | no contract currently exposed | backend contract gap | keep source unavailable state |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| connect/reconnect | EasyParcel card → OAuth modal → Continue | real backend redirect to EasyParcel, callback refreshes persisted connection | available adapter after server config boundary |
| disconnect | connected OAuth modal action | revokes local connection/token record and refreshes card | available adapter |
| rate request | Pending AWB action → booking modal | real Laravel quote for immutable package snapshot; no generated rates | available adapter |
| service selection / booking | select exactly one returned service → confirm | real atomic booking and persisted AWB/Ready to Ship refresh | available adapter |
| live selection | source environment card | visible but inactive until later production acceptance | intentionally dormant |
| wallet | source wallet region | unavailable/disabled absent real provider DTO | backend contract gap |

## Approval record

- Initial source boundary approved by: user, by providing `jw-ecommerce-dashboard (15).zip` after approving platform-owned OAuth UI direction.
- Dependency expansion approved by: user approved existing EasyParcel backend/OAuth/quote/booking closure; this task consumes those APIs and moves platform application credentials to private server environment ownership.
- Official route cutover approved by: user approved localhost-first `/admin/shipping` integration.
