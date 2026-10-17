<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\SupportFaqService;
use Illuminate\Http\Request;
use Illuminate\Session\EncryptedStore;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/** A server-only session reference, never a persisted copy of private business facts. */
class SupportPrivateContextLease
{
    private function persistentDriver(): bool
    {
        return in_array(config('session.driver'), ['file', 'database', 'redis', 'memcached', 'dynamodb'], true);
    }

    public function issue(Request $request, SupportMessage $source): void
    {
        $settings = app(SupportFaqService::class)->read();
        $hint = $source->ai_trace['pageContext'] ?? null;
        if (! $request->hasSession() || ! $this->persistentDriver() || ! $hint || ! app(SupportCommerceContext::class)->isPath($hint['path'])) {
            return;
        }
        $conversation = $source->conversation;
        if (! $conversation || ! app(SupportCommerceContext::class)->facts($hint, $settings, $request, $conversation)) {
            return;
        }
        $payload = ['source' => $source->id, 'conversation' => $conversation->id, 'merchant' => $conversation->merchant_key,
            'owner' => $conversation->owner_hash, 'user' => $conversation->customer_user_id,
            'session' => $request->session()->getId(), 'sessionGeneration' => $request->session()->get('support_generation', ''),
            'generation' => $request->query('support_generation'), 'enquiry' => $request->input('enquiry_id'),
            'hint' => $hint, 'version' => $settings['version'] ?? 0, 'expires' => now()->addMinutes(5)->timestamp];
        // Idempotent message retries must not extend the lease or replace the original page.
        try {
            Cache::add('support-private-context:'.$source->id, Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)), now()->addMinutes(5));
        } catch (\Throwable) {
            // No lease means workers cannot read private context. The stored message remains available.
        }
    }

    public function facts(SupportMessage $source, array $settings): array
    {
        try {
            if (! $this->persistentDriver() || $source->sender_role !== 'customer') {
                return [];
            }
            $cipher = Cache::get('support-private-context:'.$source->id);
            if (! is_string($cipher)) {
                return [];
            }
            $lease = json_decode(Crypt::decryptString($cipher), true, 32, JSON_THROW_ON_ERROR);
            $c = SupportConversation::find($source->conversation_id);
            if (! $c || ! app(SupportAiPolicy::class)->enabled($settings, $c->channel) || $c->channel !== 'web_guest' || ! in_array($c->status, ['open', 'waiting_human'], true)
                || ($lease['source'] ?? null) !== $source->id || ($lease['conversation'] ?? null) !== $c->id
                || ($lease['merchant'] ?? null) !== $c->merchant_key || ($lease['user'] ?? null) !== $c->customer_user_id
                || ! hash_equals($lease['owner'] ?? '', $c->owner_hash) || ($lease['hint'] ?? null) !== ($source->ai_trace['pageContext'] ?? null)
                || ($lease['expires'] ?? 0) <= now()->timestamp || ($lease['version'] ?? -1) !== ($settings['version'] ?? 0)
                || (int) $c->messages()->where('sender_role', 'customer')->max('id') !== $source->id
                || $c->notes()->where('content', 'like', '[Archive] %')->latest('id')->value('content') === '[Archive] true') {
                return [];
            }
            if (! preg_match('/^[a-zA-Z0-9]{40}$/D', $lease['session'] ?? '')) {
                return [];
            }
            $handler = app('session')->driver()->getHandler();
            $session = config('session.encrypt')
                ? new EncryptedStore(config('session.cookie'), $handler, app('encrypter'), $lease['session'], config('session.serialization', 'php'))
                : new Store(config('session.cookie'), $handler, $lease['session'], config('session.serialization', 'php'));
            $session->start(); // Read only. Never save this isolated session or mutate global Auth state.
            if ($session->get('support_generation', '') !== $lease['sessionGeneration']) {
                return [];
            }
            $userId = $session->get(auth('customer')->getName());
            $user = $userId ? User::find($userId) : null;
            if (($user?->id ?? null) !== $lease['user'] || $user?->is_admin) {
                return [];
            }
            if (! $user && ! $session->has('support_guest_key') && empty($lease['enquiry'])) {
                return [];
            }
            $parameters = array_filter(['enquiry_id' => $lease['enquiry']], fn ($v) => $v !== null);
            $request = Request::create('/support/chat/messages'.($lease['generation'] !== null ? '?'.http_build_query(['support_generation' => $lease['generation']]) : ''), 'POST', $parameters);
            $request->setLaravelSession($session);
            $request->setUserResolver(fn ($guard = null) => $guard === 'customer' ? $user : null);

            return app(SupportCommerceContext::class)->facts($lease['hint'], $settings, $request, $c);
        } catch (\Throwable) {
            // Missing/expired sessions, corrupt cache and revoked resources never widen access.
            return [];
        }
    }

    public function fingerprint(array $facts): string
    {
        return hash_hmac('sha256', json_encode($facts, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function publicationAllowed(SupportMessage $source, array $settings, ?string $fingerprint = null): bool
    {
        $hint = $source->ai_trace['pageContext'] ?? null;
        if (! $hint || ! app(SupportCommerceContext::class)->isPath($hint['path'])) {
            return true;
        }
        if (request()->hasSession()) {
            $facts = app(SupportCommerceContext::class)->facts($hint, $settings, request(), $source->conversation->fresh());
        } else {
            $facts = $this->facts($source, $settings);
        }

        return (bool) $facts && ($fingerprint === null || hash_equals($fingerprint, $this->fingerprint($facts)));
    }
}
