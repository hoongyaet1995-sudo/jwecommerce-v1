<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceOrderPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_preview_requires_admin_and_is_never_available_in_production(): void
    {
        $url = '/admin/marketplace/orders/1/preview';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->app->detectEnvironment(fn () => 'production');
        $this->getJson($url)->assertNotFound();
        $this->app->detectEnvironment(fn () => 'local');
        $this->getJson($url)->assertNotFound(); // A nonexistent order is not a demo success.
    }
}
