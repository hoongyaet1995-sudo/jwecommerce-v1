# Marketplace Store Publish Capability Phase One Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow a verified Lazada production store to publish only when its own audited `product_publish` capability is enabled, while every other write capability stays disabled.

**Architecture:** Persist account mode and per-store capabilities separately from provider transport environment. A server-only access service validates store identity, token application identity, the global emergency switch, and the requested capability before a publish task is created or dispatched. The initial rollout activates only store ID `3` / Lazada seller ID `300927968304`.

**Tech Stack:** Laravel 12, PHP 8.x, MySQL, Laravel migrations, feature tests, React/TypeScript.

**Spec:** `docs/superpowers/specs/2026-09-18-marketplace-store-environment-capabilities-design.md`

## Global Constraints

- `marketplace_stores.environment` remains provider transport state; browser input never supplies an endpoint.
- `account_mode` is `test` or `production`; OAuth and provider identity determine it on the server.
- A global master switch and the matching per-store capability must both be enabled for every write.
- Store `3` receives only `catalog_read` and `product_publish` in this phase.
- `order_receive`, `inventory_update`, and `fulfillment_write` remain disabled.
- Existing Lazada seller, category, attribute, image, source-revision, warehouse, zero-stock, and uncertain-result checks remain unchanged.
- Do not expose endpoints, app keys, secrets, or tokens to the browser.
- Preserve existing Google AI Studio DOM and Tailwind classes.

---

## File Structure

- Create: `database/migrations/2026_09_18_000001_add_account_mode_and_store_capabilities.php` — account mode and one capability record per store.
- Create: `app/Services/Marketplace/MarketplaceStoreAccess.php` — central server-only capability and identity assertion.
- Create: `app/Console/Commands/ActivateMarketplaceStoreCapability.php` — audited activation command.
- Modify: `app/Services/Marketplace/BasicPublishingDestination.php` — capability-aware Lazada destination validation.
- Modify: `app/Services/Marketplace/MarketplacePublishingDispatchService.php` — recheck capability immediately before any provider write.
- Modify: `app/Services/Marketplace/LazadaAuthorizationService.php` — create production OAuth stores with read-only defaults.
- Modify: `app/Http/Controllers/Admin/MarketplaceOAuthController.php` — return verified non-secret mode/capability state.
- Modify: `resources/admin/src/integration/marketplaceOperations.ts` — expand the typed connection contract.
- Modify: `resources/admin/src/marketplace/components/marketplace/MarketplaceAuthPage.tsx` — display account mode and capability state.
- Create/Modify tests: `tests/Feature/MarketplaceStoreAccessTest.php`, `tests/Feature/BasicPublishingDestinationTest.php`, `tests/Feature/LazadaTestConnectionTest.php`, `resources/admin/src/integration/marketplaceOperations.test.ts`, and `resources/admin/src/marketplace/components/marketplace/MarketplaceAuthPage.test.tsx`.

## Task 1: Persist Store Account Mode and Capabilities

**Files:**
- Create: `database/migrations/2026_09_18_000001_add_account_mode_and_store_capabilities.php`
- Create: `tests/Feature/MarketplaceStoreAccessTest.php`

**Interfaces:**
- Produces `marketplace_stores.account_mode`.
- Produces `marketplace_store_capabilities` keyed by `store_id`.
- Produces booleans `catalog_read`, `product_publish`, `order_receive`, `inventory_update`, `fulfillment_write`, plus `changed_by` and `change_reason`.

- [ ] **Step 1: Write the failing feature test**

    public function test_capabilities_are_isolated_per_store_and_default_to_read_only(): void
    {
        $first = $this->connectedStore('300927968304');
        $second = $this->connectedStore('300927968305');

        DB::table('marketplace_store_capabilities')->where('store_id', $first)->update([
            'product_publish'=>true, 'change_reason'=>'Acceptance',
        ]);

        $this->assertTrue((bool) DB::table('marketplace_store_capabilities')->where('store_id',$first)->value('product_publish'));
        $this->assertFalse((bool) DB::table('marketplace_store_capabilities')->where('store_id',$second)->value('product_publish'));
    }

- [ ] **Step 2: Verify the test fails before the migration exists**

Run: `C:\\xampp\\php\\php.exe artisan test --compact tests/Feature/MarketplaceStoreAccessTest.php`

Expected: FAIL because `account_mode` and `marketplace_store_capabilities` do not exist.

