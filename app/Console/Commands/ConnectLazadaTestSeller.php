<?php

namespace App\Console\Commands;

use App\Services\Marketplace\LazadaTestConnectionService;
use Illuminate\Console\Command;

class ConnectLazadaTestSeller extends Command
{
    protected $signature = 'marketplace:lazada-test-connect';

    protected $description = 'Securely connect the designated Lazada MY borrowed test seller';

    public function handle(LazadaTestConnectionService $service): int
    {
        $accessToken = trim((string) $this->secret('Lazada official test access token'));

        if ($accessToken === '') {
            $this->error('A test access token is required.');

            return self::FAILURE;
        }

        try {
            $storeId = $service->connect($accessToken);
        } catch (\Throwable) {
            // Provider errors may contain sensitive request details. Keep console output generic.
            $this->error('Test seller connection failed. Verify the app, token, and borrowed seller identity.');

            return self::FAILURE;
        } finally {
            $accessToken = '';
        }

        $this->info("Connected verified Lazada MY Test Seller as store #{$storeId}.");

        return self::SUCCESS;
    }
}
