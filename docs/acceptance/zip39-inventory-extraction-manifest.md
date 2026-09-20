# ZIP 39 Inventory Extraction Manifest

- Private source: `C:/Users/User/Downloads/jw-ecommerce-dashboard (39).zip`
- ZIP SHA-256: `2DDBB0D584D39AC2F97A8A20A495364B4BC35773B2D91DDA454634A696B7D92B`
- Private extraction root: `C:/Users/User/AppData/Local/Temp/jw-ecommerce-dashboard-39-source-20260920`
- Selected preview route: `/admin/marketplace/inventory-preview-39`
- Official Inventory route: unchanged pending approval.

| Selected source surface | Ported file SHA-256 | Connected-preview change |
| --- | --- | --- |
| Marketplace Inventory page | `BF91094BC455048D771F076D194C055C8270440E35D980C142D0F79060892FB5` | Labels and event handlers only; DOM/classes retained. |
| Marketplace logo | `3C963ED3E12747B6BCB7FAEACED916358C39B5D441C1A5EC8CB15A3587C5EF98` | None. |
| Sub navigation | `489135CCAA8B9B0D210F5F094E0414449B537F5EE3C52312271B64A2B64D1B6C` | Inventory label replaces Inventory & WMS. |
| First count modal | `6D407A7A8A12C2A6C8E7D79CF18377E509D713549DE44753CF1B111F69B9585E` | Per-store ledger balance supplies prior quantity. |
| Intake modal | `7908B2CFA31464A830FE731816977881EA9CA25880714096760D80268C8A6682` | Real reviewed mutation handler. |
| Settings modal | `4E33463B4BDD093048A4BC6CD008423C0996C34B6D0B56DFCBF0CC0AE0DBD234` | Real mode and takeover handlers. |
| Transfers tab | `3A6456DFCAE565F20E5B5025D1E1B7465E1E0DD6306B70212B6D7F5E0546DDE9` | Dynamic stores, persisted stages, no completion timer. |
| Activity history | `14921366D68BC24019F5411CDF755D390ACC4A709FF0150409C5D5D2DA570EB3` | Reads server audit records. |
| Inventory types | `1763C0FD0686BD7EA90ABC3619F804E6CDE6A3F45C892726D1BB4286F40CDD0C` | None. |
| Inventory constants | `738FB035771931B3E867B34CD7495E6B294C0F02EB56CA16B0FC63CF399ABEF0` | Retained as unreachable source fallback only. |
| Source type barrel | `C0982F995F9DD846C16BEE262ECA690A9BD8B9E1B534828D07E074A8C3498ECC` | None. |
| Source CSS | `4E9840B9AA92BF9FCFEE5B715D2943580BAA4334FF6B29C7C0D93F942AF2C75D` | None. |

Runtime data enters through `resources/admin/src/integration/storeInventory.ts` and `Inventory39Provider`. The private ZIP and full extracted project are not committed.
