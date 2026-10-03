<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\StorefrontShellSettingsService;
use App\Services\SimulatedPaymentGateway;
use App\Support\StorefrontCurrencyContext;
use App\Support\ShopBrand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\ServiceProvider;
use App\Contracts\StoreInventoryPublisher;
use App\Services\Inventory\StoreManagement\MarketplaceStoreInventoryPublisher;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, SimulatedPaymentGateway::class);
        $this->app->bind(StoreInventoryPublisher::class, MarketplaceStoreInventoryPublisher::class);
        $this->app->scoped(StorefrontCurrencyContext::class);
        $this->app->scoped(ShopBrand::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('ai-provider-save', fn (Request $request): Limit => Limit::perMinute(20)
            ->by('ai-save:'.$request->user()?->id.':'.$request->route('provider')));
        RateLimiter::for('ai-provider-check', fn (Request $request): Limit => Limit::perMinute(6)
            ->by('ai-check:'.$request->user()?->id.':'.$request->route('provider')));
        Gate::policy(\App\Models\ProductReview::class, \App\Policies\ProductReviewPolicy::class);
        Blade::directive('storefrontMoney', static fn (string $expression): string => "<?php echo app(\\App\\Support\\StorefrontCurrencyContext::class)->format({$expression}); ?>");
        RateLimiter::for('cms-contact', fn (Request $request): Limit => Limit::perMinute(3)
            ->by('cms-contact:'.$request->ip()));
        RateLimiter::for('cms-newsletter', fn (Request $request): Limit => Limit::perMinute(3)
            ->by('cms-newsletter:'.$request->ip()));
        RateLimiter::for('order-tracking', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('order-tracking:'.$request->ip()));
        View::composer('components.admin.shell', \App\View\Composers\AdminShellComposer::class);
        View::composer('components.storefront.nav', function ($view): void {
            $view->with('storefrontShell', app(StorefrontShellSettingsService::class)->live());
        });
        View::composer('*', function ($view): void {
            $view->with('shopBrandName', app(ShopBrand::class)->name());
        });
    }
}
