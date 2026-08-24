<?php

namespace Tests\Feature;

use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\PageDocumentHasher;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StorefrontShellSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StorefrontAnnouncementBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_enabled_announcement_is_visible_on_storefront(): void
    {
        $this->saveLiveShell([
            'enabled' => true,
            'zones' => [
                'left' => [
                    'enabled' => true,
                    'text' => 'Official atelier bulletin',
                    'badge' => 'Official',
                    'linkUrl' => '/collections/atelier-2026',
                ],
                'center' => [
                    'enabled' => true,
                    'text' => 'Complimentary shipping on capsule drops',
                    'couponCode' => 'WELCOME10',
                    'linkUrl' => '/promotions/welcome',
                ],
                'right' => [
                    'enabled' => true,
                    'helperText' => 'Worldwide express dispatch',
                    'trackingEnabled' => true,
                    'trackingLabel' => 'Track order',
                    'trackingUrl' => '/account/orders',
                    'whatsappEnabled' => true,
                    'whatsappLabel' => 'WhatsApp us',
                    'whatsappUrl' => 'https://wa.me/60123456789',
                ],
            ],
        ]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('Official atelier bulletin')
            ->assertSee('Official')
            ->assertSee('/collections/atelier-2026', false)
            ->assertSee('Complimentary shipping on capsule drops')
            ->assertSee('WELCOME10')
            ->assertSee('/promotions/welcome', false)
            ->assertSee('Worldwide express dispatch')
            ->assertSee('Track order')
            ->assertSee('/account/orders', false)
            ->assertSee('WhatsApp us')
            ->assertSee('https://wa.me/60123456789', false);
    }

    public function test_saved_announcement_theme_and_custom_palette_are_consumed_by_the_shared_storefront_header(): void
    {
        foreach ([
            'midnight-obsidian' => ['#0f172a', '#f8fafc'],
            'aurora-indigo' => ['#312e81', '#e0e7ff'],
            'emerald-logistics' => ['#064e3b', '#a7f3d0'],
            'amber-flash' => ['#78350f', '#fde68a'],
            'crimson-campaign' => ['#881337', '#ffe4e6'],
        ] as $theme => [$background, $text]) {
            $this->saveLiveShell([
                'enabled' => true,
                'theme' => $theme,
                'zones' => [
                    'center' => [
                        'enabled' => true,
                        'text' => 'Preset theme acceptance',
                        'couponCode' => 'PRESET',
                    ],
                ],
            ]);

            $this->get('/shop')
                ->assertOk()
                ->assertSee(sprintf('data-storefront-announcement-theme="%s"', $theme), false)
                ->assertSee(sprintf('--storefront-announcement-background: %s;', $background), false)
                ->assertSee(sprintf('--storefront-announcement-text: %s;', $text), false);
        }

        $this->saveLiveShell([
            'enabled' => true,
            'theme' => 'custom',
            'customPalette' => [
                'background' => '#123456',
                'text' => '#fedcba',
                'accent' => null,
            ],
            'zones' => [
                'center' => [
                    'enabled' => true,
                    'text' => 'Custom theme acceptance',
                    'couponCode' => 'CUSTOM',
                ],
            ],
        ]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('data-storefront-announcement-theme="custom"', false)
            ->assertSee('--storefront-announcement-background: #123456;', false)
            ->assertSee('--storefront-announcement-text: #fedcba;', false);
    }

    public function test_disabled_or_missing_zone_is_not_rendered(): void
    {
        $this->saveLiveShell([
            'enabled' => true,
            'zones' => [
                'left' => [
                    'enabled' => true,
                    'text' => 'Official atelier bulletin',
                    'badge' => 'Official',
                    'linkUrl' => null,
                ],
                'center' => [
                    'enabled' => false,
                    'text' => 'Complimentary shipping on capsule drops',
                    'couponCode' => 'WELCOME10',
                    'linkUrl' => '/promotions/welcome',
                ],
                'right' => [
                    'enabled' => true,
                    'helperText' => 'Worldwide express dispatch',
                    'trackingEnabled' => false,
                    'trackingLabel' => 'Track order',
                    'trackingUrl' => '/account/orders',
                    'whatsappEnabled' => false,
                    'whatsappLabel' => 'WhatsApp us',
                    'whatsappUrl' => 'https://wa.me/60123456789',
                ],
            ],
        ]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('Official atelier bulletin')
            ->assertSee('Official')
            ->assertDontSee('Complimentary shipping on capsule drops')
            ->assertDontSee('WELCOME10')
            ->assertDontSee('/promotions/welcome', false)
            ->assertSee('Worldwide express dispatch')
            ->assertDontSee('Track order')
            ->assertDontSee('/account/orders', false)
            ->assertDontSee('WhatsApp us')
            ->assertDontSee('https://wa.me/60123456789', false);

        $this->saveLiveShell([
            'enabled' => false,
            'zones' => [
                'left' => [
                    'enabled' => true,
                    'text' => 'Official atelier bulletin',
                    'badge' => 'Official',
                    'linkUrl' => '/collections/atelier-2026',
                ],
            ],
        ]);

        $this->get('/shop')
            ->assertOk()
            ->assertDontSee('Official atelier bulletin')
            ->assertDontSee('Official')
            ->assertDontSee('/collections/atelier-2026', false);
    }

    public function test_enabled_announcement_renders_across_all_shared_header_public_routes(): void
    {
        $product = $this->activeProduct();
        $this->activeHeaderMenu();
        $order = $this->successfulOrder($product->variants()->firstOrFail());
        $page = $this->publishedCmsPage();

        $this->saveLiveShell([
            'enabled' => true,
            'zones' => [
                'left' => [
                    'enabled' => true,
                    'text' => 'Official atelier bulletin',
                    'badge' => 'Official',
                    'linkUrl' => '/collections/atelier-2026',
                ],
                'center' => [
                    'enabled' => true,
                    'text' => 'Complimentary shipping on capsule drops',
                    'couponCode' => 'WELCOME10',
                    'linkUrl' => '/promotions/welcome',
                ],
                'right' => [
                    'enabled' => true,
                    'helperText' => 'Worldwide express dispatch',
                    'trackingEnabled' => true,
                    'trackingLabel' => 'Track order',
                    'trackingUrl' => '/account/orders',
                    'whatsappEnabled' => true,
                    'whatsappLabel' => 'WhatsApp us',
                    'whatsappUrl' => 'https://wa.me/60123456789',
                ],
            ],
        ]);

        $responses = $this->sharedHeaderPublicRouteResponses($product, $order, $page);

        foreach ($responses as $surface => $response) {
            $response->assertOk($surface)
                ->assertSee('Official atelier bulletin')
                ->assertSee('Official')
                ->assertSee('/collections/atelier-2026', false)
                ->assertSee('Complimentary shipping on capsule drops')
                ->assertSee('WELCOME10')
                ->assertSee('/promotions/welcome', false)
                ->assertSee('Worldwide express dispatch')
                ->assertSee('Track order')
                ->assertSee('/account/orders', false)
                ->assertSee('WhatsApp us')
                ->assertSee('https://wa.me/60123456789', false);
        }
    }

    /**
     * @return array<string, \Illuminate\Testing\TestResponse>
     */
    private function sharedHeaderPublicRouteResponses(Product $product, Order $order, Page $page): array
    {
        $variant = $product->variants()->firstOrFail();

        return [
            'home' => $this->get(route('storefront.home')),
            'shop' => $this->get(route('storefront.index')),
            'product' => $this->get(route('storefront.products.show', ['handle' => $product->handle])),
            'cart' => $this->withSession(['cart' => [$variant->id => 1]])->get(route('cart.show')),
            'checkout' => $this->withSession(['cart' => [$variant->id => 1]])->get(route('checkout.create')),
            'success' => $this->withSession(['last_order_id' => $order->id])->get(route('checkout.success')),
            'customer login' => $this->get(route('customer.login')),
            'CMS page' => $this->get(route('storefront.pages.show', $page->slug)),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function saveLiveShell(array $overrides): void
    {
        $payload = array_replace_recursive($this->defaultPayload(), $overrides);

        app(StorefrontShellSettingsService::class)->save($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPayload(): array
    {
        return [
            'enabled' => false,
            'layout' => 'atelier',
            'theme' => 'midnight-obsidian',
            'customPalette' => [
                'background' => null,
                'text' => null,
                'accent' => null,
            ],
            'zones' => [
                'left' => [
                    'enabled' => false,
                    'text' => '',
                    'badge' => '',
                    'linkUrl' => null,
                ],
                'center' => [
                    'enabled' => false,
                    'text' => '',
                    'couponCode' => '',
                    'linkUrl' => null,
                ],
                'right' => [
                    'enabled' => false,
                    'helperText' => '',
                    'trackingEnabled' => false,
                    'trackingLabel' => '',
                    'trackingUrl' => null,
                    'whatsappEnabled' => false,
                    'whatsappLabel' => '',
                    'whatsappUrl' => null,
                ],
            ],
        ];
    }

    private function activeProduct(): Product
    {
        $product = Product::query()->create([
            'handle' => 'announcement-bar-desk',
            'title' => 'Announcement Bar Desk',
            'base_price' => 24900,
            'status' => 'active',
        ]);

        $product->variants()->create([
            'sku' => 'ANNOUNCEMENT-BAR-DESK',
            'title' => 'Studio edition',
            'price' => 24900,
            'stock' => 4,
            'enabled' => true,
        ]);

        return $product;
    }

    private function activeHeaderMenu(): void
    {
        $header = Menu::query()->create(['key' => 'header', 'name' => 'Announcement Header']);
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
            'url' => '/products/announcement-bar-desk?nav=leaf',
            'position' => 1,
            'is_active' => true,
        ]);
    }

    private function successfulOrder(ProductVariant $variant): Order
    {
        $order = Order::query()->create([
            'number' => 'JW-ANNOUNCEMENT-BAR-001',
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
            'title' => 'Announcement Bar Desk',
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
            'title' => 'Announcement bar CMS page',
            'slug' => 'announcement-bar-cms-page',
            'status' => 'published',
            'is_published' => true,
        ]);

        $document = [
            'title' => 'Announcement bar CMS page',
            'slug' => $page->slug,
            'description' => 'A public CMS shell fixture.',
            'template' => 'landing',
            'heroImage' => null,
            'seoTitle' => 'Announcement bar CMS page',
            'seoDescription' => 'A public CMS shell fixture.',
            'sections' => [[
                'id' => 'announcement-bar-copy',
                'type' => 'rich_text',
                'title' => 'Announcement bar public copy',
                'content' => 'Announcement bar public copy',
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
