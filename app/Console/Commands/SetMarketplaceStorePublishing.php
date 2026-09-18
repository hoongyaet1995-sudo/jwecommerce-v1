<?php

namespace App\Console\Commands;

use App\Services\Marketplace\MarketplaceStoreAccess;
use Illuminate\Console\Command;
use RuntimeException;

class SetMarketplaceStorePublishing extends Command
{
    protected $signature='marketplace:store-publishing {store : Marketplace store ID} {state : enable or disable} {--actor= : Administrator ID} {--reason= : Auditable reason for this change}';
    protected $description='Enable or disable product publishing for one authorized marketplace store';

    public function handle(MarketplaceStoreAccess $access): int
    {
        try {
            $storeId=filter_var($this->argument('store'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $actorId=filter_var($this->option('actor'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $state=(string)$this->argument('state');
            if (!$storeId || !$actorId || !in_array($state,['enable','disable'],true)) {
                throw new RuntimeException('Store, administrator, and a valid state are required.');
            }

            $access->setProductPublishing($storeId,$state==='enable',$actorId,(string)$this->option('reason'));
            $this->info('Marketplace store publishing capability updated.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Marketplace store publishing capability was not changed. Verify the store, administrator, state, and reason.');

            return self::FAILURE;
        }
    }
}
