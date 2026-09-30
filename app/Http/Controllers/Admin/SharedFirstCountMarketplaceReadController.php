<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\SharedFirstCountMarketplaceRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SharedFirstCountMarketplaceReadController extends Controller
{
    public function __invoke(Request $request, int $variant, SharedFirstCountMarketplaceRead $reader): JsonResponse
    {
        return response()->json($reader->read($request->user(), $variant))
            ->header('Cache-Control', 'no-store');
    }
}
