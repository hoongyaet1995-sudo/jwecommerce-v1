<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMarketplaceOperationsEnabled
{
    public function handle(Request $request, Closure $next, string $mode = 'all')
    {
        // Publishing requires its own explicit gate, including staging requests.
        if (($mode === 'publish' && config('marketplace.publish_operations_enabled') !== true)
            || (config('marketplace.operations_enabled') !== true
            && !($mode === 'read' && config('marketplace.read_operations_enabled') === true)
            && !($mode === 'import' && config('marketplace.import_operations_enabled') === true)
            && !($mode === 'stock' && config('marketplace.stock_sync_operations_enabled') === true)
            && !($mode === 'publish' && config('marketplace.publish_operations_enabled') === true))) {
            $code = match ($mode) {
                'stock' => 'marketplace_stock_sync_disabled',
                'publish' => 'marketplace_publishing_disabled',
                default => 'marketplace_operations_disabled',
            };
            $message = match ($mode) {
                'stock' => 'Marketplace stock synchronization is disabled for this release.',
                'publish' => 'Marketplace publishing is disabled for this release.',
                default => 'Marketplace catalog and order operations are not enabled for this release.',
            };
            return response()->json(['code'=>$code, 'message'=>$message], 503)
                ->header('Cache-Control', 'no-store');
        }
        return $next($request);
    }
}
