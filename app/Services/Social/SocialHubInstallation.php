<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class SocialHubInstallation
{
    public function settings(): ?object
    {
        return DB::table('social_hub_settings')->where('id', 1)->first();
    }

    public function heartbeat(): array
    {
        $s = $this->settings();
        abort_unless($s && $s->enabled, 409, 'Installation is disabled.');
        $base = $s->hub_url;
        abort_unless($base === 'https://social.jwaistudio.online' || (app()->environment('local') && $base === 'http://127.0.0.1:8512'), 422, 'Hub URL is not allowed.');
        $body = json_encode(['registered_domain' => $s->registered_domain, 'protocol_version' => 1, 'installation_version' => 'jw-ecommerce-social-v1']);
        $secret = Crypt::decryptString($s->encrypted_secret);
        $path = '/api/v1/installations/heartbeat';
        $h = SocialProtocol::headers($s->client_id, $s->key_id, $secret, 'client-to-hub', 'POST', $path, $body);
        try {
            $r = Http::timeout(15)->connectTimeout(5)->withoutRedirecting()->withHeaders($h + ['Accept' => 'application/json'])->withBody($body, 'application/json')->post($base.$path);
            $expected = SocialProtocol::responseSignature($h, $secret, 'client-to-hub', $r->status(), $r->body());
            $ok = $r->successful() && hash_equals($expected, (string) $r->header('X-Social-Response-Signature')) && $r->json('client_id') === $s->client_id && $r->json('status') === 'verified';
            $reason = $ok ? 'verified' : ($r->successful() ? 'invalid_receipt' : 'http_rejected_'.$r->status());
        } catch (\Throwable) {
            $ok = false;
            $reason = 'connection_failed';
        }
        DB::table('social_hub_settings')->where('id', 1)->update(['last_heartbeat_status' => $reason, 'last_heartbeat_at' => now(), 'updated_at' => now()]);

        return ['ok' => $ok, 'reason' => $reason];
    }
}
