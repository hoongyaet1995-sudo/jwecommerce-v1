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
        $this->app->bind(\App\Services\Marketing\Research\ResearchProvider::class, \App\Services\Marketing\Research\TavilyResearchProvider::class);
        $this->app->bind(PaymentGateway::class, SimulatedPaymentGateway::class);
        $this->app->bind(StoreInventoryPublisher::class, MarketplaceStoreInventoryPublisher::class);
        $this->app->scoped(StorefrontCurrencyContext::class);
        $this->app->scoped(ShopBrand::class);
        $this->app->scoped(\App\Services\Marketing\MarketingClipSource::class);
        $this->app->scoped(\App\Services\Marketing\MarketingMediaReuse::class);
        $this->app->scoped(\App\Services\Marketing\MarketingMaterialRepair::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (\App\Services\Marketing\MarketingMediaSources::MODELS as $mediaSource) {
            $mediaSource::saved(fn ($source) => app(\App\Services\Marketing\MarketingMediaSources::class)->safeCapture($source));
        }
        foreach ([\App\Models\MarketingProject::class,\App\Models\MarketingScene::class,\App\Models\MarketingVideoFilm::class] as $selectionSource) {
            $selectionSource::saved(function($model) { if(\Illuminate\Support\Facades\Schema::hasTable('marketing_library_assets')) app(\App\Services\Marketing\MarketingMediaSelection::class)->project($model instanceof \App\Models\MarketingProject?$model->id:$model->project_id); });
        }
        \App\Models\MarketingPost::saved(function($post) { if(\Illuminate\Support\Facades\Schema::hasTable('marketing_library_assets')) app(\App\Services\Marketing\MarketingMediaSelection::class)->post($post); });
        \App\Models\Page::saved(function (): void { \Illuminate\Support\Facades\Cache::forget('search:sitemap'); });
        \App\Models\Page::deleted(function (): void { \Illuminate\Support\Facades\Cache::forget('search:sitemap'); });
        \App\Models\StoreSetting::saved(function (): void { \Illuminate\Support\Facades\Cache::forget('search:sitemap'); });
        \App\Models\ProductPdpContent::saved(fn ($content) => app(\App\Services\Search\SearchProducer::class)->productContentSaved((int)$content->product_id));
        \App\Models\SearchEntry::saved(function (): void { \Illuminate\Support\Facades\Cache::forget('search:sitemap'); });
        foreach (['product'=>\App\Models\Product::class,'category'=>\App\Models\Category::class,'collection'=>\App\Models\Collection::class] as $kind=>$class) {
            $class::deleted(function (): void { \Illuminate\Support\Facades\Cache::forget('search:sitemap'); });
            $class::saved(fn ($model) => app(\App\Services\Search\SearchProducer::class)->saved($kind,$model));
        }
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(fn ($user, $token) => route('customer.password.reset', ['token'=>$token,'email'=>$user->email]));
        RateLimiter::for('ai-provider-save', fn (Request $request): Limit => Limit::perMinute(20)
            ->by('ai-save:'.$request->user()?->id.':'.$request->route('provider')));
        RateLimiter::for('ai-provider-check', fn (Request $request): Limit => Limit::perMinute(6)
            ->by('ai-check:'.$request->user()?->id.':'.$request->route('provider')));
        Gate::policy(\App\Models\ProductReview::class, \App\Policies\ProductReviewPolicy::class);
        Blade::directive('productPrice', static fn (string $expression): string => "<?php echo e(app(\\App\\Services\\ProductPurchaseRules::class)->price({$expression})); ?>");
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
