# Automatic Marketplace Store Inventory Management

**Date:** 2026-09-21  
**Status:** Approved direction; pending written-spec review  
**Scope:** JW Ecommerce Inventory settings, marketplace store lifecycle, and inventory synchronization eligibility

## Problem

The current Inventory Settings UI separates a connected marketplace store from a store that is "Managed by JW". An administrator must click **Takeover Inventory** after connecting the store. The takeover request performs readiness checks for product mapping, provider snapshots, first physical counts, and historical orders.

This model creates an unnecessary second opt-in. In JW Ecommerce, connecting a marketplace store to the project already expresses the decision that JW will manage its inventory. The separate takeover action is difficult to understand, currently fails without visible feedback, and blocks Shared-to-Independent mode allocation even when the JW storefront and verified physical stock are available.

The safety checks remain necessary, but they should determine whether external inventory synchronization can start. They should not determine whether the connected store belongs to JW inventory management.

## Product Decision

Every connected Shopee or Lazada store is automatically within JW inventory management. The JW Ecom Storefront is always managed by JW.

Store management membership and external synchronization readiness are separate concepts:

- **Managed by JW:** The store belongs to the project inventory architecture and follows the selected global Shared or Independent stock mode.
- **Setup readiness:** The store has sufficient catalog, mapping, count, and order-boundary data for JW to calculate and publish trustworthy ATP.
- **Synchronization state:** JW may actively broadcast ATP or temporarily pause external writes while retaining ledger ownership.

There is no employee-facing takeover or relinquishment workflow.

## Store States

The store UI exposes three operational states:

### Setup Required

The store is connected and belongs to JW inventory management, but external inventory broadcasting is disabled until required setup work is complete.

Possible setup requirements:

- Current marketplace catalog has not been fetched.
- Active marketplace listings are not mapped one-to-one to JW Master SKUs.
- An eligible production SKU has no verified physical count in the current global stock mode.
- Historical open marketplace orders have not been reconciled and assigned an ingestion boundary.
- Store authentication is expired or disconnected.

The UI groups duplicate requirements and shows counts, affected records, and a direct action such as **Review SKU Mapping**, **Refresh Catalog**, **Complete Physical Counts**, or **Review Open Orders**.

### Inventory Sync Active

The store is connected, setup requirements are clear, and JW is allowed to publish calculated ATP to the marketplace.

### Sync Paused

The store remains part of JW inventory management and keeps its ledger ownership and reservations. External marketplace inventory writes are temporarily disabled by an administrator. Resuming synchronization re-runs readiness checks before any stock broadcast.

## User Experience

### Inventory Settings

Remove the following employee-facing language and actions:

- Takeover Inventory
- Pause Takeover
- Not Managed by JW
- Crucial Policy: Unmanaged Stores & Real Physical Inventory

Rename **Store Inventory Management** to **Store Inventory Sync**.

Each store row shows connection state and one of the three operational states. A **Resolve Setup** action opens the readiness details for `Setup Required`. An admin-only **Pause Sync** action is available for `Inventory Sync Active`; **Resume Sync** is available for `Sync Paused`.

The JW Ecom Storefront always displays **Inventory Active** and does not expose setup, takeover, or pause controls.

### Connect Store

After OAuth succeeds:

1. Create or restore the JW inventory store account automatically.
2. Mark the store as part of the selected global inventory mode.
3. Fetch the provider catalog and evaluate readiness.
4. Display `Setup Required` until required preparation is complete.
5. Automatically activate inventory synchronization when readiness becomes clear, unless the administrator explicitly paused sync.

Connecting a store must never import or add its marketplace quantity to JW physical stock.

### Global Stock Mode

The global Shared or Independent mode applies to all connected stores, including stores that are still `Setup Required` or `Sync Paused`.

During Shared-to-Independent allocation:

- JW Ecom Storefront is always an eligible destination account.
- Connected marketplace stores are visible as destination accounts because they are managed by JW.
- A `Setup Required` or `Sync Paused` store may receive internal ledger allocation, but JW must not broadcast that ATP externally until synchronization is active.
- Every eligible SKU must conserve physical stock: the sum of store allocations must equal the shared physical total.
- Store setup problems do not block the global mode transition unless they create a ledger-integrity conflict such as unresolved reservations.

## Backend Model

The existing `inventory_store_accounts.management_status` takeover semantics will be replaced or normalized so that connected stores are managed automatically. The durable state must distinguish:

- account membership/ledger ownership;
- readiness result and blockers;
- external synchronization enabled or paused;
- connection/authentication status.

The implementation may retain existing columns for compatibility, but API responses and business rules must expose the new meanings. Existing connected stores are migrated automatically into JW management. No marketplace quantities are copied into physical balances during migration.

The current takeover-readiness rules become synchronization-readiness rules. They must:

- evaluate active relevant listings rather than inactive, archived, or sandbox-only records;
- group duplicate blockers in the API response;
- preserve open-order ingestion-boundary checks;
- preserve one-to-one mapping requirements for active listings;
- preserve verified-count requirements for production SKUs;
- never fail silently in the UI.

The existing takeover and pause endpoints may be retained temporarily as compatibility adapters, but the primary API should express readiness, pause sync, and resume sync. New frontend code must not call a takeover endpoint.

## Existing Store Migration

All currently connected Shopee and Lazada stores are automatically placed under JW inventory management.

- Stores that pass readiness become `Inventory Sync Active`.
- Stores with unresolved requirements become `Setup Required`.
- Previously paused stores remain `Sync Paused`.
- JW Ecom Storefront remains active.
- Existing verified physical balances remain unchanged.
- Existing marketplace snapshots remain reference data only.
- No inbound intake, physical-stock duplication, or marketplace-stock summation occurs.

## Failure Handling

- Readiness failures render inside Inventory Settings with clear actions.
- API or authorization failures show a visible error and retain the last confirmed state.
- A failed external inventory broadcast does not roll back an already committed internal allocation or mode transition.
- Retrying external synchronization must not repeat ledger allocation.

## Audit Requirements

Activity History records:

- store connected and automatically enrolled;
- setup readiness changed;
- inventory sync activated;
- inventory sync paused or resumed;
- readiness blockers resolved;
- external broadcast failed or recovered.

Each record includes store, operator or system actor, reason, previous state, resulting state, and reference IDs where applicable.

## Acceptance Criteria

1. No `Takeover Inventory`, `Pause Takeover`, or `Not Managed by JW` control remains in the employee UI.
2. JW Ecom Storefront is always available for Independent Stock allocation.
3. Every connected Shopee and Lazada store is automatically represented in the selected global inventory mode.
4. Connecting or migrating a store never changes JW physical stock.
5. A store with incomplete setup displays grouped, actionable readiness requirements.
6. Setup problems do not silently disable global Admin Sign-off.
7. A mode switch can complete using valid conserved allocations even when a marketplace store is `Setup Required` or `Sync Paused`.
8. External ATP is published only for `Inventory Sync Active` stores.
9. Pausing synchronization preserves balances, reservations, mappings, and inventory ownership.
10. Local automated tests cover automatic enrollment, migration, readiness classification, mode allocation, pause/resume, and failure visibility.
11. Browser acceptance proves the live Inventory Settings and Shared-to-Independent flow using the connected test stores.

## Out of Scope

- Importing marketplace quantities into JW physical inventory.
- Reintroducing entity warehouses or WMS workflows.
- Automatic resolution of unmapped listings or historical orders.
- TikTok Shop and Shopify inventory synchronization before those integrations are available.
- Changes to EasyParcel, shipping booking, or fulfillment logistics.
