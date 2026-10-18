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
            'webhooks/facebook/messenger',
            'webhooks/instagram/messages',
            'payments/webhooks/stripe',
            'payments/webhooks/billplz',
            'payments/webhooks/paypal',
            'webhooks/easyparcel',
            'webhooks/easyparcel/*',
            'api/social/hub/challenge',
            'api/social/hub/events',
            'api/social/hub/instagram',
            'api/social/hub/whatsapp',
            'api/social/hub/pair',
            'api/marketplace/hub/refresh',
            'api/marketplace/hub/events',
            'api/marketplace/hub/verify-shopee',
        ]);
        $middleware->web(append: [
            ClearLegacyCustomerWebSession::class,
            \App\Http\Middleware\SearchVisibility::class,
        ]);
        $middleware->appendToPriorityList(
            StartSession::class,
            ClearLegacyCustomerWebSession::class,
        );
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, \App\Http\Middleware\SearchVisibility::class);
        $middleware->redirectGuestsTo(
            static fn (Request $request): string => $request->is('account', 'account/*')
                ? route('customer.login')
                : route('admin.login'),
        );
        $middleware->redirectUsersTo(static function (Request $request): string {
            if ($request->is('account', 'account/*')) {
                return $request->user('customer')
                    ? route('customer.account')
                    : route('customer.login');
            }

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
        // Native Admin forms need the same expired-session recovery as Admin fetch requests.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, Request $request) {
            if ($response->getStatusCode() === 419
                && $request->is('admin', 'admin/*')
                && ! $request->expectsJson()) {
                return redirect()->route('admin.login', [], 303)
                    ->withErrors(['email' => 'Your session has expired. Please sign in again.']);
            }

            return $response;
        });
        $exceptions->dontFlash([
            'api_key',
            'secret',
            'pairing_code',
            'clientId',
            'clientSecret',
            'authorizationCode',
        ]);
    })->create();
