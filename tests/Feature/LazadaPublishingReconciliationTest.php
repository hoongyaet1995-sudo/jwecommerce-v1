<?php

namespace Tests\Feature;

use App\Models\MarketplaceStoreToken;
use App\Models\Product;
use App\Models\User;
use App\Services\Marketplace\LazadaPublishingReconciliation;
use App\Services\Marketplace\PublishingTasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LazadaPublishingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_reconciliation_rejects_an_uncertain_task_only_after_the_complete_catalog_has_no_requested_sku(): void
    {
        [$store, $task] = $this->uncertainTask('MUSE-BLACK-S');
        Http::preventStrayRequests();
        Http::fake(['*/products/get*' => Http::response(['code' => '0', 'data' => ['total_products' => 0, 'products' => []]])]);

        $result = app(LazadaPublishingReconciliation::class)->reconcile($task);

        $this->assertSame(['id' => $task, 'status' => 'rejected', 'confirmed' => false], $result);
        $this->assertDatabaseHas('marketplace_publishing_tasks', ['id' => $task, 'status' => 'rejected', 'external_item_id' => null]);
        Http::assertSentCount(1);
    }

    public function test_catalog_reconciliation_confirms_a_complete_lazada_listing_and_maps_the_reviewed_sku(): void
    {
        [$store, $task, $variant] = $this->uncertainTask('MUSE-BLACK-S', true);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/products/get')) {
                return Http::response(['code' => '0', 'data' => ['total_products' => 1, 'products' => [['item_id' => '149900001']]]]);
            }
            if (str_contains($request->url(), '/product/item/get')) {
                return Http::response(['code' => '0', 'data' => ['item_id' => '149900001', 'attributes' => ['name' => 'MUSE'],
                    'skus' => [['SkuId' => '128900001', 'SellerSku' => 'MUSE-BLACK-S', 'quantity' => 0, 'price' => '199.00']]]]);
            }
            return Http::response(['code' => 'denied'], 400);
        });

        $result = app(LazadaPublishingReconciliation::class)->reconcile($task);

        $this->assertSame(['id' => $task, 'status' => 'succeeded', 'confirmed' => true, 'external_item_id' => '149900001'], $result);
        $this->assertDatabaseHas('marketplace_publishing_tasks', ['id' => $task, 'status' => 'succeeded', 'external_item_id' => '149900001']);
        $this->assertDatabaseHas('marketplace_variant_mappings', ['store_id' => $store, 'product_variant_id' => $variant]);
    }

    public function test_catalog_reconciliation_uses_complete_page_skus_without_one_detail_request_per_product(): void
    {
        [$store, $task] = $this->uncertainTask('BKJS-PINK');
        Http::preventStrayRequests();
        Http::fake(['*/products/get*' => Http::response(['code' => '0', 'data' => [
            'total_products' => 1,
            'products' => [[
                'item_id' => '149900002',
                'skus' => [['SkuId' => '128900002', 'SellerSku' => 'BKJS-PINK']],
            ]],
        ]])]);

        $result = app(LazadaPublishingReconciliation::class)->reconcile($task);

        $this->assertSame('succeeded',$result['status']);
        $this->assertSame('149900002',$result['external_item_id']);
        Http::assertSentCount(1);
    }

    private function uncertainTask(string $sku, bool $withSource = false): array
    {
        config(['marketplace.read_operations_enabled' => true, 'marketplace.lazada.enabled' => true,
            'marketplace.lazada.app_key' => '123', 'marketplace.lazada.app_secret' => 'fixture-secret']);
        $actor = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['title' => 'MUSE', 'handle' => 'muse-' . strtolower($sku), 'base_price' => 19900]);
        $variant = $product->variants()->create(['title' => 'Black S', 'sku' => $sku, 'price' => 19900, 'stock' => 0, 'enabled' => true]);
        $store = DB::table('marketplace_stores')->insertGetId(['platform' => 'lazada', 'shop_name' => 'Lazada MY',
            'external_shop_id' => '300927968304', 'region' => 'MY', 'environment' => 'live', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now()]);
        MarketplaceStoreToken::create(['store_id' => $store, 'application_id' => '123', 'access_token' => 'fixture-token',
            'access_expires_at' => now()->addHour()]);
        $payload = ['Request' => ['Product' => ['PrimaryCategory' => '1740', 'Images' => ['Image' => ['https://my-live-02.slatic.net/p/test.jpg']],
            'Attributes' => ['name' => 'MUSE'], 'Skus' => ['Sku' => [['SellerSku' => $sku, 'quantity' => '0', 'price' => '199.00']]]]]];
        $source = $withSource ? app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read($product->id) : null;
        $task = app(PublishingTasks::class)->create($store, $product->id, $actor->id, 'f285db80-1111-4111-8111-123456789012', $payload, $source);
        DB::table('marketplace_publishing_tasks')->where('id', $task)->update(['status' => 'uncertain']);
        return $withSource ? [$store, $task, $variant->id] : [$store, $task];
    }
}
