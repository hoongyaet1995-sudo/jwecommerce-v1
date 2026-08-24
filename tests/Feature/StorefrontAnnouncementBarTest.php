<?php

namespace Tests\Feature;

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
}
