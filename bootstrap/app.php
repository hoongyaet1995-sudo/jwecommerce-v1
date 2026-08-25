<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\ClearLegacyCustomerWebSession::class,
        ]);
        $middleware->appendToPriorityList(
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\ClearLegacyCustomerWebSession::class,
        );
        $middleware->redirectGuestsTo(
            static fn (\Illuminate\Http\Request $request): string => $request->is('account', 'account/*')
                ? route('customer.login')
                : route('admin.login'),
        );
        $middleware->redirectUsersTo(static function (\Illuminate\Http\Request $request): string {
            if ($request->user()?->is_admin) {
                return route('admin.dashboard');
            }

            if ($request->user('customer')) {
                return route('customer.account');
            }

            return route('storefront.home');
        });

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'customer' => \App\Http\Middleware\EnsureCustomer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
