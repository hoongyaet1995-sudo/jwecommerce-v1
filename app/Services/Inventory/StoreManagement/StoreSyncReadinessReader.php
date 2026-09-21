<?php

namespace App\Services\Inventory\StoreManagement;

use Illuminate\Support\Facades\DB;

final class StoreSyncReadinessReader
{
    public function __construct(private readonly ReadinessReader $legacy) {}

    public function read(int $storeId): array
    {
        $result = $this->legacy->takeover($storeId);
        $account = DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)->first();
        $requirements = collect($result['blockers'])->groupBy('code')->map(function ($rows, string $code): array {
            $ids = $rows->flatMap(fn (array $row) => $row['ids'] ?? (isset($row['variant_id']) ? [$row['variant_id']] : []))
                ->filter()->unique()->values()->all();

            return [
                'code' => $code,
                'count' => max(count($ids), $rows->count()),
                'ids' => $ids,
                'next_action' => (string) $rows->first()['next_action'],
            ];
        })->values()->all();

        $status = $account?->management_status ?? 'setup_required';
        if (($result['store']?->status ?? null) !== 'connected' && $status === 'managed') {
            $status = 'setup_required';
        }

        return [
            'ready' => $requirements === [],
            'effective_state' => match ($status) {
                'managed' => 'active',
                'paused' => 'paused',
                default => 'setup_required',
            },
            'inventory_membership' => 'enrolled',
            'requirements' => $requirements,
            'review_token' => $result['review_token'],
            'balances' => $result['balances'],
            'snapshot' => $result['snapshot'],
        ];
    }
}
