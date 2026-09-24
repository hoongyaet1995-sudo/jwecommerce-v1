<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\FacilityBalanceReader;
use App\Services\Inventory\StoreManagement\FacilityDiscovery;
use App\Services\Inventory\StoreManagement\FacilityMigrationPreview;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\OverviewReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class StoreInventoryReadController extends Controller
{
    public function overview(Request $request, OverviewReader $reader): JsonResponse
    {
        $filters = $request->validate(['platform' => ['nullable', Rule::in(['shopee', 'lazada'])], 'sku' => ['nullable', 'string', 'max:100']]);

        return response()->json($reader->read($filters))->header('Cache-Control', 'no-store');
    }

    public function stores(Request $request, OverviewReader $reader): JsonResponse
    {
        $filters = $request->validate(['platform' => ['nullable', Rule::in(['shopee', 'lazada'])]]);

        return response()->json(['data' => $reader->stores($filters)])->header('Cache-Control', 'no-store');
    }

    public function facilities(int $store, FacilityRegistry $registry): JsonResponse
    {
        abort_unless(DB::table('marketplace_stores')->where('id', $store)
            ->whereIn('platform', ['shopee', 'lazada'])->exists(), 404);

        return response()->json($registry->read($store))->header('Cache-Control', 'no-store');
    }

    public function facilityBalances(int $store, int $variant, FacilityBalanceReader $reader): JsonResponse
    {
        return response()->json($reader->read($store, $variant))->header('Cache-Control', 'no-store');
    }

    public function facilityMigrationPreview(Request $request, int $store, int $variant, FacilityMigrationPreview $planner): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);
        $input = $request->validate([
            'physical_by_provider_id' => ['required', 'array', 'min:1', 'max:100'],
            'physical_by_provider_id.*' => ['required', 'integer', 'min:0'],
        ]);

        return response()->json($planner->preview($store, $variant, $input['physical_by_provider_id']))
            ->header('Cache-Control', 'no-store');
    }

    public function refreshFacilities(Request $request, int $store, FacilityDiscovery $discovery): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);
        abort_unless(config('marketplace.multiwarehouse_discovery_enabled') === true, 404);
        abort_unless(DB::table('marketplace_stores')->where('id', $store)
            ->whereIn('platform', ['shopee', 'lazada'])->exists(), 404);

        return response()->json($discovery->refresh($store))->header('Cache-Control', 'no-store');
    }

    public function history(Request $request, OverviewReader $reader): JsonResponse
    {
        $filters = $request->validate(['kind' => ['nullable', 'string', 'max:32'], 'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return response()->json($reader->history($filters))->header('Cache-Control', 'no-store');
    }

    public function operation(string $operation, OverviewReader $reader): JsonResponse
    {
        return response()->json($reader->operation($operation))->header('Cache-Control', 'no-store');
    }
}
