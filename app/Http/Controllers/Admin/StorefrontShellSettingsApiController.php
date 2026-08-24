<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StorefrontShellSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontShellSettingsApiController extends Controller
{
    public function __construct(private readonly StorefrontShellSettingsService $settings) {}

    public function show(): JsonResponse
    {
        return response()->json($this->settings->live());
    }

    public function update(Request $request): JsonResponse
    {
        $setting = $this->settings->save($request->all());

        return response()->json($setting->payload);
    }
}
