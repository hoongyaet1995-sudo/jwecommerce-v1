<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingProviderConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShippingProviderApiController extends Controller
{
    public function __construct(private readonly ShippingProviderConfigurationService $configuration) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json(['data' => $this->configuration->status()]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'environment' => ['required', 'string', Rule::in(['sandbox'])],
            'clientId' => ['required', 'string', 'max:2048'],
            'clientSecret' => ['required', 'string', 'max:4096'],
            'enabled' => ['required', 'boolean'],
        ]);

        return response()->json(['data' => $this->configuration->save(
            $data['clientId'],
            $data['clientSecret'],
            $data['enabled'],
        )]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'authorizationCode' => ['required', 'string', 'max:4096'],
            'redirectUri' => ['required', 'url:https', 'max:2048'],
        ]);

        $status = $this->configuration->testConnection($data['authorizationCode'], $data['redirectUri']);
        if ($status['connectionState'] !== 'connected') {
            return response()->json([
                'message' => 'Connection verification failed.',
                'data' => $status,
            ], 422);
        }

        return response()->json(['data' => $status]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_super_admin === true, 403);
    }
}
