<?php

namespace App\Http\Middleware;

use App\Services\Search\SearchMetadata;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SearchVisibility
{
    public function handle(Request $r, Closure $next)
    {
        if ($r->isMethod('GET') && Schema::hasTable('search_redirects')) {
            $redirect = DB::table('search_redirects')->where('from_path', $r->getPathInfo())->first();
            if ($redirect) {
                try {
                    $metadata = app(SearchMetadata::class);
                    $target = $metadata->path($redirect->kind, $redirect->entity_id);
                    if ($target !== $r->getPathInfo() && $metadata->public($redirect->kind, $redirect->entity_id)) {
                        return redirect()->to($target, 301);
                    }
                } catch (ModelNotFoundException) {
                }
            }
        }
        $response = $next($r);
        if ($r->attributes->get('searchNoindex') || $r->is('admin', 'admin/*', 'account', 'account/*', 'cart', 'cart/*', 'checkout', 'checkout/*', 'enquiries/*', 'quotations/*', 'support/*', 'page-template-sample/*', 'order-tracking', 'order-tracking/*', 'login', 'register')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            if ($r->is('admin/*', 'account/*', 'enquiries/*', 'quotations/*', 'checkout/*')) {
                $response->headers->set('Cache-Control','private, no-store');
            }
        }

        return $response;
    }
}