- [ ] **Step 3: Add the migration**

    Schema::table('marketplace_stores', function (Blueprint $table) {
        $table->string('account_mode', 20)->default('production')->after('environment');
        $table->index(['platform','environment','account_mode'], 'mp_store_mode_lookup');
    });

    Schema::create('marketplace_store_capabilities', function (Blueprint $table) {
        $table->foreignId('store_id')->primary()->constrained('marketplace_stores')->cascadeOnDelete();
        $table->boolean('catalog_read')->default(true);
        $table->boolean('product_publish')->default(false);
        $table->boolean('order_receive')->default(false);
        $table->boolean('inventory_update')->default(false);
        $table->boolean('fulfillment_write')->default(false);
        $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->string('change_reason', 255)->nullable();
        $table->timestamps();
    });

The migration inserts one capability row for every existing store, with `catalog_read` true only when the store is connected and all write booleans false.

- [ ] **Step 4: Run migration and test**

Run: `C:\\xampp\\php\\php.exe artisan migrate --force; C:\\xampp\\php\\php.exe artisan test --compact tests/Feature/MarketplaceStoreAccessTest.php`

Expected: PASS. No existing connection receives a write permission by inference.

- [ ] **Step 5: Commit**

    git add database/migrations/2026_09_18_000001_add_account_mode_and_store_capabilities.php tests/Feature/MarketplaceStoreAccessTest.php
    git commit -m "Persist marketplace store capabilities"

## Task 2: Add the Central Publish Access Boundary

**Files:**
- Create: `app/Services/Marketplace/MarketplaceStoreAccess.php`
- Modify: `app/Services/Marketplace/BasicPublishingDestination.php`
- Modify: `tests/Feature/MarketplaceStoreAccessTest.php`
- Modify: `tests/Feature/BasicPublishingDestinationTest.php`

**Interfaces:**
- Produces `MarketplaceStoreAccess::publishingStore(int $storeId): object`.
- It returns only a connected store with `product_publish=true`.
- It throws `Product publishing is not enabled for this store.` with HTTP 403 for a missing or disabled capability.

- [ ] **Step 1: Write failing access tests**

    public function test_publish_requires_global_switch_and_store_capability(): void
    {
        config(['marketplace.publish_operations_enabled'=>true]);
        $store = $this->connectedStore('300927968304');

        $this->expectException(HttpException::class);
        app(MarketplaceStoreAccess::class)->publishingStore($store);
    }

    public function test_global_publish_switch_overrides_store_capability(): void
    {
        config(['marketplace.publish_operations_enabled'=>false]);
        $store = $this->enabledPublishStore('300927968304');

        $this->expectException(RuntimeException::class);
        app(MarketplaceStoreAccess::class)->publishingStore($store);
    }

- [ ] **Step 2: Verify focused tests fail**

Run: `C:\\xampp\\php\\php.exe artisan test --compact tests/Feature/MarketplaceStoreAccessTest.php tests/Feature/BasicPublishingDestinationTest.php`

Expected: FAIL because `MarketplaceStoreAccess` does not exist and the legacy Lazada gate still requires seller `300163725140`.

- [ ] **Step 3: Implement the minimal access service**

    final class MarketplaceStoreAccess
    {
        public function publishingStore(int $storeId): object
        {
            if (config('marketplace.publish_operations_enabled') !== true) {
                throw new RuntimeException('Marketplace publishing is disabled.');
            }

            $store = DB::table('marketplace_stores')->where('id', $storeId)->first();
            $capability = $store
                ? DB::table('marketplace_store_capabilities')->where('store_id', $store->id)->first()
                : null;

            abort_unless(
                $store && $store->status === 'connected' && $capability && $capability->product_publish,
                403,
                'Product publishing is not enabled for this store.'
            );

            return $store;
        }
    }

In `BasicPublishingDestination::check()`, call `publishingStore()` first. Keep existing Shopee checks. For Lazada, require `environment='live'`, `region='MY'`, configured app key matching token application ID, and either a normal `oauth` token or a valid leased `official_test` token. Remove the hard-coded seller ID as a condition for OAuth publishing.

- [ ] **Step 4: Verify provider rules still hold**

Run: `C:\\xampp\\php\\php.exe artisan test --compact tests/Feature/MarketplaceStoreAccessTest.php tests/Feature/BasicPublishingDestinationTest.php tests/Feature/LazadaListingPreflightTest.php`

