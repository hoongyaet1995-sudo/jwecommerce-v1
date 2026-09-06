<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;

class OAuthStateService
{
    private function configurationHash(string $platform): string
    {
        return hash_hmac('sha256', json_encode(config('marketplace.'.$platform, []), JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function issue(int $actorId, string $platform, string $deployment, ?int $reconnectStoreId): string
    {
        $state = bin2hex(random_bytes(32));
        DB::table('marketplace_oauth_states')->insert([
            'state_hash' => hash('sha256', $state),
            'actor_id' => $actorId,
            'platform' => $platform,
            'deployment_hash' => hash('sha256', $deployment),
            'configuration_hash' => $this->configurationHash($platform),
            'reconnect_store_id' => $reconnectStoreId,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        return $state;
    }

    public function consume(string $state, int $actorId, string $platform, string $deployment): ?object
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $state)) {
            return null;
        }

        return DB::transaction(function () use ($state, $actorId, $platform, $deployment) {
            $query = DB::table('marketplace_oauth_states')
                ->where('state_hash', hash('sha256', $state))
                ->where('actor_id', $actorId)
                ->where('platform', $platform)
                ->where('deployment_hash', hash('sha256', $deployment))
                ->where('configuration_hash', $this->configurationHash($platform))
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now());
            $row = (clone $query)->lockForUpdate()->first();
            if (!$row || $query->update(['consumed_at' => now()]) !== 1) {
                return null;
            }

            return $row;
        });
    }
}
