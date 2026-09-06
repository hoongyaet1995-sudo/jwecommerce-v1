<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMarketplaceOperationsEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (config('marketplace.operations_enabled') !== true) {
            return response()->json(['code'=>'marketplace_operations_disabled',
                'message'=>'Marketplace catalog and order operations are not enabled for this release.'], 503)
                ->header('Cache-Control', 'no-store');
        }
        return $next($request);
    }
}
