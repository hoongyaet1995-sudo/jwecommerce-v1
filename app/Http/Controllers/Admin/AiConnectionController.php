<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AI\AiConnectionService;
use App\Services\AI\ProviderFailure;
use App\Services\AI\ProviderRegistry;
use Illuminate\Http\Request;

class AiConnectionController extends Controller
{
    private function authorizeOwner(Request $request, ?string $provider = null): void
    {
        abort_unless($request->user()?->is_super_admin, 403, 'Only the store owner can manage AI credentials.');
        if ($provider !== null) {
            abort_unless(array_key_exists($provider, ProviderRegistry::LABELS), 404);
        }
    }

    public function index(Request $request, AiConnectionService $service)
    {
        $this->authorizeOwner($request);

        return response()->json(['connections' => $service->all(), 'compatible_base_urls' => config('ai_connect.compatible_base_urls', [])])->header('Cache-Control', 'no-store');
    }

    public function save(Request $request, string $provider, AiConnectionService $service)
    {
        $this->authorizeOwner($request, $provider);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'], 'enabled' => ['required', 'boolean'],
            'search_enabled' => ['sometimes', 'boolean'], 'support_enabled' => ['required', 'boolean'], 'builder_enabled' => ['required', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:4096', 'regex:/^\S+$/'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'text_model' => ['nullable', 'string', 'max:150'], 'image_model' => ['nullable', 'string', 'max:150'],
        ]);

        return response()->json(['connection' => $service->view($service->save($provider, $data), $provider)])->header('Cache-Control', 'no-store');
    }

    public function verify(Request $request, string $provider, AiConnectionService $service)
    {
        $this->authorizeOwner($request, $provider);
        $data = $request->validate(['version' => ['required', 'integer', 'min:0']]);
        try {
            $connection = $service->verify($provider, $data['version']);

            return response()->json(['connection' => $service->view($connection, $provider)])->header('Cache-Control', 'no-store');
        } catch (ProviderFailure $failure) {
            return response()->json(['message' => $failure->getMessage(), 'code' => $failure->reason], 422)->header('Cache-Control', 'no-store');
        }
    }
}
