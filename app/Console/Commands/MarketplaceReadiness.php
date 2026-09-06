<?php

namespace App\Console\Commands;

use App\Services\Marketplace\ShopeeClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class MarketplaceReadiness extends Command
{
    protected $signature = 'marketplace:readiness {--json : Emit secret-free configuration and schema checks}';
    protected $description = 'Read-only local Marketplace readiness checks; never calls a provider or claims real acceptance';

    public function handle(ShopeeClient $client): int
    {
        $tables = ['marketplace_stores', 'marketplace_store_tokens', 'marketplace_oauth_states',
            'marketplace_listing_variants', 'marketplace_variant_mappings', 'marketplace_mapping_events',
            'marketplace_catalog_runs', 'marketplace_order_runs', 'marketplace_orders', 'marketplace_product_imports', 'jobs'];
        $data = [
            'platform'=>'shopee', 'environment'=>config('marketplace.shopee.environment'),
            'provider_configured'=>$client->isConfigured(),
            'oauth_callback_ready'=>$client->isAuthorizationReady(),
            'missing_tables'=>array_values(array_filter($tables, fn ($table) => !Schema::hasTable($table))),
            'queue_driver'=>config('queue.connections.marketplace.driver'),
            'queue_worker'=>'not_verified_by_this_check',
            'real_provider_acceptance'=>'not_verified_by_this_check',
        ];
        $this->line(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | ($this->option('json') ? 0 : JSON_PRETTY_PRINT)));

        return $data['oauth_callback_ready'] && !$data['missing_tables'] && $data['queue_driver'] === 'database'
            ? self::SUCCESS : self::FAILURE;
    }
}
