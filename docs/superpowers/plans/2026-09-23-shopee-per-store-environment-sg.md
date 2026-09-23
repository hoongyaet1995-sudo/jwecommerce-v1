# Shopee Per-Store Environment and SG Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Connect and operate Shopee MY/SG Sandbox and Production shops side by side in one JW Ecom installation, then register each verified shop in Marketplace Hub.

**Architecture:** An immutable Shopee application context is selected at OAuth start, bound to the one-time state, and persisted through single-shop or Main Account authorization. Every later provider call resolves that context from the addressed store and its token application ID. The Connect Store modal selects Sandbox or Production; Shopee supplies the shop name and region.

**Tech Stack:** Laravel/PHP 8, MySQL, encrypted Eloquent token casts, React/TypeScript, Vitest, PHPUnit, Vite, existing Hub signed store receipts.

**Spec:** `docs/superpowers/specs/2026-09-23-shopee-per-store-environment-sg-design.md`

## Global Constraints

- Develop first in `C:\xampp\htdocs\jwstudio-ecommerce`; preserve unrelated dirty files and never run destructive migrations on the business DB.
- Keep the approved Google AI Studio modal DOM/classes except for the explicitly requested field removal and environment control in existing styles.
- Never expose app secrets, OAuth codes, access/refresh tokens, signed headers, or raw provider payloads through browser, Hub API, logs, or test output.
- Existing MY Sandbox shops must retain credentials and continue working; no environment switch may change another shop's provider host.
- Shop Account and Main Account OAuth both remain supported; callback states remain single-use and actor/deployment-bound.
- New Production/SG connection does not enable stock, publishing, fulfillment, or other provider writes.
- Provider callback cutover to Hub is a separate release gate; the signed store receipt already registers a successful Ecom shop in Hub.

## File map and interfaces

- Create `app/Services/Marketplace/ShopeeApplicationContext.php`: immutable `environment`, `partnerId`, `partnerKey`, `callbackUrl`, `oauthHost`, `apiHost` with no JSON serialization of the key.
- Create `app/Services/Marketplace/ShopeeApplicationResolver.php`: `forEnvironment(string): ShopeeApplicationContext` and `forStore(int): ShopeeApplicationContext`; enforce token application ID and store environment match.
- Modify `config/marketplace.php`: independent `shopee.applications.production` and `.sandbox` entries including callback URL; retain old config keys only as a migration compatibility layer.
- Create `database/migrations/2026_09_23_000002_bind_shopee_oauth_state_environment.php`: nullable `environment` and `application_id` on `marketplace_oauth_states`; old in-flight Shopee states expire naturally.
- Modify `OAuthStateService`, `ShopeeClient`, `ShopeeAuthorizationService`, `ShopeeOAuthBatchProcessor`, `ShopeeTokenManager`, `MarketplaceOAuthController`: carry selected context without changing global config.
- Modify `ShopeeCatalogService`, `ShopeeOrderService`, `MarketplaceAutomaticOrders`, `WarmShopeeListingMetadata` and other Shopee client call sites discovered by a final call-site search: use `forStore` before any shop API call.
- Modify `resources/admin/src/previews/marketplace30/components/marketplace/ConnectStoreModal.tsx` and `resources/admin/src/integration/marketplaceOperations.ts`: remove pre-OAuth name/region inputs and add Shopee environment selection; reauthorization locks it.
- Reuse `MarketplaceHubStoreReporter` and Hub's current store registry; no Hub schema change is required.

## Review Focus

1. A seller changes environment while the modal is open: only the final selected environment goes to the start API; callback still uses the stored state value. Pin in Task 2 and Task 6 tests.
2. A seller reauthorizes an SG Production store after selecting Sandbox: the request is rejected before redirect; original token remains untouched. Pin in Task 2 and Task 3.
3. Production and Sandbox use the same numeric shop ID: both identities remain independent by environment/application. Pin in Task 3 and Task 7.
4. A provider response returns an unknown region or mismatched shop ID: no connected store/token is created. Pin in Task 3.
5. An old background job runs after a new app configuration is saved: it fails for its addressed store without using the other environment's host or overwriting tokens. Pin in Task 4.

