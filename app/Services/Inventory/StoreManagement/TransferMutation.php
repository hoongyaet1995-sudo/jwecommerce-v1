<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransferMutation
{
    public function preview(object $source, object $destination, array $input): array
    {
        if ((int) $source->id === (int) $destination->id) {
            throw ValidationException::withMessages(['destination_balance_id' => 'Choose a different destination store.']);
        }
        if ((int) $source->product_variant_id !== (int) $destination->product_variant_id) {
            throw ValidationException::withMessages(['destination_balance_id' => 'Both store accounts must belong to the same SKU.']);
        }
        $quantity = (int) ($input['quantity'] ?? 0);
        $sourceState = $this->state($source);
        $destinationState = $this->state($destination);
        if ($sourceState['physical'] === null || $destinationState['physical'] === null) {
            throw ValidationException::withMessages(['quantity' => 'Complete the initial physical count for both stores first.']);
        }
        if ($quantity < 1 || $quantity > $sourceState['available']) {
            throw ValidationException::withMessages(['quantity' => 'Transfer quantity cannot exceed source available stock. Reserved stock cannot be transferred.']);
        }

        return ['before' => ['source' => $sourceState, 'destination' => $destinationState], 'after' => [
            'source' => $this->changed($sourceState, -$quantity),
            'destination' => $this->changed($destinationState, $quantity),
        ]];
    }

    public function apply(object $source, object $destination, array $after): array
    {
        return [
            $this->save($source, $after['source']),
            $this->save($destination, $after['destination']),
        ];
    }

    private function state(object $balance): array
    {
        $physical = $balance->physical_stock === null ? null : (int) $balance->physical_stock;
        $reserved = $balance->reserved_stock === null ? 0 : (int) $balance->reserved_stock;
        $safety = $balance->safety_stock === null ? 0 : (int) $balance->safety_stock;

        return ['physical' => $physical, 'reserved' => $reserved, 'safety' => $safety,
            'available' => $physical === null ? null : max(0, $physical - $reserved - $safety)];
    }

    private function changed(array $state, int $delta): array
    {
        $state['physical'] += $delta;
        $state['available'] = max(0, $state['physical'] - $state['reserved'] - $state['safety']);
        return $state;
    }

    private function save(object $balance, array $state): array
    {
        $version = (int) $balance->version + 1;
        DB::table('inventory_balances')->where('id', $balance->id)->update([
            'physical_stock' => $state['physical'], 'reserved_stock' => $state['reserved'],
            'safety_stock' => $state['safety'], 'version' => $version, 'updated_at' => now(),
        ]);
        return ['id' => (int) $balance->id, 'variant_id' => (int) $balance->product_variant_id,
            ...$state, 'counted' => true, 'version' => $version];
    }
}
