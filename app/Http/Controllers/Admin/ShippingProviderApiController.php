<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shipping\EasyParcelOAuthAuthorizationService;
use App\Services\Shipping\ShippingProviderConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class ShippingProviderApiController extends Controller
{
    private const AUTHORIZATION_SESSION_BINDING_KEY = 'easyparcel.oauth.session_binding';

    public function __construct(
        private readonly ShippingProviderConfigurationService $configuration,
        private readonly EasyParcelOAuthAuthorizationService $authorization,
    ) {}

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

        try {
            $status = $this->configuration->save(
                $data['clientId'],
                $data['clientSecret'],
                $data['enabled'],
            );
        } catch (Throwable) {
            return response()->json(['message' => 'Provider configuration could not be saved.'], 500);
        }

        return response()->json(['data' => $status]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'authorizationCode' => ['required', 'string', 'max:4096'],
            'redirectUri' => [
                'required',
                'url:https',
                'max:2048',
                Rule::in([(string) config('services.easyparcel.redirect_uri')]),
            ],
        ]);

        try {
            $result = $this->configuration->testConnection($data['authorizationCode'], $data['redirectUri']);
        } catch (Throwable) {
            return response()->json(['message' => 'Provider connection could not be verified.'], 500);
        }

        if (! $result['verified']) {
            return response()->json([
                'message' => 'Connection verification failed.',
                'data' => $result['status'],
            ], 422);
        }

        return response()->json(['data' => $result['status']]);
    }

    public function beginAuthorization(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        try {
            $sessionBinding = bin2hex(random_bytes(32));
            $authorizationUrl = $this->authorization->begin(
                $request->user()->id,
                $sessionBinding,
                (string) config('services.easyparcel.redirect_uri'),
            );
        } catch (Throwable) {
            return $this->callbackRedirect(false);
        }

        if ($authorizationUrl === null) {
            return $this->callbackRedirect(false);
        }

        $request->session()->put(self::AUTHORIZATION_SESSION_BINDING_KEY, $sessionBinding);

        return redirect()->away($authorizationUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->user() === null) {
            $this->sanitizeCallbackRequest($request);

            return redirect()->route('admin.login');
        }

        if (! $this->isSuperAdmin($request)) {
            $this->sanitizeCallbackRequest($request);

            return redirect()->route('admin.shipping.easyparcel.callback.denied');
        }

        $callback = $this->consumeCallbackRequest($request);

        try {
            $verified = $this->authorization->complete(
                $request->user()->id,
                $this->authorizationSessionBinding($request),
                $callback['state'],
                $callback['code'],
                (string) config('services.easyparcel.redirect_uri'),
            );
        } catch (Throwable) {
            return $this->callbackRedirect(false);
        }

        return $this->callbackRedirect($verified);
    }

    public function callbackDenied(Request $request): RedirectResponse
    {
        abort_if($request->user() === null, 401);
        $this->authorizeAdmin($request);

        return $this->callbackRedirect(false);
    }

    /** @return array{code: string|null, state: string|null} */
    private function consumeCallbackRequest(Request $request): array
    {
        $authorizationCode = $request->query('code');
        $state = $request->query('state');

        $this->sanitizeCallbackRequest($request);

        return [
            'code' => is_string($authorizationCode) ? $authorizationCode : null,
            'state' => is_string($state) ? $state : null,
        ];
    }

    private function sanitizeCallbackRequest(Request $request): void
    {
        $safePath = $request->getPathInfo();

        $request->query->replace([]);
        $request->server->set('QUERY_STRING', '');
        $request->server->set('REQUEST_URI', $safePath);
    }

    private function authorizationSessionBinding(Request $request): string
    {
        $binding = $request->session()->get(self::AUTHORIZATION_SESSION_BINDING_KEY);

        return is_string($binding) ? $binding : '';
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($this->isSuperAdmin($request), 403);
    }

    private function isSuperAdmin(Request $request): bool
    {
        return $request->user()?->is_admin === true && $request->user()?->is_super_admin === true;
    }

    private function callbackRedirect(bool $verified): RedirectResponse
    {
        return redirect()->route('admin.shipping')->with('easyparcelConnection', [
            'state' => $verified ? 'connected' : 'failed',
            'message' => $verified
                ? 'EasyParcel connection verified.'
                : 'EasyParcel connection could not be verified.',
        ]);
    }
}
