<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RebaselineFreeze
{
    public function __construct(private readonly RebaselineReadiness $readiness) {}

    public function start(User $actor, string $operationKey): array
    {
        abort_unless($actor->is_admin && $actor->is_super_admin, 403);
        abort_unless(config('marketplace.rebaseline_cutover_enabled') === true, 404);
        if (! preg_match('/\A[A-Za-z0-9:_-]{1,120}\z/D', $operationKey)) {
            throw ValidationException::withMessages(['operation_key' => 'Use a valid operation key.']);
        }

        return DB::transaction(function () use ($actor, $operationKey): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            $existing = DB::table('inventory_rebaseline_epochs')->where('operation_key', $operationKey)->first();
            if ($existing) {
                if ((int) $existing->actor_id !== (int) $actor->id) {
                    throw ValidationException::withMessages(['operation_key' => 'This operation key belongs to another actor.']);
                }

                return ['epoch_id' => $existing->id, 'status' => $existing->status];
            }
            if (DB::table('inventory_rebaseline_epochs')->whereIn('status', ['frozen', 'zeroing', 'reviewed', 'counting'])->exists()) {
                throw ValidationException::withMessages(['rebaseline' => 'A rebaseline is already in progress.']);
            }
            $preflight = $this->readiness->inspect();
            if (! $preflight['ready'] || (int) $configuration->version !== $preflight['configuration_version']) {
                throw ValidationException::withMessages(['rebaseline' => array_column($preflight['blockers'], 'code')]);
            }
            $id = (string) Str::uuid();
            DB::table('inventory_rebaseline_epochs')->insert([
                'id' => $id, 'status' => 'frozen', 'operation_key' => $operationKey,
                'configuration_version' => (int) $configuration->version,
                'actor_id' => $actor->id,
                'review_snapshot' => json_encode($preflight, JSON_THROW_ON_ERROR),
                'cutoff_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($preflight['stores'] as $store) {
                $storeId = (int) $store['store_id'];
                $scan = DB::table('marketplace_store_facility_scans')
                    ->where('marketplace_store_id', $storeId)->lockForUpdate()->first();
                if (! $scan || (int) $scan->revision !== (int) $store['topology_revision']
                    || $scan->status !== 'verified') {
                    throw ValidationException::withMessages(['rebaseline' => 'Provider facility topology changed during freeze.']);
                }
                $facilities = $scan->topology === 'no_warehouse' ? [null]
                    : DB::table('marketplace_store_facilities')->where('marketplace_store_id', $storeId)
                        ->where('is_active', true)->orderBy('provider_facility_id')->get()->all();
                $mappings = DB::table('marketplace_variant_mappings')->where('store_id', $storeId)
                    ->orderBy('id')->lockForUpdate()->get();
                foreach ($mappings as $mapping) {
                    foreach ($facilities as $facility) {
                        DB::table('inventory_rebaseline_destinations')->insert([
                            'epoch_id' => $id, 'store_id' => $storeId,
                            'variant_id' => $mapping->product_variant_id,
                            'listing_variant_id' => $mapping->listing_variant_id,
                            'provider_facility_id' => $facility?->id,
                            'route_key' => $facility?->provider_facility_id ?? 'no_warehouse',
                            'zero_status' => 'pending', 'reopen_status' => 'paused',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }

            return ['epoch_id' => $id, 'status' => 'frozen'];
        }, 3);
    }

    public function assertNewReservationAllowed(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
        if ($this->isPaused()) {
            throw ValidationException::withMessages(['inventory' => 'Sales are paused for inventory rebaseline.']);
        }
    }

    public function assertOrdinaryPushAllowed(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
        if ($this->isPaused()) {
            throw ValidationException::withMessages(['inventory' => 'Stock synchronization is paused for inventory rebaseline.']);
        }
    }

    public function isPaused(): bool
    {
        if (! Schema::hasTable('inventory_rebaseline_epochs')) {
            return false;
        }

        return DB::table('inventory_rebaseline_epochs')
            ->whereIn('status', ['frozen', 'zeroing', 'reviewed', 'counting'])->exists();
    }
}
