<?php

namespace App\Http\Controllers;

use App\Services\Social\SocialHubInstallation;
use App\Services\Social\SocialProtocol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class SocialHubReceiverController extends Controller
{
    public function receive(Request $r, SocialHubInstallation $installation)
    {
        $s = $installation->settings();
        abort_unless($s && $s->enabled, 404);
        $h = SocialProtocol::requestHeaders($r);
        abort_unless($h['X-Social-Client'] === $s->client_id && $h['X-Social-Key-Id'] === $s->key_id, 401, 'credential_unavailable');
        $secret = Crypt::decryptString($s->encrypted_secret);
        abort_unless($r->getQueryString() === null && SocialProtocol::verify($h, $secret, 'hub-to-client', $r->method(), $r->getPathInfo(), $r->getContent()), 401, 'invalid_signature');
        $d = $r->json()->all();
        abort_unless(($d['client_id'] ?? null) === $s->client_id, 403, 'tenant_mismatch');
        abort_unless(($d['registered_domain'] ?? null) === $s->registered_domain, 403, 'domain_mismatch');
        if ($r->is('api/social/hub/challenge')) {
            $d = $r->validate(['check_id' => 'required|string|max:100', 'challenge' => 'required|regex:/^[a-f0-9]{64}$/D']);
            $response = ['status' => 'verified', 'check_id' => $d['check_id'], 'challenge' => $d['challenge']];
        } elseif ($r->is('api/social/hub/instagram')) {
            abort_if(strlen($r->getContent())>1048576,413);
            abort_unless(is_array($d['payload']??null)&&($d['payload']['object']??null)==='instagram',422);
            $response=['status'=>'accepted'];
        } else {
            $d = $r->validate(['client_id' => 'required|string', 'event_id' => 'required|string|max:100', 'type' => 'required|in:connectivity.test', 'correlation_id' => 'required|string|max:100', 'created_at' => 'required|date', 'payload' => 'required|array']);
            $response = ['status' => 'accepted', 'event_id' => $d['event_id']];
        }
        DB::table('social_hub_request_nonces')->where('expires_at', '<', now())->delete();
        abort_unless(DB::table('social_hub_request_nonces')->insertOrIgnore(['client_id' => $s->client_id, 'key_id' => $s->key_id, 'nonce' => $h['X-Social-Nonce'], 'expires_at' => now()->addMinutes(10)]), 409, 'request_replayed');
        if ($r->is('api/social/hub/instagram')) {
            $response['processed']=app(\App\Services\Social\MessengerInboundService::class)->receive($d['payload']);
        }
        if ($r->is('api/social/hub/events')) {
            $hash = hash('sha256', $r->getContent());
            DB::table('social_hub_test_event_inbox')->insertOrIgnore(['client_id' => $s->client_id, 'event_id' => $d['event_id'], 'payload_hash' => $hash, 'received_at' => now()]);
            $row = DB::table('social_hub_test_event_inbox')->where('event_id', $d['event_id'])->first();
            abort_unless($row->client_id === $s->client_id && $row->payload_hash === $hash, 409, 'event_payload_conflict');
        }

        return SocialProtocol::response($response,$h,$secret,'hub-to-client');
    }
}
