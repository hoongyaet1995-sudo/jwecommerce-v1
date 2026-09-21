<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\ModeMutation;
use App\Services\Inventory\StoreManagement\ReadinessReader;
use App\Services\Inventory\StoreManagement\StoreSyncReadinessReader;
use App\Services\Inventory\StoreManagement\StoreSyncStateMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StoreInventorySettingsController extends Controller
{
    public function readiness(Request $request, int $store, ReadinessReader $reader): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);

        return response()->json($reader->takeover($store));
    }

    public function syncReadiness(Request $request, int $store, StoreSyncReadinessReader $reader): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);

        return response()->json($reader->read($store));
    }

    public function pauseSync(Request $request, int $store, StoreSyncStateMutation $mutation): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return response()->json($mutation->pause($store, $request->user(), $data['reason']));
    }

    public function resumeSync(Request $request, int $store, StoreSyncStateMutation $mutation): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'review_token' => ['required', 'string', 'size:64']]);

        return response()->json($mutation->resume($store, $request->user(), $data['reason'], $data['review_token']));
    }

    public function takeover(Request $request, int $store): JsonResponse
    {
        return response()->json(['message' => 'Inventory takeover is automatic. Use sync-readiness, pause-sync, or resume-sync.'], 410);
    }

    public function pause(Request $request, int $store): JsonResponse
    {
        return response()->json(['message' => 'Inventory takeover is automatic. Use sync-readiness, pause-sync, or resume-sync.'], 410);
    }

    public function mode(Request $request, ModeMutation $mutation): JsonResponse
    {
        $data = $request->validate(['target' => ['required', 'string'], 'configuration_version' => ['required', 'integer'], 'distribution' => ['present', 'array']]);

        return response()->json($mutation->switch($data['target'], $data['distribution'], $data['configuration_version'], $request->user()));
    }
}
