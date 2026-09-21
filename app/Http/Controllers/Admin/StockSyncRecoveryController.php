<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\StockSyncRecovery;
use Illuminate\Http\Request;

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

    private function authorizeActor(Request $request): void
    {
        $actor = $request->user();
        abort_unless($actor?->is_admin && ($actor->is_super_admin || $actor->permissions()->where('permission', 'inventory.edit')->exists()), 403);
    }
}