Expected: PASS. An enabled OAuth store is accepted; a second unenabled store, expired official test lease, and disabled global switch are rejected.

- [ ] **Step 5: Commit**

    git add app/Services/Marketplace/MarketplaceStoreAccess.php app/Services/Marketplace/BasicPublishingDestination.php tests/Feature/MarketplaceStoreAccessTest.php tests/Feature/BasicPublishingDestinationTest.php
    git commit -m "Gate publishing by marketplace store capability"

## Task 3: Recheck Permission at Dispatch and Default New OAuth Stores to Read-only

**Files:**
- Modify: `app/Services/Marketplace/MarketplacePublishingDispatchService.php`
- Modify: `app/Services/Marketplace/LazadaAuthorizationService.php`
- Modify: `tests/Feature/BasicPublishingDestinationTest.php`
- Modify: `tests/Feature/LazadaTestConnectionTest.php`

**Interfaces:**
- Consumes `MarketplaceStoreAccess::publishingStore()`.
- A task loses eligibility if capability is disabled after task creation and before dispatch.
- New Lazada OAuth stores persist `account_mode='production'` plus a read-only capability row.

- [ ] **Step 1: Write failing regression tests**

    public function test_queued_task_is_rejected_if_publish_is_disabled_before_dispatch(): void
    {
        $task = $this->reviewedLazadaTaskWithPublishCapability();
        DB::table('marketplace_store_capabilities')->where('store_id', $task['store_id'])
            ->update(['product_publish'=>false]);

        $this->expectException(HttpException::class);
        app(MarketplacePublishingDispatchService::class)->dispatch($task['id']);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/product/create'));
    }

    public function test_lazada_oauth_store_defaults_to_production_read_only(): void
    {
        $storeId = app(LazadaAuthorizationService::class)->complete($state, $admin->id, $code);

        $this->assertSame('production', DB::table('marketplace_stores')->where('id',$storeId)->value('account_mode'));
        $this->assertSame(0, DB::table('marketplace_store_capabilities')->where('store_id',$storeId)->value('product_publish'));
    }

- [ ] **Step 2: Verify tests fail**

Run: `C:\\xampp\\php\\php.exe artisan test --compact tests/Feature/BasicPublishingDestinationTest.php tests/Feature/LazadaTestConnectionTest.php`

Expected: FAIL because dispatch has no second capability assertion and OAuth completion creates no capability record.

- [ ] **Step 3: Implement both checks**

After loading the connected store in `MarketplacePublishingDispatchService::dispatch()`, before source/image/warehouse/provider work:

    app(MarketplaceStoreAccess::class)->publishingStore((int) $task->store_id);

Within `LazadaAuthorizationService::complete()`, insert capabilities only for a new store:

    DB::table('marketplace_store_capabilities')->insert([
        'store_id'=>$id, 'catalog_read'=>true, 'product_publish'=>false,
        'order_receive'=>false, 'inventory_update'=>false, 'fulfillment_write'=>false,
        'created_at'=>now(), 'updated_at'=>now(),
    ]);

Reconnects retain the existing row and account mode.

- [ ] **Step 4: Verify regression protection**

Run: `C:\\xampp\\php\\php.exe artisan test --compact tests/Feature/BasicPublishingDestinationTest.php tests/Feature/LazadaTestConnectionTest.php tests/Feature/LazadaListingPreflightTest.php`

Expected: PASS and the revoked task makes no `/product/create` request.

- [ ] **Step 5: Commit**

    git add app/Services/Marketplace/MarketplacePublishingDispatchService.php app/Services/Marketplace/LazadaAuthorizationService.php tests/Feature/BasicPublishingDestinationTest.php tests/Feature/LazadaTestConnectionTest.php
    git commit -m "Recheck store publish permission before dispatch"

## Task 4: Add Audited Capability Activation

**Files:**
- Create: `app/Console/Commands/ActivateMarketplaceStoreCapability.php`
- Modify: `routes/console.php`
- Modify: `tests/Feature/MarketplaceStoreAccessTest.php`

**Interfaces:**
- Produces `marketplace:store-capability {store} {capability} {--actor=} {--reason=}`.
- Accepted capabilities: `product_publish`, `order_receive`, `inventory_update`, `fulfillment_write`.
- Does not change global switches, endpoints, credentials, classifications, or any capability other than the requested one.

