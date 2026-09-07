<?php

namespace App\Services\Inventory;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Unallocated availability only; never infer physical inventory from this ledger. */
class InventoryMutationService
{
    public function change(int $variantId, int $delta, string $operation, string $reason, ?int $actorId = null): int
    {
        return $this->apply($variantId, $delta, null, $operation, $reason, $actorId);
    }

    public function setAvailable(int $variantId, int $quantity, int $expectedVersion, string $operation, string $reason, ?int $actorId = null): int
    {
        if ($quantity < 0 || $expectedVersion < 0) throw new RuntimeException('Invalid inventory adjustment.');
        return $this->apply($variantId, $quantity, $expectedVersion, $operation, $reason, $actorId);
    }

    private function apply(int $variantId, int $amount, ?int $expectedVersion, string $operation, string $reason, ?int $actorId): int
    {
        if ($variantId < 1 || abs($amount) > 2147483647 || !preg_match('/\A[A-Za-z0-9:_-]{1,128}\z/D', $operation)
            || trim($reason) === '' || strlen($reason) > 255 || ($actorId !== null && $actorId < 1)) {
            throw new RuntimeException('Invalid inventory adjustment.');
        }
        $hash = hash('sha256', json_encode([$amount,$expectedVersion,$reason,$actorId], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($variantId,$amount,$expectedVersion,$operation,$reason,$actorId,$hash) {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId);
            $previous = DB::table('inventory_movements')->where('product_variant_id',$variantId)->where('operation_key',$operation)->first();
            if ($previous) {
                if (!hash_equals($previous->request_hash,$hash)) throw new RuntimeException('Inventory operation identity conflicts with an earlier change.');
                return (int)$previous->available_after;
            }
            if ($expectedVersion !== null && (int)$variant->stock_version !== $expectedVersion) {
                throw new RuntimeException('Inventory changed. Reload before adjusting quantity.');
            }
            $after = $expectedVersion === null ? (int)$variant->stock + $amount : $amount;
            if ($after < 0 || $after > 2147483647) throw new RuntimeException('Requested quantity is unavailable.');
            $delta = $after - (int)$variant->stock;
            $version = (int)$variant->stock_version + 1;
            DB::table('product_variants')->where('id',$variantId)->update(['stock'=>$after,'stock_version'=>$version,'updated_at'=>now()]);
            DB::table('inventory_movements')->insert(['product_variant_id'=>$variantId,'operation_key'=>$operation,'request_hash'=>$hash,
                'available_delta'=>$delta,'available_after'=>$after,'stock_version'=>$version,'actor_id'=>$actorId,'reason'=>$reason,'created_at'=>now()]);
            return $after;
        }, 3);
    }
}
