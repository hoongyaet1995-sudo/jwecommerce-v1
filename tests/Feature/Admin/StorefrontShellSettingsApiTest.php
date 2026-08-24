<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\StorefrontShellSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class StorefrontShellSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_app_bootstrap_exposes_the_protected_shell_settings_url(): void
    {
        $response = $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk();

        preg_match('/window\.__JW_ADMIN__\s*=\s*(\{.*\});/sU', $response->getContent(), $matches);
        $bootstrap = json_decode($matches[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            route('admin.api.storefront-shell-settings.show'),
            $bootstrap['storefrontShellSettingsUrl'] ?? null,
        );
    }

    public function test_show_returns_the_default_live_shell_shape_when_nothing_is_saved(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/admin/api/storefront-shell-settings')
            ->assertOk()
            ->assertExactJson($this->defaultPayload());
    }

    public function test_admin_can_save_and_reload_structured_announcement_settings(): void
    {
        $payload = [
            'enabled' => true,
            'layout' => 'atelier',
            'theme' => 'midnight-obsidian',
            'customPalette' => [
                'background' => '#111827',
                'text' => '#f9fafb',
                'accent' => '#f59e0b',
            ],
            'zones' => [
                'left' => [
                    'enabled' => true,
                    'text' => 'Official atelier bulletin',
                    'badge' => 'Official',
                    'linkUrl' => '/collections/atelier-2026',
                ],
                'center' => [
                    'enabled' => true,
                    'text' => 'Free shipping on capsule drops',
                    'couponCode' => 'SHIPFREE',
                    'linkUrl' => 'https://shop.jwstudio.com/promotions/shipping',
                ],
                'right' => [
                    'enabled' => true,
                    'helperText' => 'Track an order or message the concierge.',
                    'trackingEnabled' => true,
                    'trackingLabel' => 'Track order',
                    'trackingUrl' => '/account',
                    'whatsappEnabled' => true,
                    'whatsappLabel' => 'WhatsApp us',
                    'whatsappUrl' => 'https://wa.me/60123456789',
                ],
            ],
        ];

        $this->actingAs($this->admin())
            ->putJson('/admin/api/storefront-shell-settings', $payload)
            ->assertOk()
            ->assertJsonPath('layout', 'atelier')
            ->assertJsonPath('zones.left.badge', 'Official')
            ->assertJsonPath('zones.center.couponCode', 'SHIPFREE')
            ->assertJsonPath('zones.right.whatsappUrl', 'https://wa.me/60123456789');

        $this->assertDatabaseHas('storefront_shell_settings', ['key' => 'global']);

        $this->actingAs($this->admin())
            ->getJson('/admin/api/storefront-shell-settings')
            ->assertOk()
            ->assertExactJson($payload);

        $this->assertSame(
            $payload,
            app(StorefrontShellSettingsService::class)->live(),
        );
    }

    public function test_non_atelier_layout_cannot_be_published(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/admin/api/storefront-shell-settings', [
                'layout' => 'floating-pill',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('layout');
    }

    public function test_shell_settings_routes_require_admin_authentication(): void
    {
        $this->getJson('/admin/api/storefront-shell-settings')->assertUnauthorized();
        $this->putJson('/admin/api/storefront-shell-settings', $this->defaultPayload())->assertUnauthorized();

        $staff = User::factory()->create(['is_admin' => false]);

        $this->actingAs($staff)
            ->getJson('/admin/api/storefront-shell-settings')
            ->assertUnauthorized();

        $this->actingAs($staff)
            ->putJson('/admin/api/storefront-shell-settings', $this->defaultPayload())
            ->assertUnauthorized();
    }

    public function test_shell_settings_accept_supported_links_and_reject_unsafe_urls(): void
    {
        $payload = $this->defaultPayload();
        $payload['enabled'] = true;
        $payload['zones']['left'] = [
            'enabled' => true,
            'text' => 'Call the atelier',
            'badge' => 'Direct',
            'linkUrl' => 'tel:+60312345678',
        ];
        $payload['zones']['center'] = [
            'enabled' => true,
            'text' => 'Shipping update',
            'couponCode' => 'CALLSTUDIO',
            'linkUrl' => 'https://shop.jwstudio.com/promo',
        ];
        $payload['zones']['right'] = [
            'enabled' => true,
            'helperText' => 'Utility actions',
            'trackingEnabled' => true,
            'trackingLabel' => 'Track',
            'trackingUrl' => '/account',
            'whatsappEnabled' => true,
            'whatsappLabel' => 'WhatsApp',
            'whatsappUrl' => 'https://api.whatsapp.com/send?phone=60123456789',
        ];

        $this->actingAs($this->admin())
            ->putJson('/admin/api/storefront-shell-settings', $payload)
            ->assertOk()
            ->assertJsonPath('zones.left.linkUrl', 'tel:+60312345678')
            ->assertJsonPath('zones.right.whatsappUrl', 'https://api.whatsapp.com/send?phone=60123456789');

        $payload['zones']['left']['linkUrl'] = 'http://localhost/private';

        $this->actingAs($this->admin())
            ->putJson('/admin/api/storefront-shell-settings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('zones.left.linkUrl');
    }

    public function test_enabled_zones_require_live_copy_and_utility_urls(): void
    {
        $cases = [
            'left text' => function (array $payload): array {
                $payload['zones']['left']['enabled'] = true;
                $payload['zones']['left']['text'] = '';

                return [$payload, 'zones.left.text'];
            },
            'center text' => function (array $payload): array {
                $payload['zones']['center']['enabled'] = true;
                $payload['zones']['center']['text'] = '';

                return [$payload, 'zones.center.text'];
            },
            'tracking url' => function (array $payload): array {
                $payload['zones']['right']['trackingEnabled'] = true;
                $payload['zones']['right']['trackingUrl'] = null;

                return [$payload, 'zones.right.trackingUrl'];
            },
            'whatsapp url' => function (array $payload): array {
                $payload['zones']['right']['whatsappEnabled'] = true;
                $payload['zones']['right']['whatsappUrl'] = null;

                return [$payload, 'zones.right.whatsappUrl'];
            },
        ];

        foreach ($cases as $label => $mutate) {
            [$payload, $field] = $mutate($this->defaultPayload());

            $this->actingAs($this->admin())
                ->putJson('/admin/api/storefront-shell-settings', $payload)
                ->assertUnprocessable($label)
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_api_save_rebuilds_the_canonical_global_singleton_when_rogue_rows_exist(): void
    {
        DB::table('storefront_shell_settings')->insert([
            'key' => 'rogue',
            'payload' => json_encode(['enabled' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = $this->defaultPayload();
        $payload['enabled'] = true;
        $payload['zones']['left']['enabled'] = true;
        $payload['zones']['left']['text'] = 'Canonical only';

        $this->actingAs($this->admin())
            ->putJson('/admin/api/storefront-shell-settings', $payload)
            ->assertOk()
            ->assertJsonPath('zones.left.text', 'Canonical only');

        $this->assertDatabaseCount('storefront_shell_settings', 1);
        $this->assertDatabaseHas('storefront_shell_settings', ['key' => 'global']);
        $this->assertDatabaseMissing('storefront_shell_settings', ['key' => 'rogue']);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
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
