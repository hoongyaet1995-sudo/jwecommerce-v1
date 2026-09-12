<?php
namespace App\Console\Commands;

use App\Models\User;
use App\Services\Marketplace\LazadaTestConnectionService;
use Illuminate\Console\Command;

class RenewLazadaTestLease extends Command
{
    protected $signature='marketplace:lazada-test-renew {store} {--actor=} {--reason=}';
    protected $description='Revalidate the designated official Lazada test seller and renew its internal test lease';

    public function handle(LazadaTestConnectionService $service):int
    {
        try {
            $store=filter_var($this->argument('store'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $actorId=filter_var($this->option('actor'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if (!$store || !$actorId) throw new \RuntimeException('Identity required.');
            $actor=User::whereKey($actorId)->where('is_admin',true)->firstOrFail();
            $receipt=$service->renew($store,$actor,(string)$this->option('reason'));
            $this->line(json_encode($receipt,JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Throwable $error) {
            // Provider error text can include request details; never echo it or credentials.
            $this->error('Test lease renewal failed. Verify the designated store, administrator, flags and official test credential.');
            return self::FAILURE;
        }
    }
}