---

### Task 1: Immutable application context and store resolver

**Files:** Create `app/Services/Marketplace/ShopeeApplicationContext.php`, `app/Services/Marketplace/ShopeeApplicationResolver.php`; modify `config/marketplace.php`; test `tests/Feature/ShopeeApplicationResolverTest.php`.

**Interfaces:** `forEnvironment('sandbox'|'production'): ShopeeApplicationContext`; `forStore(int $storeId): ShopeeApplicationContext`; `ShopeeClient::forApplication(ShopeeApplicationContext $context): ShopeeClient` returns a clone, never mutating the container singleton.

Implementation shape:
```php
final readonly class ShopeeApplicationContext {
    public function __construct(public string $environment, public string $partnerId,
        #[\SensitiveParameter] public string $partnerKey, public string $callbackUrl,
        public string $oauthHost, public string $apiHost) {}
}
// Resolver: reject when (string) $token->application_id !== $context->partnerId.
```

- [ ] **Step 1: Write a failing resolver test.** Seed two connected shops using different environments and token `application_id`s. Assert `forStore` returns `openplatform.sandbox.test-stable.shopee.sg` versus `partner.shopeemobile.com`; a mismatched token application ID throws `RuntimeException`.
- [ ] **Step 2: Run red test.** `C:\xampp\php\php.exe artisan test --filter=ShopeeApplicationResolverTest`; expect missing resolver/context failure.
- [ ] **Step 3: Implement the context and resolver.** Resolve `config('marketplace.shopee.applications.'.$environment)`, require positive numeric partner ID, nonempty key and HTTPS callback; compare `MarketplaceStoreToken::application_id` with the chosen partner ID. Add the clone method to `ShopeeClient` and make its `configuration()` use the clone's context when present.
- [ ] **Step 4: Run green and regression.** Run the new test plus `ShopeeClientTest`. Confirm no array/log serialization path returns `partnerKey`.
- [ ] **Step 5: Commit only this task's files.** `git add` the exact files above and commit `Add per-environment Shopee application context`.

### Task 2: Bind environment and application to one-time OAuth state

**Files:** Create `database/migrations/2026_09_23_000002_bind_shopee_oauth_state_environment.php`; modify `app/Services/Marketplace/OAuthStateService.php`, `app/Http/Controllers/Admin/MarketplaceOAuthController.php`, `app/Services/Marketplace/MarketplaceHubOAuthIntent.php`; test `tests/Feature/ShopeeOAuthEnvironmentStartTest.php`.

**Interfaces:** `OAuthStateService::issue(..., ?ShopeeApplicationContext $context = null)` persists environment/app ID and context fingerprint for Shopee; `consume(...)` returns the bound row after comparing its current application fingerprint. `ShopeeClient::forApplication($context)->authorizationUrl($state)` produces the allowlisted environment's `/auth` URL.

Expected start payload/response:
```json
{"environment":"production"}
{"url":"https://open.shopee.com/auth?..."}
```

- [ ] **Step 1: Write failing start tests.** POST `environment=sandbox` and `environment=production` to the existing protected Shopee authorize route; assert distinct official HTTPS `/auth` hosts and state records. Assert invalid environment, incomplete app, and reconnect to a store of another environment return 409 without issuing state or URL. Repeat with Hub routing enabled and assert the signed intent contains the selected environment.
- [ ] **Step 2: Run red test.** `C:\xampp\php\php.exe artisan test --filter=ShopeeOAuthEnvironmentStartTest`; expect the Production request to use the old global Sandbox host.
- [ ] **Step 3: Implement state migration and start selection.** Validate `environment in:sandbox,production`, default new attempts to Sandbox only when the frontend omits it for legacy callers, and force reconnect environment from the existing store. Persist the exact partner ID and fingerprint. Do not permit the request to provide a partner ID or callback URL.
- [ ] **Step 4: Run green and replay tests.** Run `ShopeeOAuthEnvironmentStartTest` and `MarketplaceHubOAuthRoutingTest`; assert a changed partner key invalidates the pending state.
- [ ] **Step 5: Commit exact files.** Commit `Bind Shopee OAuth attempts to selected environment`.

