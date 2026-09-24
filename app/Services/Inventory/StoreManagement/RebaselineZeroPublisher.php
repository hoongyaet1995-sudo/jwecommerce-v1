<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

/** A frozen epoch's one-shot zero write; uncertainty is reconciled read-only, never blindly retried. */
final class RebaselineZeroPublisher
{
    public function __construct(private readonly MarketplaceStoreInventoryPublisher $publisher) {}

    public function check(string $epochId, int $destinationId): array
    {
        $row = DB::table('inventory_rebaseline_destinations')->where('id', $destinationId)
            ->where('epoch_id', $epochId)->first();
        abort_unless($row, 404);
        if ($row->zero_status !== 'uncertain') {
            return $this->receipt($epochId, $destinationId);
        }
        $epoch = DB::table('inventory_rebaseline_epochs')->find($epochId);
        $configuration = DB::table('inventory_configuration')->where('id', 1)->first();
        $snapshot = $epoch ? json_decode($epoch->review_snapshot ?? '{}', true) ?: [] : [];
        $storeSnapshot = collect($snapshot['stores'] ?? [])->firstWhere('store_id', (int) $row->store_id);
        $scan = DB::table('marketplace_store_facility_scans')
            ->where('marketplace_store_id', $row->store_id)->first();
        $store = DB::table('marketplace_stores')->find($row->store_id);
        $listing = DB::table('marketplace_listing_variants')->find($row->listing_variant_id);
        $facility = $row->provider_facility_id === null ? null
            : DB::table('marketplace_store_facilities')->find($row->provider_facility_id);
        $mapped = DB::table('marketplace_variant_mappings')
            ->where('store_id', $row->store_id)->where('listing_variant_id', $row->listing_variant_id)
            ->where('product_variant_id', $row->variant_id)->exists();
        if (! $epoch || $epoch->status !== 'frozen' || ! $configuration
            || (int) $configuration->version !== (int) $epoch->configuration_version
            || ! $storeSnapshot || ! $scan || $scan->status !== 'verified'
            || (int) $scan->revision !== (int) $storeSnapshot['topology_revision']
            || ! $store || $store->status !== 'connected'
            || ! $listing || ! $mapped || ($facility && (! $facility->is_active
                || $facility->provider_facility_id !== $row->route_key))) {
            return $this->receipt($epochId, $destinationId);
        }
        $listing->sync_provider_location_id = $facility?->provider_facility_id;
        $listing->sync_strict_facility = $facility !== null;
        try {
            $observation = $this->publisher->observe($store, $listing);
        } catch (\Throwable) {
            return $this->receipt($epochId, $destinationId);
        }
        if (($observation['observed_quantity'] ?? null) === 0
            && ($facility === null || ($observation['provider_location_id'] ?? null) === $facility->provider_facility_id)) {
            DB::table('inventory_rebaseline_destinations')->where('id', $destinationId)
                ->where('epoch_id', $epochId)->where('zero_status', 'uncertain')->update([
                    'zero_status' => 'zero_confirmed',
                    'zero_receipt' => json_encode($observation + ['confirmation_method' => 'provider_readback'], JSON_THROW_ON_ERROR),
                    'zero_confirmed_at' => now(), 'updated_at' => now(),
                ]);
        }

        return $this->receipt($epochId, $destinationId);
    }

