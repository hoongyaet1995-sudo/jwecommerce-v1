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

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'customer' => \App\Http\Middleware\EnsureCustomer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
