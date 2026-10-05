<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Social\SocialHubInstallation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class SocialHubInstallationController extends Controller
{
    private function guard(Request $r): void
    {
        $developerIds = array_map('strval', config('services.social_hub.developer_user_ids', []));
        abort_unless($r->user()?->is_super_admin && in_array((string) $r->user()->getAuthIdentifier(), $developerIds, true), 403);
    }

    public function page(Request $r)
    {
        $this->guard($r);

        return view('admin.social-hub-installation');
    }

    public function index(Request $r, SocialHubInstallation $service)
    {
        $this->guard($r);
        $s = $service->settings();
        if ($s) {
            unset($s->encrypted_secret);
        }

return response()->json(['installation' => $s])->header('Cache-Control', 'no-store');
    }

    public function save(Request $r)
    {
        $this->guard($r);
        $d = $r->validate(['hub_url' => 'required|string', 'client_id' => 'required|string|max:100', 'key_id' => 'required|string|max:100', 'secret' => 'nullable|string|min:32|max:512', 'registered_domain' => 'required|string|max:253', 'enabled' => 'required|boolean']);
        abort_unless($d['hub_url'] === 'https://social.jwaistudio.online' || (app()->environment('local') && $d['hub_url'] === 'http://127.0.0.1:8512'), 422, 'Hub URL is not allowed.');
        abort_unless(filter_var($d['registered_domain'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME), 422, 'Invalid registered domain.');
        $old = DB::table('social_hub_settings')->where('id', 1)->first();
        abort_unless(! empty($d['secret']) || ($old && $old->key_id === $d['key_id'] && $old->client_id === $d['client_id']), 422, 'Secret is required for new credentials.');
        $secret = ! empty($d['secret']) ? Crypt::encryptString($d['secret']) : $old->encrypted_secret;
        unset($d['secret']);
        DB::table('social_hub_settings')->updateOrInsert(['id' => 1], $d + ['encrypted_secret' => $secret, 'last_heartbeat_status' => null, 'last_heartbeat_at' => null, 'created_at' => $old->created_at ?? now(), 'updated_at' => now()]);

        return response()->json(['status' => 'saved'])->header('Cache-Control', 'no-store');
    }

    public function heartbeat(Request $r, SocialHubInstallation $service)
    {
        $this->guard($r);

        return response()->json($service->heartbeat());
    }
}
