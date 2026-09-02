<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CatalogListingSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogListingSettingsApiController extends Controller
{
    public function __construct(private readonly CatalogListingSettingsService $settings) {}

    public function show(): JsonResponse
    {
        return response()->json($this->settings->live());
    }

    public function update(Request $request): JsonResponse
    {
        return response()->json($this->settings->save($request->all()));
    }
}
