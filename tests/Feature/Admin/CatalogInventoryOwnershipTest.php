<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogInventoryOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_nonzero_catalog_stock_creates_one_unverified_opening_balance(): void
    {
        $this->enableSharedInventory();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $this->payload(20))
            ->assertCreated()
            ->assertJsonPath('data.variants.0.inventoryQuantity', 20)
            ->assertJsonPath('data.variants.0.inventoryInitialized', true)
            ->assertJsonPath('data.variants.0.inventoryVerified', false)
            ->assertJsonPath('data.variants.0.inventoryEditable', true);

        $variantId = (int) $response->json('data.variants.0.id');
        $this->assertDatabaseHas('inventory_balances', [
            'product_variant_id' => $variantId,
            'physical_stock' => 20,
            'reserved_stock' => 0,
            'counted_at' => null,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variantId,
            'first_physical_verified_at' => null,
        ]);
        $this->assertDatabaseCount('catalog_stock_operations', 1);
    }

    public function test_edit_before_first_count_updates_the_same_physical_balance(): void
    {
        $this->enableSharedInventory();
        $admin = User::factory()->create(['is_admin' => true]);
        $created = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $this->payload(20))
            ->assertCreated()->json('data');
        $payload = $this->payload(15, $created['variants'][0]);

        $updated = $this->patchJson('/admin/api/catalog/products/'.$created['id'], $payload)
            ->assertOk()->json('data');

        $variantId = (int) $created['variants'][0]['id'];
        $this->assertSame(15, $updated['variants'][0]['inventoryQuantity']);
        $this->assertDatabaseHas('inventory_balances', ['product_variant_id' => $variantId, 'physical_stock' => 15]);
        $this->assertDatabaseCount('catalog_stock_operations', 2);
    }

    public function test_metadata_only_save_omits_quantity_and_does_not_create_stock_movement(): void
    {
        $this->enableSharedInventory();
        $admin = User::factory()->create(['is_admin' => true]);
        $created = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $this->payload(0))
            ->assertCreated()->json('data');
        $payload = $this->payload(0, $created['variants'][0]);
        unset($payload['variants'][0]['inventoryQuantity'], $payload['variants'][0]['inventoryRevision']);
        $payload['title'] = 'Metadata changed';

        $this->patchJson('/admin/api/catalog/products/'.$created['id'], $payload)
            ->assertOk()->assertJsonPath('data.title', 'Metadata changed');

        $this->assertDatabaseCount('catalog_stock_operations', 1);
    }

    public function test_verified_sku_rejects_a_forged_catalog_quantity_change(): void
    {
        $this->enableSharedInventory();
        $admin = User::factory()->create(['is_admin' => true]);
        $created = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $this->payload(8))
            ->assertCreated()->json('data');
        DB::table('product_variants')->where('id', $created['variants'][0]['id'])->update([
            'first_physical_verified_at' => now(),
            'first_physical_verification_operation' => 'verified-test',
        ]);
        $payload = $this->payload(5, $created['variants'][0]);

        $this->patchJson('/admin/api/catalog/products/'.$created['id'], $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('variants.0.inventoryQuantity');

        $this->assertDatabaseHas('inventory_balances', [
            'product_variant_id' => $created['variants'][0]['id'],
            'physical_stock' => 8,
        ]);
    }

    public function test_catalog_quantity_cannot_drop_below_reserved_stock(): void
    {
        $this->enableSharedInventory();
        $admin = User::factory()->create(['is_admin' => true]);
        $created = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $this->payload(8))
            ->assertCreated()->json('data');
        DB::table('inventory_balances')->where('product_variant_id', $created['variants'][0]['id'])
            ->update(['reserved_stock' => 4, 'version' => DB::raw('version + 1')]);
        $payload = $this->payload(3, $created['variants'][0]);
        $payload['variants'][0]['inventoryRevision'] = app(\App\Services\Inventory\CatalogInventoryState::class)
            ->snapshot((int) $created['variants'][0]['id'])['revision'];

        $this->patchJson('/admin/api/catalog/products/'.$created['id'], $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('variants.0.inventoryQuantity');
    }

    private function enableSharedInventory(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update([
            'operating_mode' => 'shared_pool',
            'accounting_enabled' => true,
        ]);
    }

    /** @param array<string,mixed>|null $savedVariant */
    private function payload(int $quantity, ?array $savedVariant = null): array
    {
        $variant = [
            'id' => $savedVariant['id'] ?? 'new-variant',
            'sku' => $savedVariant['sku'] ?? 'OWNERSHIP-001',
            'title' => 'Default',
            'options' => [],
            'price' => 100,
            'compareAtPrice' => null,
            'costPrice' => null,
            'inventoryQuantity' => $quantity,
            'inventoryRevision' => $savedVariant['inventoryRevision'] ?? null,
            'weightGrams' => null,
            'packageLengthCm' => null,
            'packageWidthCm' => null,
            'packageHeightCm' => null,
            'barcode' => null,
            'image' => null,
            'visualType' => 'none',
            'colorCode' => null,
            'optionImage' => null,
            'enabled' => true,
        ];

        return [
            'title' => 'Ownership Product',
            'slug' => 'ownership-product',
            'brand' => '',
            'categoryId' => null,
            'collectionIds' => [],
            'summary' => '',
            'descriptionHtml' => '',
            'images' => [],
            'status' => 'active',
            'hasVariants' => false,
            'basePrice' => 100,
            'baseCompareAtPrice' => null,
            'options' => [],
            'tags' => [],
            'featured' => false,
            'variants' => [$variant],
            'seoTitle' => null,
            'seoDescription' => null,
        ];
    }
}
