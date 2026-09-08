# Marketplace OAuth and catalog: phase one design

Status: business direction approved; technical design and missing UI source awaiting review. No implementation or release is claimed by this document.

## Approved business boundaries

- One JW Ecommerce deployment belongs to one company, with multiple seller stores. No shared multi-company tenancy is introduced.
- JW owns the developer application. Application IDs and secrets belong in server environment configuration, never merchant forms or browser payloads.
- Each external sellable variant maps to at most one JW variant. Within a store, a JW variant maps to at most one external variant. Across different stores, the same JW variant may be reused.
- Standalone products also resolve to a sellable variant. Bundle and quantity-conversion mappings are excluded.
- Scope: authorize, read catalog, manually map/unmap. No remote publishing, inventory writes, order ingestion or automated suggestions in this phase.

## Approach and alternatives

Recommended: reuse reviewed provider-specific Shopee/Lazada protocol code from JW Dashboard, behind Ecommerce-owned services and contracts. Keep the approved Google AI Studio presentation and replace data ownership only.

Rejected: copying the entire Dashboard Marketplace subsystem, because its generic LiveTransport includes live_stub success and its models, tenant assumptions and diagnostics differ.

Deferred: a central OAuth broker shared by all deployments. It introduces a separate service, identity and routing boundary. Do not build it implicitly. First verify whether each provider application accepts each approved deployment callback. If not, require a separate broker design before onboarding another deployment.

## Ownership and security

- Server application configuration: provider enabled flag, environment, application identifier, secret and exact callback URI. Never copy existing secret values to docs, logs, source control or browser forms.
- Per-store encrypted token records: access/refresh tokens and separate expiry timestamps. Tokens are not stored in .env because each seller store has its own authorization.
- Start authorization requires authenticated staff permission. Use expiring, random, one-use state bound to actor, provider, deployment and requested reconnect target. Atomically consume state; never accept an unrelated callback or log raw callback codes/tokens.
- Resolve external store identity from provider-confirmed data, not typed names. Reconnect must preserve the original identity; unexpected account changes require explicit handling, never overwrite another store.
- Disconnect stops local jobs and token use, preserves historical mappings and business data, and distinguishes local disconnect from provider-side revocation.
- Token refresh is serialized per store, retains refreshed credentials atomically and reports reconnect-required on terminal authorization failure.

## Catalog and mapping

- Fetch means read the remote catalog into a local external-catalog snapshot. It must not create JW products, alter stock, or publish storefront content.
- Use stable provider/store/product/variant identities, not SKU strings or names. Import repeated pages idempotently.
- Persist fetch run state, progress, timestamps and sanitized errors. Failed or incomplete runs must not make absent items look deleted. Missing price/stock remains unknown, not manufactured zero.
- Database-backed filtering/pagination is required for grouped and flat views. Pagination counts must state whether they count products or variants; no claim of 300+ acceptance without a representative test.
- Add database-backed per-store JW-variant uniqueness and matching service checks. Validate existing rows before migration; if conflicts exist, report them and do not silently delete/reassign mappings.
- Retain atomic audit events. Concurrent attempts must not bypass uniqueness. Unlink changes mapping only, never stock or historical orders.

## UI contract

Keep existing Connect Store, Mapping and navigation visual language. New merchant OAuth and multi-state catalog progress constitute an ADMIN_UI_GAP requiring approved Google AI Studio Admin source before visual implementation.

Merchant controls: connect, reconnect, disconnect, fetch, view results, retry, map and unlink. Show real store identity, country, connection status, last successful fetch and actionable failures. No app secrets, token controls, scope checkboxes, sandbox selectors, simulated data tools or invented healthy metrics.

TikTok and Shopify remain explicitly unavailable until separately implemented; do not imply equal capability because a platform tab exists.

## Acceptance gates

1. Mapping tests: reject same-store duplicates, allow cross-store reuse, validate variant parent, no stock changes, concurrent conflict and audit correctness.
2. OAuth tests: state expiry/replay, cancellation, wrong identity, failed exchange, secret redaction, reconnect and isolated store tokens.
3. Catalog tests: multiple pages, retry, duplicate pages, partial failure, stale runs, unknown fields and no JW mutations.
4. Browser: approved source desktop/mobile comparison; connect/reconnect/cancel/error flows; fetch progress and paginated mapping; refresh persistence.
5. Real provider gate: approved callback and app eligibility, seller explicitly completes login, actual read-only catalog response. Mocked HTTP tests do not satisfy this gate.
6. Local development and acceptance first. GitHub/Hostinger sync and console callback changes require phase-specific approval. Existing Dashboard app configuration must not be overwritten.

## Current closure classification

| Capability | Status |
| --- | --- |
| Merchant OAuth states | ADMIN_UI_GAP |
| Provider authorization/token handling | BACKEND_CONTRACT_GAP |
| Real paginated catalog ingestion | BACKEND_CONTRACT_GAP |
| Per-store strict mapping uniqueness | BACKEND_CONTRACT_GAP |
| Publishing / inventory writes | DORMANT |

Next step: approve the technical design, obtain the narrow Admin UI export, then execute a test-first implementation plan. App eligibility and callback settings remain explicit external verification gates, not assumed facts.
