# Unified Fulfilment Google AI Studio extraction manifest

- Source archive: `jw-ecommerce-dashboard (19).zip`
- Archive SHA-256: `CE109B4916147E29D0EE2D1446B5968F1512D95C8F8EF3B68D3A794E750D7AD2`
- Private extraction root: `%LOCALAPPDATA%/Temp/jw-ecommerce-dashboard-19-ce109b49`
- Classification: `OPERATIONAL_CLOSURE`

## Selected source boundary

| Source component | SHA-256 | Integration decision |
| --- | --- | --- |
| `ChooseFulfillmentMethodModal.tsx` | `AF9A915D042BD84AEC19EC066F9FCB9993B2C188DE84844CA997DBDF9C16FE00` | Preserve modal DOM/classes/copy; replace mock quote and client-only dispatch handlers at the event boundary. |
| `UpdateShipmentStatusModal.tsx` | `48C3E8C807C5942BD991AB93F214C3C7F2701634AC723B2EFC9E85EE63426015` | Preserve status-card UI; bind save to protected shipment-event API. |
| `ShippingFulfillmentTab.tsx` | `13253A57ED02E6C19663CA5166C0CBCD5F78A63D722D5916DDD1E7717F7354F7` | Integrate unified trigger, management badges, and filtering into the operational tab without removing EasyParcel recovery controls. |
| `ParcelTrackingModal.tsx` | `279A4F01EB7C32A26BD065309C0C75C187FEA0721F2280E28C8C6969B54F83CD` | Integrate fulfilment metadata panel into the existing redacted tracking view. |
| `types/shipping.ts` | `3C14DA858F3CB436F6DABD7740C71F4431E7C57A95E5D89DAFC6F71BA7FCF443` | Adopt only fulfilment/rate/management contracts required by selected components. |

Excluded: Courier Configuration, Smart Routing, Billing Reconciliation, mock shipment fixtures, simulated EasyParcel rates, recipient PII, and unrelated dashboard surfaces.

## Fidelity exceptions required for operational truth

- Mock `setTimeout` EasyParcel prices were replaced by the existing authenticated quote and booking APIs.
- Browser-only External/Manual mutations were replaced by Laravel transactions and MySQL-backed shipments/events.
- Recipient phone/address from the ZIP stays redacted in the shipment-list DTO; the approved restricted-recipient copy is retained.
- Provider badges reflect the real connection state instead of always claiming EasyParcel is connected.
