<?php

namespace Tests\Support;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

final class StoreInventoryFixture
{
    public function store(string $platform, string $suffix): object
    {
        $id = DB::table('marketplace_stores')->insertGetId([
            'platform' => $platform,
            'shop_name' => $platform.' '.$suffix,
            'external_shop_id' => strtoupper($platform).'-'.$suffix,
            'region' => 'MY',
            'environment' => 'test',
            'status' => 'connected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('marketplace_stores')->find($id);
    }

    /** @return array{product: Product, variant: object, stores: array<string, object>} */
    public function sku(array $balances = []): array
    {
        $token = strtolower(bin2hex(random_bytes(4)));
        $product = Product::create([
            'title' => 'Store inventory '.$token,
            'handle' => 'store-inventory-'.$token,
            'base_price' => 100,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'STORE-'.$token,
            'title' => 'Default',
            'price' => 100,
            'stock' => 999,
        ]);

        return ['product' => $product, 'variant' => $variant, 'stores' => [], 'balances' => $balances];
    }
}