### Task 3: Complete single and Main Account OAuth for MY/SG

**Files:** Modify `ShopeeClient.php`, `ShopeeAuthorizationService.php`, `ShopeeOAuthBatchProcessor.php`, `MarketplaceOAuthController.php`; tests `ShopeeClientTest.php`, `ShopeeOAuthEnvironmentCallbackTest.php`, `ShopeeMainAccountBatchTest.php` (create the latter two if absent).

**Interfaces:** Shop code exchange, main code exchange, per-shop seed refresh, and `shopInfo` all run on `ShopeeClient::forApplication($context)`. `OAuthStateService::consume` is called before code exchange; its environment/app ID select the context. Main Account jobs reconstruct context from the persisted batch environment/application ID.

Core identity assertions:
```php
$this->assertDatabaseHas('marketplace_stores', ['platform' => 'shopee',
    'environment' => 'sandbox', 'external_shop_id' => '227929252', 'region' => 'SG']);
$this->assertDatabaseHas('marketplace_store_tokens', ['store_id' => $storeId,
    'application_id' => $sandboxPartnerId]);
```

- [ ] **Step 1: Write failing callback tests.** Fake Shopee's shop-info result for `SG` shop `227929252`, assert encrypted token, `region=SG`, provider shop name, and environment=Sandbox in Ecom. Repeat Production with a different shop; permit the same shop ID under distinct environment/application identities. Assert wrong shop ID, unsupported region, wrong-app reconnect, replayed state, and changed app leave prior tokens unchanged. Fake a two-shop Main Account batch with one failure and assert partial success and exact-environment retry.
- [ ] **Step 2: Run red tests.** Run the two new test classes; SG should fail at the existing `region !== 'MY'` guard and Production at global configuration.
- [ ] **Step 3: Implement the minimal callback changes.** Accept only `MY|SG` from `get_shop_info`, normalize uppercase, preserve exact Shop ID checks, and use the consumed state/batch context for every code/refresh request. Keep provider name and region authoritative; never accept browser-supplied shop identity.
- [ ] **Step 4: Run green and existing OAuth suite.** Include single-shop, Main Account, replay, partial batch, and token encryption tests.
- [ ] **Step 5: Commit exact files.** Commit `Support SG identity and environment-bound Shopee OAuth`.

### Task 4: Make token rotation and read-side jobs store-scoped

**Files:** Modify `ShopeeTokenManager.php`, `ShopeeCatalogService.php`, `ShopeeOrderService.php`, `MarketplaceAutomaticOrders.php`, `WarmShopeeListingMetadata.php`; inspect and update every `ShopeeClient`/`ShopeeTokenManager` consumer in `app/Services/Marketplace` and `app/Jobs` that can address a store; tests `ShopeeStoreContextOperationsTest.php` plus existing catalog/order/token tests.

**Interfaces:** Each service resolves `$context = $resolver->forStore($storeId)` and calls `$client->forApplication($context)`. Token manager continues to return the encrypted model's plaintext only inside server memory and refreshes with the selected context.

Store-scoped call shape:
```php
$context = $applications->forStore($storeId);
$accessToken = $tokens->accessToken($storeId);
$page = $client->forApplication($context)->catalogPage($accessToken, $store->external_shop_id, $offset, $status);
```