- [ ] **Step 1: Write failing command test**

    public function test_capability_activation_requires_admin_and_reason(): void
    {
        $store = $this->connectedStore('300927968304');

        $this->artisan('marketplace:store-capability', [
            'store'=>$store, 'capability'=>'product_publish', '--actor'=>999, '--reason'=>'',
        ])->assertExitCode(1);

        $this->assertSame(0, DB::table('marketplace_store_capabilities')->where('store_id',$store)->value('product_publish'));
    }

- [ ] **Step 2: Verify it fails**

Run: `C:\\xampp\\php\\php.exe artisan test --compact tests/Feature/MarketplaceStoreAccessTest.php`

Expected: FAIL because the command is unavailable.

- [ ] **Step 3: Implement audited activation**

    $valid = ['product_publish','order_receive','inventory_update','fulfillment_write'];

    if (!in_array($capability, $valid, true) || !$actor || !$actor->is_admin
        || trim($reason) === '' || mb_strlen($reason) > 255) {
        $this->error('An administrator, supported capability, and reason are required.');
        return self::FAILURE;
    }

    DB::transaction(function () use ($storeId, $capability, $actor, $reason): void {
        $store = DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
        abort_unless($store && $store->status === 'connected', 404, 'Connected store not found.');

        DB::table('marketplace_store_capabilities')->where('store_id',$storeId)->update([
            $capability=>true, 'changed_by'=>$actor->id,
            'change_reason'=>trim($reason), 'updated_at'=>now(),
        ]);
    });

- [ ] **Step 4: Verify audit behaviour**

Run: `C:\\xampp\\php\\php.exe artisan test --compact tests/Feature/MarketplaceStoreAccessTest.php`

Expected: PASS. One enabled capability records actor and reason; the remaining write capabilities remain false.

- [ ] **Step 5: Commit**

    git add app/Console/Commands/ActivateMarketplaceStoreCapability.php routes/console.php tests/Feature/MarketplaceStoreAccessTest.php
    git commit -m "Add audited marketplace capability activation"

## Task 5: Show Verified Mode and Capability State

**Files:**
- Modify: `app/Http/Controllers/Admin/MarketplaceOAuthController.php`
- Modify: `resources/admin/src/integration/marketplaceOperations.ts`
- Modify: `resources/admin/src/marketplace/components/marketplace/MarketplaceAuthPage.tsx`
- Modify: `resources/admin/src/integration/marketplaceOperations.test.ts`
- Modify: `resources/admin/src/marketplace/components/marketplace/MarketplaceAuthPage.test.tsx`

**Interfaces:**
- The connection response includes `environment`, `accountMode`, and:
  `{catalogRead, productPublish, orderReceive, inventoryUpdate, fulfillmentWrite}`.
- The response never includes an access token, refresh token, endpoint, app key, or secret.

- [ ] **Step 1: Write failing contract and UI tests**

    expect(connection).toMatchObject({
      environment: 'live',
      accountMode: 'production',
      capabilities: {
        catalogRead: true, productPublish: true, orderReceive: false,
        inventoryUpdate: false, fulfillmentWrite: false,
      },
    });
    expect(JSON.stringify(connection)).not.toContain('access_token');

    await act(async () => root.render(<MarketplaceAuthPage operational />));
    expect(container.textContent).toContain('Production Store');
    expect(container.textContent).toContain('Product publishing enabled');
    expect(container.textContent).toContain('Inventory update disabled');

- [ ] **Step 2: Verify frontend tests fail**

Run: `npm test -- --run src/integration/marketplaceOperations.test.ts src/marketplace/components/marketplace/MarketplaceAuthPage.test.tsx`

Expected: FAIL because connection types and card data lack account mode and per-store capabilities.

- [ ] **Step 3: Implement response mapping and existing-style display**

Add to the controller connection mapping:

    'environment'=>$store->environment,
    'accountMode'=>$store->account_mode,
    'capabilities'=>[
        'catalogRead'=>(bool) $capability->catalog_read,
        'productPublish'=>(bool) $capability->product_publish,
        'orderReceive'=>(bool) $capability->order_receive,
        'inventoryUpdate'=>(bool) $capability->inventory_update,
        'fulfillmentWrite'=>(bool) $capability->fulfillment_write,
    ],

Extend `StoreConnection` with the same fields. Render `Production Store` or `Lazada Test Account` and read-only enabled/disabled status chips using existing neutral and emerald card classes. Do not add browser-side activation controls in this phase.

- [ ] **Step 4: Verify tests and build**

