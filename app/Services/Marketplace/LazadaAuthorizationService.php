<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStoreToken;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LazadaAuthorizationService
{
    public function __construct(private OAuthStateService $states, private LazadaClient $client) {}

    public function complete(string $state, int $actorId, #[\SensitiveParameter] string $code): int
    {
        $attempt=$this->states->consume($state,$actorId,'lazada',config('app.url'));
        if (!$attempt) throw new RuntimeException('Authorization expired or invalid. Start again.');
        $configuration=config('marketplace.lazada');
        $tokens=$this->client->exchange($code);
        return DB::transaction(function () use ($attempt,$tokens,$configuration) {
            if (config('marketplace.lazada')!==$configuration) {
                throw new RuntimeException('Authorization configuration changed. Start again.');
            }
            $store=$attempt->reconnect_store_id
                ? DB::table('marketplace_stores')->where('id',$attempt->reconnect_store_id)->lockForUpdate()->first() : null;
            if (!DB::table('marketplace_oauth_states')->where('id',$attempt->id)->where('expires_at','>',now())->exists()) {
                throw new RuntimeException('Authorization expired or invalid. Start again.');
            }
            // Testing is an application approval status, not a separate seller API environment.
            if ($attempt->reconnect_store_id && (!$store || $store->platform!=='lazada'
                || $store->environment!=='live' || $store->region!=='MY' || $store->external_shop_id!==$tokens['seller_id'])) {
                throw new RuntimeException('Reconnect must authorize the original store.');
            }
            if (!$store && DB::table('marketplace_stores')->where('platform','lazada')->where('environment','live')
                ->where('external_shop_id',$tokens['seller_id'])->exists()) {
                throw new RuntimeException('This store already exists. Use Reconnect on that store.');
            }
            $values=['platform'=>'lazada','environment'=>'live','external_shop_id'=>$tokens['seller_id'],
                // System label, not a claimed provider shop name; preserve an existing merchant label on reconnect.
                'shop_name'=>$store?->shop_name ?? 'Lazada MY '.$tokens['seller_id'],
                'region'=>'MY','status'=>'connected','updated_at'=>now()];
            if ($store) {
                $id=$store->id;
                DB::table('marketplace_stores')->where('id',$id)->update($values);
            } else {
                $id=DB::table('marketplace_stores')->insertGetId($values+['created_at'=>now()]);
            }
            MarketplaceStoreToken::updateOrCreate(['store_id'=>$id],[
                'credential_source'=>'oauth','test_lease_expires_at'=>null,
                'application_id'=>(string)config('marketplace.lazada.app_key'),
                'access_token'=>$tokens['access_token'],'refresh_token'=>$tokens['refresh_token'],
                'access_expires_at'=>now()->addSeconds($tokens['expires_in']),
                'refresh_expires_at'=>now()->addSeconds($tokens['refresh_expires_in']),
            ]);
            return $id;
        });
    }
}
