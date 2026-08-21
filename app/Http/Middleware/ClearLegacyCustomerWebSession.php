<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClearLegacyCustomerWebSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $webUser = Auth::guard('web')->user();

        if ($webUser && ! $webUser->is_admin) {
            Auth::guard('web')->logout();
        }

        return $next($request);
    }
}