Run: `npm test -- --run src/integration/marketplaceOperations.test.ts src/marketplace/components/marketplace/MarketplaceAuthPage.test.tsx; npm run build`

Expected: PASS. TypeScript compiles and the UI cannot access credentials.

- [ ] **Step 5: Commit**

    git add app/Http/Controllers/Admin/MarketplaceOAuthController.php resources/admin/src/integration/marketplaceOperations.ts resources/admin/src/marketplace/components/marketplace/MarketplaceAuthPage.tsx resources/admin/src/integration/marketplaceOperations.test.ts resources/admin/src/marketplace/components/marketplace/MarketplaceAuthPage.test.tsx
    git commit -m "Show marketplace store capability state"

## Task 6: Activate Store 3 and Perform Acceptance

**Files:**
- Modify: local and Hostinger `.env` only if the existing global Lazada/publish flags are not enabled; never commit it.
- Test: the feature tests from Tasks 1–5.

**Interfaces:**
- Uses `marketplace:store-capability`.
- Requires `MARKETPLACE_LAZADA_ENABLED=true` and `MARKETPLACE_PUBLISH_OPERATIONS_ENABLED=true`.

- [ ] **Step 1: Run all backend and frontend tests**

Run: `C:\\xampp\\php\\php.exe artisan test --compact tests/Feature/MarketplaceStoreAccessTest.php tests/Feature/BasicPublishingDestinationTest.php tests/Feature/LazadaListingPreflightTest.php tests/Feature/MarketplaceListingMetadataTest.php; npm test -- --run src/integration/marketplaceOperations.test.ts src/marketplace/components/marketplace/MarketplaceAuthPage.test.tsx`

Expected: PASS with order, inventory, and fulfilment capabilities disabled.

- [ ] **Step 2: Push the five reviewed task commits and deploy their exact HEAD SHA**

    git status --short
    git push origin main
    ssh jw-hostinger "cd /home/u762348670/apps/jwecommerce && git pull --ff-only origin main && /usr/bin/php artisan migrate --force && /usr/bin/php artisan optimize:clear && git rev-parse HEAD"

Expected: Hostinger returns the pushed SHA.

- [ ] **Step 3: Identify an administrator without inspecting credentials**

Run: `ssh jw-hostinger "cd /home/u762348670/apps/jwecommerce && /usr/bin/php artisan tinker --execute='echo json_encode(App\\Models\\User::where(\\\"is_admin\\\",true)->get([\\\"id\\\",\\\"name\\\"]));'"`

Expected: administrator IDs/names only.

- [ ] **Step 4: Activate only product publishing for store 3**

Run: `ssh jw-hostinger "cd /home/u762348670/apps/jwecommerce && /usr/bin/php artisan marketplace:store-capability 3 product_publish --actor=2 --reason='Lazada product publishing acceptance for seller 300927968304'"`

Expected: store 3 receives only `product_publish=true`.

- [ ] **Step 5: Confirm persisted scope before browser acceptance**

Run: `ssh jw-hostinger "cd /home/u762348670/apps/jwecommerce && /usr/bin/php artisan tinker --execute='echo json_encode(Illuminate\\Support\\Facades\\DB::table(\\\"marketplace_store_capabilities\\\")->where(\\\"store_id\\\",3)->first());'"`

Expected: `catalog_read=true`, `product_publish=true`, `order_receive=false`, `inventory_update=false`, `fulfillment_write=false`.

- [ ] **Step 6: Browser acceptance**

1. Open Marketplace → Connect Store and verify Lazada MY `300927968304` displays `Production Store` and product publishing enabled.
2. Open Product & SKU Mapping, choose the same store and `MUSE CASUAL COLLECTION`.
3. Complete current category/attribute review and submit one zero-stock listing.
4. Verify the confirmed item ID in Lazada Seller Center.
5. Verify Order Receive, Inventory Update, and Fulfillment Write remain disabled.

Do not commit `.env`, credentials, signed provider URLs, or screenshots containing credentials. A later evidence commit must name its concrete file before staging it.

## Later Plans Required

- Order receive requires a separate plan that applies `order_receive` at manual and automatic pull boundaries.
- Inventory update requires a separate plan that applies `inventory_update` at task creation, dispatch, recheck, and scheduler boundaries.
- Fulfilment requires a separate plan that applies `fulfillment_write` at package, RTS, AWB, and cancellation boundaries.
- Connect Store environment-selection UI requires a separate source-faithful UI plan; authorization intent must be validated against the returned provider identity.