    public function sendAndVerify(string $epochId, int $destinationId): array
    {
        $claim = DB::transaction(function () use ($epochId, $destinationId): ?array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            $epoch = DB::table('inventory_rebaseline_epochs')->where('id', $epochId)->lockForUpdate()->first();
            $destination = DB::table('inventory_rebaseline_destinations')->where('id', $destinationId)
                ->where('epoch_id', $epochId)->lockForUpdate()->first();
            abort_unless($epoch && $destination, 404);
            if ($epoch->status !== 'frozen' || (int) $epoch->configuration_version !== (int) $configuration->version) {
                return null;
            }
            if (! in_array($destination->zero_status, ['pending', 'failed_preflight'], true)) {
                return null;
            }
            $snapshot = json_decode($epoch->review_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $storeSnapshot = collect($snapshot['stores'] ?? [])->firstWhere('store_id', (int) $destination->store_id);
            $scan = DB::table('marketplace_store_facility_scans')
                ->where('marketplace_store_id', $destination->store_id)->first();
            $store = DB::table('marketplace_stores')->find($destination->store_id);
            $listing = DB::table('marketplace_listing_variants')->find($destination->listing_variant_id);
            $mapped = DB::table('marketplace_variant_mappings')
                ->where('store_id', $destination->store_id)
                ->where('listing_variant_id', $destination->listing_variant_id)
                ->where('product_variant_id', $destination->variant_id)->exists();
            $facility = $destination->provider_facility_id === null ? null
                : DB::table('marketplace_store_facilities')->find($destination->provider_facility_id);
            if (! $storeSnapshot || ! $scan || $scan->status !== 'verified'
                || (int) $scan->revision !== (int) $storeSnapshot['topology_revision']
                || ! $store || $store->status !== 'connected' || ! $listing || ! $mapped
                || ($facility && (! $facility->is_active
                    || (int) $facility->marketplace_store_id !== (int) $destination->store_id
                    || $facility->provider_facility_id !== $destination->route_key))) {
                DB::table('inventory_rebaseline_destinations')->where('id', $destinationId)->update([
                    'zero_status' => 'failed_preflight', 'zero_receipt' => json_encode(['code' => 'destination_identity_changed']),
                    'updated_at' => now(),
                ]);

                return null;
            }
            $listing->sync_provider_location_id = $facility?->provider_facility_id;
            $listing->sync_strict_facility = $facility !== null;
            DB::table('inventory_rebaseline_destinations')->where('id', $destinationId)->update([
                'zero_status' => 'processing', 'updated_at' => now(),
            ]);

            return ['store' => $store, 'listing' => $listing,
                'expected_location' => $facility?->provider_facility_id];
        }, 3);

        if ($claim === null) {
            return $this->receipt($epochId, $destinationId);
        }

        $writeStarted = false;
        try {
            $before = $this->publisher->observe($claim['store'], $claim['listing']);
            if (! is_int($before['observed_quantity'] ?? null)
                || ($claim['expected_location'] !== null
                    && ($before['provider_location_id'] ?? null) !== $claim['expected_location'])) {
                throw new \RuntimeException('Exact provider stock context was not confirmed before write.');
            }
            $expectedLocation = $claim['expected_location'] ?? ($before['provider_location_id'] ?? null);
            $writeStarted = true;
            $result = $this->publisher->publish($claim['store'], $claim['listing'], 0,
                'rebaseline:'.$epochId.':zero:'.$destinationId);
            $confirmed = ($result['confirmation_method'] ?? null) === 'provider_readback'
                && ($result['requested_quantity'] ?? null) === 0
                && ($result['observed_quantity'] ?? null) === 0
                && ($result['provider_location_id'] ?? null) === $expectedLocation;
            $status = $confirmed ? 'zero_confirmed' : 'uncertain';
            $receipt = $confirmed ? $result : ['code' => 'exact_readback_mismatch'];
        } catch (\Throwable $error) {
            $status = $writeStarted ? 'uncertain' : 'failed_preflight';
            $receipt = ['code' => $writeStarted ? 'provider_write_or_readback_uncertain' : 'provider_preflight_failed'];
        }
        DB::table('inventory_rebaseline_destinations')->where('id', $destinationId)
            ->where('epoch_id', $epochId)->where('zero_status', 'processing')->update([
                'zero_status' => $status, 'zero_receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
                'zero_confirmed_at' => $status === 'zero_confirmed' ? now() : null,
                'updated_at' => now(),
            ]);

        return $this->receipt($epochId, $destinationId);
    }

    private function receipt(string $epochId, int $destinationId): array
    {
        $row = DB::table('inventory_rebaseline_destinations')->where('id', $destinationId)
            ->where('epoch_id', $epochId)->first();
        abort_unless($row, 404);

        return ['destination_id' => $destinationId, 'status' => $row->zero_status,
            'receipt' => json_decode($row->zero_receipt ?? '{}', true) ?: []];
    }
}
