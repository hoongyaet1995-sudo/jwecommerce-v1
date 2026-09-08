# Lazada inventory write contract — verified 2026-09-08

Official Lazada documentation confirms that the generic seller inventory API is
`/product/price_quantity/update`. It updates total quantity and rejects a
quantity below provider-held withhold, occupy or campaign inventory. For
multi-warehouse sellers, the same API accepts per-warehouse
`MultiWarehouseInventories` quantities. Lazada-managed FBL/VMI inventory is
not editable via this API.

JW implementation implications:

- A JW physical warehouse must be explicitly mapped to the remote Lazada
  warehouse code before it may be included in a write request.
- A calculated WMS ATP is a desired sellable quantity, not proof that Lazada's
  held, occupied, campaign or FBL quantities allow the write.
- Every remote attempt must retain the immutable WMS balance revision and
  remote item/SKU/warehouse identity, and a failed or ambiguous request must
  be reconciled by a subsequent read before retry.
- The first real write remains disabled until a user explicitly authorizes a
  bounded test quantity and its read-back. This document does not authorize a
  stock write.

Sources consulted:

- Lazada [Inventory Management API](https://open.lazada.com/apps/doc/doc?docId=121234&nodeId=42715)
- Lazada [Inventory calculation logic](https://open.lazada.com/apps/doc/doc?docId=121233&nodeId=42714)
- Lazada [Product API overview](https://open.lazada.com/apps/doc/doc?docId=120945&nodeId=29614)
