<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\StoreInventoryPublisher;
use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\FacilitySyncRecovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FacilitySyncRecoveryController extends Controller
{
    public function details(Request $request, int $store, int $facility, int $variant,
        FacilitySyncRecovery $service): JsonResponse
    {
        $this->authorizeActor($request);

        return response()->json($service->details($store, $facility, $variant))
            ->header('Cache-Control', 'no-store');
    }

    public function check(Request $request, int $store, int $facility, int $variant,
        FacilitySyncRecovery $service, StoreInventoryPublisher $publisher): JsonResponse
    {
        $this->authorizeActor($request);
        $request->validate([]);

        return response()->json($service->check($store, $facility, $variant, $publisher))
            ->header('Cache-Control', 'no-store');
    }

    public function resync(Request $request, int $store, int $facility, int $variant,
        FacilitySyncRecovery $service): JsonResponse
    {
        $this->authorizeActor($request);
        $data = $request->validate(['operation_key' => ['required', 'string', 'max:120']]);

        return response()->json($service->resync($store, $facility, $variant, $data['operation_key'],
            (int) $request->user()->id, 'Manual resend of current JW available stock'))
            ->header('Cache-Control', 'no-store');
    }

    private function authorizeActor(Request $request): void
    {
        $actor = $request->user();
        abort_unless($actor?->is_admin
            && ($actor->is_super_admin || $actor->permissions()->where('permission', 'inventory.edit')->exists()), 403);
    }
}
