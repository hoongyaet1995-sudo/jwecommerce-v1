<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Page;
use App\Models\PageDocument;
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

    public function test_header_menu_defaults_highlight_state_when_the_client_does_not_send_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->putJson('/admin/api/menus/header', ['items' => [[
            'id' => 'no-highlight-state',
            'title' => 'Contact Us',
            'url' => '/contact',
            'linkType' => 'custom_url',
            'displayStyle' => 'simple_link',
            'isActive' => true,
            'sortOrder' => 1,
            'columns' => [],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.0.isHighlighted', false);

        $this->assertDatabaseHas('menu_items', [
            'label' => 'Contact Us',
            'is_highlighted' => false,
        ]);
    }

    public function test_menu_write_requires_admin_authentication(): void
    {
        $this->putJson('/admin/api/menus/header', ['items' => []])->assertUnauthorized();
    }

    public function test_internal_menu_targets_are_resolved_server_side_and_unavailable_targets_are_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $collection = Collection::query()->create(['title' => 'Featured', 'slug' => 'featured', 'status' => 'active', 'is_active' => true]);
        $page = Page::query()->create(['status' => 'published']);
        $page->documents()->create([
            'stage' => PageDocument::STAGE_PUBLISHED,
            'slug' => 'brand-story',
            'schema_version' => 1,
            'lock_version' => 1,
            'content_hash' => hash('sha256', '{}'),
            'document' => ['title' => 'Brand Story', 'slug' => 'brand-story', 'sections' => []],
        ]);
        $inactiveCategory = Category::query()->create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $header = [[
            'id' => 'header-target', 'title' => 'Audio', 'url' => '/forged', 'linkType' => 'category', 'targetId' => (string) $category->id,
            'displayStyle' => 'simple_link', 'isHighlighted' => false, 'isActive' => true, 'sortOrder' => 1,
            'columns' => [[
                'id' => 'column-target', 'title' => 'Featured', 'url' => '/forged-column', 'linkType' => 'collection', 'targetId' => (string) $collection->id,
                'isActive' => true, 'sortOrder' => 1,
                'items' => [[
                    'id' => 'leaf-target', 'title' => 'Brand Story', 'url' => '/forged-leaf', 'linkType' => 'page', 'targetId' => (string) $page->id,
                    'isActive' => true, 'sortOrder' => 1,
                ]],
            ]],
        ]];

        $this->actingAs($admin)->putJson('/admin/api/menus/header', ['items' => $header])
            ->assertOk()
            ->assertJsonPath('data.0.url', '/categories/audio')
            ->assertJsonPath('data.0.columns.0.url', '/collections/featured')
            ->assertJsonPath('data.0.columns.0.items.0.url', '/pages/brand-story');

        $header[0]['targetId'] = (string) $inactiveCategory->id;
        $this->actingAs($admin)->putJson('/admin/api/menus/header', ['items' => $header])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.targetId');
    }

    public function test_menu_badge_palette_color_persists_and_storefront_uses_accessible_contrast(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $header = [[
            'id' => 'night-link', 'title' => 'Night collection', 'url' => '/shop', 'linkType' => 'custom_url',
            'badge' => 'Night', 'badgeColor' => '#111827', 'displayStyle' => 'simple_link',
            'isHighlighted' => false, 'isActive' => true, 'sortOrder' => 1, 'columns' => [],
        ]];

        $this->actingAs($admin)->putJson('/admin/api/menus/header', ['items' => $header])
            ->assertOk()
            ->assertJsonPath('data.0.badgeColor', '#111827');

        $this->assertDatabaseHas('menu_items', ['label' => 'Night collection', 'badge_color' => '#111827']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Night')
            ->assertSee('--menu-badge-bg: #111827; --menu-badge-fg: #ffffff', false);
    }

    public function test_menu_badge_palette_uses_dark_text_for_a_bright_saved_color(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $header = [[
            'id' => 'bright-link',
            'title' => 'Amber collection',
            'url' => '/shop',
            'linkType' => 'custom_url',
            'badge' => 'New',
            'badgeColor' => '#f59e0b',
            'displayStyle' => 'simple_link',
            'isHighlighted' => false,
            'isActive' => true,
            'sortOrder' => 1,
            'columns' => [],
        ]];

        $this->actingAs($admin)
            ->putJson('/admin/api/menus/header', ['items' => $header])
            ->assertOk()
            ->assertJsonPath('data.0.badgeColor', '#f59e0b');

        $this->get('/')
            ->assertOk()
            ->assertSee('New')
            ->assertSee('--menu-badge-bg: #f59e0b; --menu-badge-fg: #111827', false);
    }

    public function test_menu_rejects_badge_colors_outside_the_supported_palette(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $header = [[
            'id' => 'bad-color', 'title' => 'Bad color', 'url' => '/shop', 'linkType' => 'custom_url',
            'badge' => 'Unsafe', 'badgeColor' => '#123456', 'displayStyle' => 'simple_link',
            'isHighlighted' => false, 'isActive' => true, 'sortOrder' => 1, 'columns' => [],
        ]];

        $this->actingAs($admin)->putJson('/admin/api/menus/header', ['items' => $header])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.badgeColor');
    }
}