- [ ] **Step 1: Write failing mixed-environment tests.** Seed one MY Sandbox, one SG Sandbox, one SG Production store. Fake each official API host and assert refresh, catalog page, order page, and metadata warm-up use only the addressed store's host/partner ID. A wrong application ID or changed provider configuration fails one store without affecting the others.
- [ ] **Step 2: Run red test.** `C:\xampp\php\php.exe artisan test --filter=ShopeeStoreContextOperationsTest`; expect the current global environment guard to reject Production.
- [ ] **Step 3: Implement store-scoped calls.** Replace global `marketplace.shopee.environment` filters in the token manager, automatic order scheduler and warm-up job. Search all Shopee client call sites again; no operational store-addressed path may call an unscoped client. Preserve current opt-in auto-order settings and existing write capability checks.
- [ ] **Step 4: Run green and regressions.** Run `ShopeeStoreContextOperationsTest`, `ShopeeClientTest`, catalog/order and automatic-order tests. Confirm no Production request is sent to Sandbox host, including retries.
- [ ] **Step 5: Commit exact files.** Commit `Scope Shopee tokens and reads to each store application`.

### Task 5: SG currency and write-side safety gate

**Files:** Modify `ShopeeCatalogService.php`, `MarketplaceOrderSnapshotService.php` only where current currency handling needs it; modify `MarketplacePublishingDispatchService.php` or existing write guards only if they allow an unreviewed SG write; test `ShopeeSingaporeReadSafetyTest.php`.

**Interfaces:** Provider price and currency remain paired in observed catalog/order data. SG shop-region metadata remains `SG`, and a missing/contradictory currency fails the relevant read rather than silently labeling a number MYR. Existing per-store write capabilities still control publishing/stock operations.

Currency test shape:
```php
$this->assertSame('SGD', $observedVariant['currency']);
$this->assertSame('SG', $store->region);
$this->assertFalse($capabilities->inventory_update);
```

- [ ] **Step 1: Write failing SGD tests.** Feed an SG item with `currency=SGD` and an SG order with provider currency; assert stored snapshot/UI contract retains SGD. Feed missing currency where the consumer would otherwise assume MYR; assert a clear unsupported-currency result. Assert newly connected SG store has stock push and publishing disabled.
- [ ] **Step 2: Run red test.** `C:\xampp\php\php.exe artisan test --filter=ShopeeSingaporeReadSafetyTest`; record the first actual failure before changing implementation.
- [ ] **Step 3: Implement only observed gaps.** Preserve the provider currency in existing JSON snapshots; gate a specific consumer when its schema cannot represent SGD. Do not add exchange-rate conversion or enable any provider write.
- [ ] **Step 4: Run green and MY regression.** Verify MYR catalog/orders remain unchanged, SG reads show SGD, and inventory/publishing tests still enforce existing gates.
- [ ] **Step 5: Commit exact files.** Commit `Preserve SG currency and safe read capabilities`.

### Task 6: Source-faithful environment selector in Connect Store

**Files:** Modify `resources/admin/src/previews/marketplace30/components/marketplace/ConnectStoreModal.tsx`, `resources/admin/src/integration/marketplaceOperations.ts`, and its direct caller that provides `requestAuthorization`; test `resources/admin/src/integration/marketplaceOperations.test.ts` and a focused modal test under the same preview package.

**Interfaces:** `requestMarketplaceAuthorization(platform, reconnectId?, environment?)` sends only `environment` for Shopee; `marketplaceOperations.authorize` forwards it as protected JSON. The modal's Shopee environment defaults to Sandbox for new stores and is fixed to `existingConnection.environment` for reauthorization.

Request shape:
```ts
authorize: (reconnectId?: string, platform: 'shopee' | 'lazada' = 'shopee',
  environment?: 'sandbox' | 'production') => marketplaceRequest<{url: string}>(
    `/${platform}/authorize`, 'POST', {
      ...(reconnectId ? {reconnectStoreId: Number(reconnectId)} : {}),
      ...(platform === 'shopee' ? {environment} : {}),
    }),
```

