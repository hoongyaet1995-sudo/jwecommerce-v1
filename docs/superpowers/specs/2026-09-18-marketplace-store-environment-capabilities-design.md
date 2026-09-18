# Marketplace Store Environment and Capability Design

Date: 2026-09-18

## Problem

JW Ecommerce currently combines three separate concerns:

1. the provider API environment used by a connection;
2. whether the connected seller account is a provider test account or a production account;
3. which write operations JW Ecommerce is permitted to perform for that store.

Shopee publishing is currently constrained by its sandbox connection. Lazada publishing is constrained by one hard-coded official test seller, application identity, credential source, and a short internal lease. The newly authorized Lazada OAuth store `300927968304` is a valid production seller connection, but it cannot pass the old official-test-store gate.

Replacing that gate with an unrestricted global switch would allow any connected production seller to receive catalog, order, inventory, and fulfilment writes. A successful OAuth connection proves identity and delegated access; it does not prove that an administrator intended to enable every write operation.

## Decision

JW Ecommerce will support multiple test and production stores at the same time. Environment selection, verified account identity, and operational capabilities will be stored per connection and enforced centrally on the server.

The first acceptance phase will enable only product publishing for Lazada store `300927968304`. Order receive, inventory update, and fulfilment writes remain disabled for that store until their separate acceptance phases are completed.

## Store Model

The existing `marketplace_stores.environment` remains the provider transport environment:

- `sandbox`: use the provider's sandbox transport and sandbox application credentials;
- `live`: use the provider's regional production transport and production application credentials.

A new `account_mode` records the semantic account type:

- `test`: an account verified as a provider test account or deliberately designated as an acceptance account;
- `production`: a real seller account;

These values must not be inferred from UI input alone. The OAuth or test-account callback verifies the returned provider identity, application identity, region, and store ID before persistence.

For Lazada, a production seller and an official borrowed test account may use the same regional API family. `account_mode` therefore cannot be used as an API hostname by itself. The provider driver resolves the endpoint from platform, transport environment, region, and the application configuration.

## Per-Store Capabilities

Create a `marketplace_store_capabilities` table with one row per store. It contains these booleans, all defaulting to `false` except read access after a verified connection:

- `catalog_read`
- `product_publish`
- `order_receive`
- `inventory_update`
- `fulfillment_write`

Each capability change records `changed_by`, `change_reason`, and timestamps. Capability changes are server-side admin actions. A browser request cannot supply or override an endpoint, application ID, or credential source.

Existing global configuration flags remain emergency master switches. A write is allowed only when both the global switch and the matching store capability are enabled.

Examples:

- product publishing requires `MARKETPLACE_PUBLISH_OPERATIONS_ENABLED=true` and `product_publish=true`;
- stock synchronization requires `MARKETPLACE_STOCK_SYNC_OPERATIONS_ENABLED=true` and `inventory_update=true`;
- automatic order import requires `MARKETPLACE_AUTOMATIC_ORDERS_ENABLED=true` and `order_receive=true`.

## Central Store Context

Add a `MarketplaceStoreContext` service that loads and validates:

- platform;
- store database ID and provider shop ID;
- transport environment;
- account mode;
- region;
- connection status;
- token application identity and credential source;
- enabled store capabilities.

All product, order, inventory, and fulfilment entry points use this context. Platform clients receive an already validated context rather than accepting endpoint or environment values from controllers or browser payloads.

Add a `MarketplaceEndpointResolver` owned by the backend:

- Shopee resolves sandbox or production hosts from the verified store environment and configured application identity;
- Lazada authorization remains on `auth.lazada.com`; Malaysia live API calls resolve to `api.lazada.com.my/rest`;
- a Lazada test account uses the endpoint and application identity verified for that provider-issued account, not a hostname invented from a UI toggle;
- unsupported platform/environment combinations fail closed.

## Connect Store UX

The Connect Store modal displays platform-specific connection choices:

- Shopee: `Sandbox` or `Production`;
- Lazada: `Lazada Test Account` or `Production Store`.

The choice states the intended flow. It does not grant capabilities. After authorization, the success screen shows the verified platform, store ID, region, account mode, and initial capability state.

Production stores start with read access only. Test accounts may receive capabilities allowed by the current acceptance phase, but only after their provider identity is verified. Capability controls live in the connected-store management area rather than the OAuth form.

## Lazada Acceptance Phase

For the current store:

- store database ID: `3`;
- Lazada seller ID: `300927968304`;
- region: `MY`;
- transport environment: `live`;
- account mode: `production`;
- credential source: OAuth;
- enabled capability: `catalog_read`, `product_publish`;
- disabled capabilities: `order_receive`, `inventory_update`, `fulfillment_write`.

Publishing still performs all existing direct provider checks immediately before `/product/create`: current seller identity, current category schema, required attributes, prepared Lazada-hosted images, reviewed source revision, zero initial stock, and current warehouse structure.

The old hard-coded seller `300163725140` remains supported only as an official borrowed test account. It no longer determines whether every Lazada publishing operation is permitted.

## Failure Handling

- A connected store without the required capability returns a clear `403` naming the disabled capability.
- A stale or mismatched provider identity rejects the operation before any provider write.
- Unsupported environment and region combinations fail closed.
- Uncertain provider write outcomes remain reconciliation-required and are not retried automatically.
- Disabling a store capability prevents new jobs from being created and prevents queued jobs from dispatching.
- Store disconnection disables all capabilities atomically.

## Migration and Compatibility

The migration does not infer write permissions from existing connections.

- Existing connected stores receive `catalog_read=true` and all write capabilities `false`.
- Existing `environment` values remain unchanged.
- The current Lazada store is explicitly assigned `account_mode=production` and `product_publish=true` through an audited activation command or service.
- Global flags remain in place as master kill switches.
- No stored token or secret is moved into browser-visible data.

## Testing

Backend tests must prove:

- two stores on the same platform can have different capabilities;
- OAuth identity alone cannot grant a write capability;
- Lazada store `300927968304` can create a publishing task when only `product_publish` is enabled;
- the same store cannot receive inventory, order, or fulfilment writes;
- a different connected Lazada store cannot reuse store `3` capabilities;
- queued publishing is rejected if the capability is disabled before dispatch;
- endpoints are resolved from verified server state, never request data;
- existing Shopee sandbox publishing remains functional;
- provider preflight and uncertain-result protections remain functional.

Frontend tests must prove:

- connection choices are platform-specific;
- the authorization result displays the verified mode;
- capability state is displayed accurately;
- disabled operations are not presented as active;
- the browser cannot submit an arbitrary provider endpoint.

## Delivery Sequence

1. Add the store account mode, capability persistence, central context, and endpoint resolver.
2. Migrate existing stores with read-only defaults.
3. Replace Lazada's hard-coded publishing destination gate with the central `product_publish` capability check.
4. Activate `product_publish` only for store `3` with an audit reason.
5. Verify the current Lazada product publishing flow end to end.
6. Add the platform-specific connection-mode UI and capability management UI using the existing Google AI Studio visual patterns.
7. Accept and enable order receive, inventory update, and fulfilment capabilities in separate phases.

## Out of Scope for This Phase

- enabling Lazada order receive;
- enabling Lazada inventory updates;
- enabling Lazada fulfilment writes;
- removing global emergency switches;
- exposing raw endpoints, app keys, secrets, or tokens to the browser;
- automatically enabling write permissions for every OAuth connection.
