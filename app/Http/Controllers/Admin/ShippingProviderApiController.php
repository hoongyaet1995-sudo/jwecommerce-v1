<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shipping\EasyParcelOAuthAuthorizationService;
use App\Services\Shipping\ShippingProviderConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        return response()->json(['message' => 'EasyParcel platform credentials are server-managed.'], 410);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json(['data' => $this->configuration->disconnect()]);
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
            return $this->callbackRedirect('failed');
        }

        if ($authorizationUrl === null) {
            return $this->callbackRedirect('failed');
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

        if ($request->query('error') !== null) {
            $state = $request->query('state');
            $this->sanitizeCallbackRequest($request);
            try {
                $this->authorization->cancel(
                    $request->user()->id,
                    $this->authorizationSessionBinding($request),
                    is_string($state) ? $state : null,
                );
            } catch (Throwable) {
                // Provider cancellation must never reveal a callback or persistence failure.
            } finally {
                $request->session()->forget(self::AUTHORIZATION_SESSION_BINDING_KEY);
            }

            return $this->callbackRedirect('cancelled');
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
            return $this->callbackRedirect('failed');
        } finally {
            $request->session()->forget(self::AUTHORIZATION_SESSION_BINDING_KEY);
        }

        return $this->callbackRedirect($verified ? 'connected' : 'failed');
    }

    public function callbackDenied(Request $request): RedirectResponse
    {
        abort_if($request->user() === null, 401);
        $this->authorizeAdmin($request);

        $request->session()->forget(self::AUTHORIZATION_SESSION_BINDING_KEY);

        return $this->callbackRedirect('failed');
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

    private function callbackRedirect(string $state): RedirectResponse
    {
        $message = match ($state) {
            'connected' => 'EasyParcel connection verified.',
            'cancelled' => 'EasyParcel authorization was not completed.',
            default => 'EasyParcel connection could not be verified.',
        };

        return redirect()->route('admin.shipping')->with('easyparcelConnection', [
            'state' => $state,
            'message' => $message,
        ]);
    }
}
