<?php

namespace Tests\Feature;

use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\PageDocumentHasher;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\StorefrontCurrencyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StorefrontCanonicalHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_header_renders_on_every_customer_visible_public_route(): void
    {
        $product = $this->activeProduct();
        $this->activeHeaderMenu();
        $order = $this->successfulOrder($product->variants()->firstOrFail());
        $page = $this->publishedCmsPage();

        $responses = [
            'home' => $this->get(route('storefront.home')),
            'shop' => $this->get(route('storefront.index')),
            'product' => $this->get(route('storefront.products.show', ['handle' => $product->handle])),
            'cart' => $this->withSession(['cart' => [$product->variants()->firstOrFail()->id => 1]])->get(route('cart.show')),
            'checkout' => $this->withSession(['cart' => [$product->variants()->firstOrFail()->id => 1]])->get(route('checkout.create')),
            'success' => $this->withSession(['last_order_id' => $order->id])->get(route('checkout.success')),
            'customer login' => $this->get(route('customer.login')),
            'CMS page' => $this->get(route('storefront.pages.show', $page->slug)),
        ];

        foreach ($responses as $surface => $response) {
            $response->assertOk();
            $this->assertCanonicalHeader($response, $surface);
        }
    }

    public function test_header_uses_configured_navigation_cart_and_currency_state_without_a_featured_fallback(): void
    {
        $product = $this->activeProduct();
        $this->activeHeaderMenu();
        StoreSetting::query()->create(['key' => 'currencies', 'value' => ['data' => [
            ['id' => 'MYR', 'code' => 'MYR', 'title' => 'Malaysian Ringgit', 'symbolLeft' => 'RM ', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 1, 'isDefault' => true, 'autoUpdate' => false, 'lastUpdated' => '', 'isEnabled' => true],
            ['id' => 'USD', 'code' => 'USD', 'title' => 'US Dollar', 'symbolLeft' => '$', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.2245, 'isDefault' => false, 'autoUpdate' => false, 'lastUpdated' => '', 'isEnabled' => true],
            ['id' => 'GBP', 'code' => 'GBP', 'title' => 'British Pound Sterling', 'symbolLeft' => '£', 'symbolRight' => '', 'decimalPlaces' => 2, 'exchangeRate' => 0.178, 'isDefault' => false, 'autoUpdate' => false, 'lastUpdated' => '', 'isEnabled' => false],
        ]]]);

        $response = $this->withSession([
            'cart' => [$product->variants()->firstOrFail()->id => 3],
            StorefrontCurrencyContext::SESSION_KEY => 'USD',
        ])->get(route('storefront.index'));

        $response->assertOk()
            ->assertSee('/shop?nav=root', false)
            ->assertSee('\/shop?nav=column', false)
            ->assertSee('\/products\/canonical-header-desk?nav=leaf', false)
            ->assertSee('data-cart-count-badge>3</span>', false)
            ->assertSee('data-atelier-currency-default="USD"', false)
            ->assertSee('value="MYR"', false)
            ->assertSee('value="USD" selected', false)
            ->assertDontSee('value="GBP"', false)
            ->assertDontSee('30-Day Trial', false);
    }

    public function test_member_header_uses_the_customer_guard_identity_and_account_destinations(): void
    {
        $customer = $this->customer('Canonical Header Member', 'member@example.test');
        $this->activeHeaderMenu();

        $this->actingAs($customer->user, 'customer')
            ->get(route('storefront.index'))
            ->assertOk()
            ->assertSee($customer->user->name, false)
            ->assertSee($customer->user->email, false)
            ->assertSee('My Orders & Dispatch (我的订单与物流)', false)
            ->assertSee('Account & Addresses (个人资料与地址)', false)
            ->assertSee(route('customer.account').'#purchase_history', false)
            ->assertSee(route('customer.account').'#profile', false)
            ->assertSee(route('customer.logout'), false);
    }

    public function test_member_guest_header_uses_real_auth_targets_without_fixture_member_state(): void
    {
        $customer = $this->customer('Canonical Header Member', 'member@example.test');
        $this->activeHeaderMenu();

        $response = $this->get(route('storefront.index'));

        $response->assertOk();
        $this->assertCanonicalHeader($response);
        $response->assertSee(route('customer.login'), false)
            ->assertSee(route('customer.register'), false)
            ->assertSee(route('customer.password.request'), false)
            ->assertDontSee($customer->user->name, false)
            ->assertDontSee($customer->user->email, false)
            ->assertDontSee('1250 PTS', false)
            ->assertDontSee('Atelier Member Studio', false)
            ->assertDontSee('Sign in to access your orders and account.', false)
            ->assertDontSee('Sign in to access your account', false);
    }

    public function test_each_customer_auth_screen_uses_the_canonical_header_and_footer(): void
    {
        $this->activeHeaderMenu();

        foreach ([
            'login' => route('customer.login'),
            'register' => route('customer.register'),
            'forgot password' => route('customer.password.request'),
        ] as $surface => $route) {
            $response = $this->get($route)->assertOk();

            $this->assertCanonicalHeader($response, $surface);
            $response->assertSee('© 2026 ATELIER Design Studio Inc. All rights reserved.', false);
        }
    }

    /**
     * A missing Account-source header structure would remove these real storefront controls and visible source copy.
     */
    private function assertCanonicalHeader(TestResponse $response, string $surface = 'storefront response'): void
    {
        $response->assertDontSee('COMPLIMENTARY SHIPPING', false)
            ->assertDontSee('Handcrafted Studio Essentials for Intentional Workspaces', false)
            ->assertSee('data-atelier-theme-toggle', false)
            ->assertSee('data-atelier-currency', false)
            ->assertSee('data-cart-drawer-open', false)
            ->assertSee('Explore Collections & Categories', false)
            ->assertSee('x-data="{ searchOpen: false, userDropdownOpen: false, mobileMenuOpen: false, activeMenuId: null, mobileExpandedLayer1: null, mobileExpandedLayer2: null }"', false)
            ->assertSee('@focusout="if (! $el.contains($event.relatedTarget)) activeMenuId = null"', false)
            ->assertDontSee('data-mega-nav-item', false)
            ->assertDontSee('data-mega-nav-panel', false)
            ->assertDontSee('data-mobile-mega-', false);

        self::assertSame(1, substr_count($response->getContent(), 'data-mega-nav'), "{$surface} must render exactly one canonical shell header.");
    }

    private function activeProduct(): Product
    {
        $product = Product::query()->create([
            'handle' => 'canonical-header-desk',
            'title' => 'Canonical Header Desk',
            'base_price' => 24900,
            'status' => 'active',
        ]);

        $product->variants()->create([
            'sku' => 'CANONICAL-HEADER-DESK',
            'title' => 'Studio edition',
            'price' => 24900,
            'stock' => 4,
            'enabled' => true,
        ]);

        return $product;
    }

    private function customer(string $name, string $email): Customer
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'is_admin' => false,
        ]);

        return Customer::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => 'Canonical',
            'last_name' => 'Member',
        ]);
    }

    private function activeHeaderMenu(): void
    {
        $header = Menu::query()->create(['key' => 'header', 'name' => 'Canonical Header']);
        $root = MenuItem::query()->create([
            'menu_id' => $header->id,
            'label' => 'Shop',
            'url' => '/shop?nav=root',
            'position' => 1,
            'is_active' => true,
        ]);
        $column = MenuItem::query()->create([
            'menu_id' => $header->id,
            'parent_id' => $root->id,
            'label' => 'Workspace',
            'url' => '/shop?nav=column',
            'position' => 1,
            'is_active' => true,
        ]);
        MenuItem::query()->create([
            'menu_id' => $header->id,
            'parent_id' => $column->id,
            'label' => 'Desks',
            'url' => '/products/canonical-header-desk?nav=leaf',
            'position' => 1,
            'is_active' => true,
        ]);

    }

    private function successfulOrder(ProductVariant $variant): Order
    {
        $order = Order::query()->create([
            'number' => 'JW-CANONICAL-HEADER-001',
            'email' => 'order@example.test',
            'currency' => 'MYR',
            'status' => 'pending_payment',
            'subtotal' => 24900,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 24900,
        ]);
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'title' => 'Canonical Header Desk',
            'variant_title' => $variant->title,
            'unit_price' => 24900,
            'quantity' => 1,
            'line_total' => 24900,
        ]);

        return $order;
    }

    private function publishedCmsPage(): Page
    {
        $page = Page::query()->create([
            'title' => 'Canonical header CMS page',
            'slug' => 'canonical-header-cms-page',
            'status' => 'published',
            'is_published' => true,
        ]);
        $document = [
            'title' => 'Canonical header CMS page',
            'slug' => $page->slug,
            'description' => 'A public CMS shell fixture.',
            'template' => 'landing',
            'heroImage' => null,
            'seoTitle' => 'Canonical header CMS page',
            'seoDescription' => 'A public CMS shell fixture.',
            'sections' => [[
                'id' => 'canonical-header-copy',
                'type' => 'rich_text',
                'title' => 'Canonical header public copy',
                'content' => 'Canonical header public copy',
                'isVisible' => true,
            ]],
        ];
        $page->documents()->create([
            'stage' => 'published',
            'slug' => $page->slug,
            'schema_version' => PageDocumentContract::VERSION,
            'lock_version' => 1,
            'content_hash' => PageDocumentHasher::hash($document),
            'document' => $document,
        ]);

        return $page;
    }
}
