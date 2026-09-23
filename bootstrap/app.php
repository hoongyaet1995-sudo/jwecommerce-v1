<?php

use App\Http\Middleware\ClearLegacyCustomerWebSession;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureCustomer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'payments/webhooks/stripe',
            'payments/webhooks/billplz',
            'payments/webhooks/paypal',
            'webhooks/easyparcel',
            'webhooks/easyparcel/*',
            'api/marketplace/hub/refresh',
            'api/marketplace/hub/events',
            'api/marketplace/hub/verify-shopee',
        ]);
        $middleware->web(append: [
            ClearLegacyCustomerWebSession::class,
        ]);
        $middleware->appendToPriorityList(
            StartSession::class,
            ClearLegacyCustomerWebSession::class,
        );
        $middleware->redirectGuestsTo(
            static fn (Request $request): string => $request->is('account', 'account/*')
                ? route('customer.login')
                : route('admin.login'),
        );
        $middleware->redirectUsersTo(static function (Request $request): string {
            if ($request->user()?->is_admin) {
                return route('admin.dashboard');
            }

            if ($request->user('customer')) {
                return route('customer.account');
            }

            return route('storefront.home');
        });

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'customer' => EnsureCustomer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'clientId',
            'clientSecret',
            'authorizationCode',
        ]);
    })->create();