- [ ] **Step 1: Write failing UI tests.** Assert no pre-OAuth Store Display Name or editable Region control in operational mode; selecting Production posts `environment=production`, selecting Sandbox posts `environment=sandbox`, and changing selection before Proceed sends only the final value. Reauthorization shows the saved environment and does not permit changing it. An incomplete app configuration disables its choice when configuration status is available; otherwise a 409 start response keeps the modal open and shows the actionable configuration error. Preview/mock mode retains its non-operational sample behavior if the same component is shared.
- [ ] **Step 2: Run red tests.** `npm --prefix resources/admin test -- --run` with the focused test files; expect missing environment payload.
- [ ] **Step 3: Implement minimal UI binding.** Reuse existing modal button, label, border and active-state classes. Keep the provider-retrieved shop name and region on success/store cards. Only allowlist `https://open.shopee.com/auth` and `https://open.sandbox.test-stable.shopee.com/auth` before browser navigation.
- [ ] **Step 4: Run green, typecheck and build.** `npm --prefix resources/admin run lint`, `npm --prefix resources/admin run build`, focused Vitest, and localhost browser at desktop/mobile width. Compare unchanged modal regions to the accepted source.
- [ ] **Step 5: Commit only related UI/build files.** Commit `Add Shopee environment selection to Connect Store` without staging unrelated storefront build changes.

### Task 7: Hub identity, local closure, and controlled release

**Files:** Reuse `MarketplaceHubStoreReporter.php`; add or extend an Ecom reporter test under `tests/Feature` to assert two distinct signed outbound receipts. In the separate Hub project, reuse `StoreRegistryController.php` and extend its registry test to assert two persisted rows. Update `docs/acceptance/jw-ecommerce-p1-p4-master-checklist.md` only with evidenced partial/closed state.

**Interfaces:** Signed receipt identity is `(client, provider, environment, application_id, shop_id)`; Hub never receives tokens or secrets. Existing five-minute `marketplace:hub-sync` can recover a missed immediate report.

Hub-side receipt identity assertion (run only in the Hub test database):
```php
$this->assertDatabaseHas('marketplace_stores', ['provider' => 'shopee',
    'environment' => 'sandbox', 'application_id' => $sandboxPartnerId,
    'external_shop_id' => '227929252', 'region' => 'SG']);
```

- [ ] **Step 1: Write separation tests in the owning projects.** Ecom test: report the same numeric shop ID once as Sandbox and once as Production; assert two signed payloads with distinct `environment` and `application_id`, correct SG region/name, and no token fields. Hub test: feed those payloads to its test endpoint and assert two Hub rows and no overwrite of an existing MY shop. Assert a failed Ecom OAuth produces no connected receipt.
- [ ] **Step 2: Run the focused tests.** Run Ecom reporter and Hub registry tests in their respective isolated test databases. If the existing contract already passes, retain that evidence rather than force a production-code change.
- [ ] **Step 3: Run local closure.** Execute focused PHP tests, TypeScript/Vitest/build, local protected browser flow, database migration on isolated test DB, and an unrelated MY store regression. Do not mark the master acceptance row `[x]` without real seller browser evidence.
- [ ] **Step 4: Prepare release review.** Report exact changed scope, tests, remaining Production Partner ID discrepancy, and the proposed callback/feature-flag state. Obtain the release approval required by the Ecom delivery contract before pushing/deploying.
- [ ] **Step 5: Release the accepted SHA.** Push `main`, fast-forward `/home/u762348670/apps/jwecommerce`, migrate forward, verify localhost/GitHub/Hostinger SHA equality and HTTPS. Hub stays on its independent deployment; no Hub change is needed unless the real receipt exposes a contract gap.
- [ ] **Step 6: Real provider acceptance.** Connect SG Sandbox shop `227929252` through the browser; verify Shopee callback, Ecom token/name/SG region, Hub row, read-only product/order fetch and per-store refresh. Reconcile the Production Partner ID with Shopee console before using Production, then repeat with a low-risk real Production shop. Record provider evidence and any unsupported capability separately; do not claim a successful provider write.
