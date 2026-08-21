<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_header_and_footer_documents_persist_and_render_on_storefront(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $header = [[
            'id' => 'new-header', 'title' => 'Studio Systems', 'url' => '/shop',
            'linkType' => 'custom_url', 'badge' => 'New', 'displayStyle' => 'megamenu',
            'isHighlighted' => false, 'isActive' => true, 'sortOrder' => 1,
            'columns' => [[
                'id' => 'new-column', 'title' => 'Reference Audio', 'url' => '/shop?category=audio',
                'linkType' => 'category', 'description' => 'Studio monitoring', 'isActive' => true, 'sortOrder' => 1,
                'items' => [[
                    'id' => 'new-link', 'title' => 'Monitor Headphones', 'url' => '/shop?category=audio',
                    'linkType' => 'category', 'isActive' => true, 'sortOrder' => 1,
                ]],
            ]],
        ]];
        $footer = [[
            'id' => 'new-footer', 'sectionTitle' => 'Customer Assistance', 'isActive' => true, 'sortOrder' => 1,
            'links' => [[
                'id' => 'new-footer-link', 'title' => 'Track Your Order', 'url' => '/account',
                'linkType' => 'page', 'isActive' => true, 'sortOrder' => 1,
            ]],
        ]];

        $this->actingAs($admin)->putJson('/admin/api/menus/header', ['items' => $header])
            ->assertOk()->assertJsonPath('data.0.title', 'Studio Systems');
        $this->actingAs($admin)->putJson('/admin/api/menus/footer', ['items' => $footer])
            ->assertOk()->assertJsonPath('data.0.sectionTitle', 'Customer Assistance');

        $this->assertDatabaseHas('menu_items', ['label' => 'Studio Systems', 'position' => 1]);
        $this->assertDatabaseHas('menu_items', ['label' => 'Track Your Order', 'position' => 1]);

        $this->actingAs($admin)->getJson('/admin/api/menus')->assertOk()
            ->assertJsonPath('headerMenus.0.columns.0.items.0.title', 'Monitor Headphones')
            ->assertJsonPath('footerMenus.0.links.0.title', 'Track Your Order');

        $this->get('/')->assertOk()->assertSee('Studio Systems')->assertSee('Monitor Headphones')
            ->assertSee('Customer Assistance')->assertSee('Track Your Order');
    }

    public function test_inactive_menu_items_do_not_render_publicly(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->putJson('/admin/api/menus/header', ['items' => [[
            'id' => 'hidden', 'title' => 'Hidden Link', 'url' => '/shop', 'linkType' => 'custom_url',
            'displayStyle' => 'simple_link', 'isHighlighted' => false, 'isActive' => false, 'sortOrder' => 1, 'columns' => [],
        ]]])->assertOk();

        $this->get('/')->assertOk()->assertDontSee('Hidden Link');
    }

    public function test_menu_write_requires_admin_authentication(): void
    {
        $this->putJson('/admin/api/menus/header', ['items' => []])->assertUnauthorized();
    }
}
