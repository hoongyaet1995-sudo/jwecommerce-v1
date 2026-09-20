<?php

namespace Tests\Feature;

use App\Services\Inventory\StoreManagement\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_suite_uses_only_the_in_memory_sqlite_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_shared_mode_resolves_two_shopee_stores_and_website_to_one_unknown_balance(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $first = $fixture->store('shopee', 'A');
        $second = $fixture->store('shopee', 'B');
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);

        $resolver = app(AccountResolver::class);
        $a = $resolver->balance($sku['variant']->id, 'marketplace:'.$first->id);
        $b = $resolver->balance($sku['variant']->id, 'marketplace:'.$second->id);
        $website = $resolver->balance($sku['variant']->id, 'website');

        $this->assertSame($a['id'], $b['id']);
        $this->assertSame($a['id'], $website['id']);
        $this->assertNull($a['physical']);
        $this->assertFalse($a['counted']);
        $this->assertSame('shared', $a['account_key']);
    }

    public function test_independent_mode_keeps_same_platform_stores_distinct_and_provisioning_is_idempotent(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $first = $fixture->store('shopee', 'A');
        $second = $fixture->store('shopee', 'B');
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);

        $resolver = app(AccountResolver::class);
        $website = $resolver->balance($sku['variant']->id, 'website');
        $websiteAgain = $resolver->balance($sku['variant']->id, 'website');
        $a = $resolver->balance($sku['variant']->id, 'marketplace:'.$first->id);
        $b = $resolver->balance($sku['variant']->id, 'marketplace:'.$second->id);

        $this->assertSame($website['id'], $websiteAgain['id']);
        $this->assertNotSame($website['id'], $a['id']);
        $this->assertNotSame($a['id'], $b['id']);
        $this->assertDatabaseCount('inventory_pools', 3);
        $this->assertDatabaseCount('inventory_balances', 3);
    }

    public function test_new_sku_stays_unknown_and_newly_joined_store_starts_at_explicit_zero_without_provider_import(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $resolver = app(AccountResolver::class);
        $website = $resolver->balance($sku['variant']->id, 'website');
        $this->assertNull($website['physical']);

        DB::table('inventory_balances')->where('id', $website['id'])->update([
            'physical_stock' => 12,
            'reserved_stock' => 2,
            'counted_at' => now(),
        ]);
        $store = $fixture->store('lazada', 'NEW');
        DB::table('marketplace_listing_variants')->insert([
            'store_id' => $store->id,
            'external_product_id' => 'P1',
            'external_variant_id' => 'V1',
            'observed_data' => json_encode(['stock' => 777], JSON_THROW_ON_ERROR),
            'fetched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $balance = $resolver->balance($sku['variant']->id, 'marketplace:'.$store->id);

        $this->assertSame(0, $balance['physical']);
        $this->assertSame(0, $balance['reserved']);
        $this->assertSame(0, $balance['available']);
        $this->assertTrue($balance['counted']);
    }

    public function test_channel_validation_rejects_arbitrary_ids_and_unsupported_platforms(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $resolver = app(AccountResolver::class);

        foreach (['marketplace:999999', 'marketplace:external-shop-id', 'other'] as $channel) {
            try {
                $resolver->balance($sku['variant']->id, $channel);
                $this->fail('Invalid channel was accepted: '.$channel);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('channel_key', $exception->errors());
            }
        }

        $unsupported = $fixture->store('shopify', 'FUTURE');
        $this->expectException(ValidationException::class);
        $resolver->balance($sku['variant']->id, 'marketplace:'.$unsupported->id);
    }
}
