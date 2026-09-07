<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMarketplaceOperationsEnabled
{
    public function handle(Request $request, Closure $next, string $mode = 'all')
    {
        if (config('marketplace.operations_enabled') !== true
            && !($mode === 'read' && config('marketplace.read_operations_enabled') === true)
            && !($mode === 'import' && config('marketplace.import_operations_enabled') === true)) {
            return response()->json(['code'=>'marketplace_operations_disabled',
                'message'=>'Marketplace catalog and order operations are not enabled for this release.'], 503)
                ->header('Cache-Control', 'no-store');
        }
        return $next($request);
    }
}
