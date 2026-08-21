<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\SimulatedPaymentGateway;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, SimulatedPaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('cms-contact', fn (Request $request): Limit => Limit::perMinute(3)
            ->by('cms-contact:'.$request->ip()));
        RateLimiter::for('cms-newsletter', fn (Request $request): Limit => Limit::perMinute(3)
            ->by('cms-newsletter:'.$request->ip()));
        View::composer('components.admin.shell', \App\View\Composers\AdminShellComposer::class);
    }
}
