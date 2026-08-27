<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingProviderConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class ShippingProviderApiController extends Controller
{
    private const AUTHORIZATION_TRANSACTION_SESSION_KEY = 'easyparcel.oauth.authorization';

    private const AUTHORIZATION_TRANSACTION_TTL_SECONDS = 600;

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
        $request->session()->forget(self::AUTHORIZATION_TRANSACTION_SESSION_KEY);

        try {
            $state = bin2hex(random_bytes(32));
            $authorizationUrl = $this->configuration->authorizationUrl(
                $state,
                (string) config('services.easyparcel.redirect_uri'),
            );
        } catch (Throwable) {
            return $this->callbackRedirect(false);
        }

        if ($authorizationUrl === null) {
            return $this->callbackRedirect(false);
        }

        $request->session()->put(self::AUTHORIZATION_TRANSACTION_SESSION_KEY, [
            'state_hash' => hash('sha256', $state),
            'expires_at' => now()->addSeconds(self::AUTHORIZATION_TRANSACTION_TTL_SECONDS)->timestamp,
            'available' => true,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->away($authorizationUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        $callback = $this->consumeCallbackRequest($request);

        abort_if($request->user() === null, 401);
        $this->authorizeAdmin($request);

        if (! $this->consumeAuthorizationTransaction($request, $callback['state'])
            || $callback['code'] === null
            || $callback['code'] === ''
            || strlen($callback['code']) > 4096) {
            return $this->callbackRedirect(false);
        }

        try {
            $result = $this->configuration->testConnection(
                $callback['code'],
                (string) config('services.easyparcel.redirect_uri'),
            );
        } catch (Throwable) {
            return $this->callbackRedirect(false);
        }

        return $this->callbackRedirect($result['verified']);
    }

    /** @return array{code: string|null, state: string|null} */
    private function consumeCallbackRequest(Request $request): array
    {
        $authorizationCode = $request->query('code');
        $state = $request->query('state');
        $safePath = $request->getPathInfo();

        $request->query->replace([]);
        $request->server->set('QUERY_STRING', '');
        $request->server->set('REQUEST_URI', $safePath);

        return [
            'code' => is_string($authorizationCode) ? $authorizationCode : null,
            'state' => is_string($state) ? $state : null,
        ];
    }

    private function consumeAuthorizationTransaction(Request $request, ?string $state): bool
    {
        $transaction = $request->session()->pull(self::AUTHORIZATION_TRANSACTION_SESSION_KEY);
        $providedState = $state !== null && strlen($state) <= 512 ? $state : '';
        $expectedStateHash = is_array($transaction)
            && is_string($transaction['state_hash'] ?? null)
            && strlen($transaction['state_hash']) === 64
                ? $transaction['state_hash']
                : str_repeat('0', 64);
        $stateMatches = hash_equals($expectedStateHash, hash('sha256', $providedState));

        return is_array($transaction)
            && $state !== null
            && $state !== ''
            && strlen($state) <= 512
            && $stateMatches
            && ($transaction['available'] ?? null) === true
            && is_int($transaction['expires_at'] ?? null)
            && $transaction['expires_at'] > now()->timestamp
            && ($transaction['user_id'] ?? null) === $request->user()?->id;
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->is_admin === true && $request->user()?->is_super_admin === true,
            403,
        );
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
