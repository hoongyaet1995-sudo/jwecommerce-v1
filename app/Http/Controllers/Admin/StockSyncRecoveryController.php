<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\StockSyncRecovery;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class StockSyncRecoveryController extends Controller
{
    public function show(Request $request, int $store, int $variant, StockSyncRecovery $sync)
    {
        $this->authorizeActor($request);

        return response()->json($sync->details($store, $variant))->header('Cache-Control', 'no-store');
    }

    public function check(Request $request, int $store, int $variant, StockSyncRecovery $sync)
    {
        $this->authorizeActor($request);

        return response()->json($sync->check($store, $variant, (int) $request->user()->id))->header('Cache-Control', 'no-store');
    }

    public function resync(Request $request, int $store, int $variant, StockSyncRecovery $sync)
    {
        $this->authorizeActor($request);
        abort_if(array_diff(array_keys($request->all()), ['operation_key', '_token']), 422, 'Unsupported stock synchronization fields.');
        $input = $request->validate(['operation_key' => ['required', 'uuid']]);

        return response()->json($sync->resync($store, $variant, $input['operation_key'], (int) $request->user()->id))->header('Cache-Control', 'no-store');
    }

    public function bulkCheck(Request $request, StockSyncRecovery $sync)
    {
        $this->authorizeActor($request);
        abort_if(array_diff(array_keys($request->all()), ['items', '_token']), 422, 'Unsupported bulk status fields.');
        $input = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*' => ['required', 'array:store_id,variant_id'],
            'items.*.store_id' => ['required', 'integer', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'min:1'],
        ]);
        $pairs = collect($input['items'])->unique(fn (array $item): string => $item['store_id'].':'.$item['variant_id'])->values();
        abort_unless($pairs->count() === count($input['items']), 422, 'Duplicate store and SKU pairs are not allowed.');

        $results = $pairs->map(function (array $item) use ($request, $sync): array {
            try {
                return ['storeId' => (string) $item['store_id'], 'variantId' => (int) $item['variant_id'],
                    'ok' => true, 'details' => $sync->check((int) $item['store_id'], (int) $item['variant_id'],
                        (int) $request->user()->id, false)];
            } catch (Throwable $error) {
                return ['storeId' => (string) $item['store_id'], 'variantId' => (int) $item['variant_id'],
                    'ok' => false, 'error' => $error instanceof HttpExceptionInterface && $error->getMessage()
                        ? $error->getMessage() : 'Marketplace status could not be checked.'];
            }
        });
        $checked = $results->where('ok', true)->count();

        return response()->json(['summary' => ['requested' => $results->count(), 'checked' => $checked,
            'failed' => $results->count() - $checked], 'results' => $results->map(fn (array $result): array => $result['ok']
                ? [...$result, ...$result['details']] : $result)->values()])->header('Cache-Control', 'no-store');
    }

    private function authorizeActor(Request $request): void
    {
        $actor = $request->user();
        abort_unless($actor?->is_admin && ($actor->is_super_admin || $actor->permissions()->where('permission', 'inventory.edit')->exists()), 403);
    }
}
