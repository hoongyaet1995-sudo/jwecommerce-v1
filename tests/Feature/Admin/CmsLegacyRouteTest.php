<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ContentController;
use App\Models\Page;
use App\Models\PageDocument;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

final class CmsLegacyRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_non_api_page_mutation_shapes_are_retired_and_cannot_mutate_canonical_or_legacy_state(): void
    {
        $legacyRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route): bool {
                $uri = trim($route->uri(), '/');
                $isLegacyCmsPath = $uri === 'admin/pages'
                    || str_starts_with($uri, 'admin/pages/')
                    || $uri === 'admin/page-sections'
                    || str_starts_with($uri, 'admin/page-sections/');

                return $isLegacyCmsPath
                    && ! str_starts_with($uri, 'admin/api/')
                    && count(array_intersect($route->methods(), ['POST', 'PATCH', 'PUT', 'DELETE'])) > 0;
            })
            ->values();

        $this->assertCount(0, $legacyRoutes, 'A non-API CMS mutation route remains registered.');
        $this->assertFileDoesNotExist(resource_path('views/admin/pages.blade.php'));

        foreach ([
            'admin.pages.store',
            'admin.pages.update',
            'admin.pages.destroy',
            'admin.pages.sections.store',
            'admin.page-sections.update',
            'admin.page-sections.destroy',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Retired {$routeName} alias remains available.");
        }

        $reflection = new ReflectionClass(ContentController::class);
        foreach (['pages', 'storePage', 'updatePage', 'destroyPage', 'storeSection', 'updateSection', 'destroySection'] as $method) {
            $this->assertFalse($reflection->hasMethod($method), "Retired ContentController::{$method} entry point remains available.");
        }

        $page = Page::query()->create(['slug' => 'legacy-seed', 'title' => 'Legacy seed']);
        $section = PageSection::query()->create([
            'page_id' => $page->getKey(),
            'type' => 'hero_banner',
            'content' => ['title' => 'Seeded legacy section'],
            'position' => 0,
        ]);
        $state = $this->cmsState();
        $requests = [
            ['POST', '/admin/pages', ['slug' => 'legacy-created', 'title' => 'Legacy created']],
            ['PATCH', "/admin/pages/{$page->getKey()}", ['slug' => 'legacy-updated', 'title' => 'Legacy updated', 'is_published' => true]],
            ['PUT', "/admin/pages/{$page->getKey()}", ['slug' => 'legacy-updated', 'title' => 'Legacy updated', 'is_published' => true]],
            ['DELETE', "/admin/pages/{$page->getKey()}", []],
            ['POST', "/admin/pages/{$page->getKey()}/sections", ['type' => 'hero_banner', 'content' => ['title' => 'Legacy created section']]],
            ['PATCH', "/admin/page-sections/{$section->getKey()}", ['type' => 'hero_banner', 'position' => 1, 'content' => ['title' => 'Legacy updated section']]],
            ['PUT', "/admin/page-sections/{$section->getKey()}", ['type' => 'hero_banner', 'position' => 1, 'content' => ['title' => 'Legacy updated section']]],
            ['DELETE', "/admin/page-sections/{$section->getKey()}", []],
        ];

        foreach ($requests as [$method, $uri, $payload]) {
            $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
                ->call($method, $uri, $payload);

            $this->assertContains($response->getStatusCode(), [404, 405], "{$method} {$uri} must be retired.");
            $this->assertSame($state, $this->cmsState(), "{$method} {$uri} mutated CMS state.");
        }
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function cmsState(): array
    {
        return [
            'pages' => Page::query()->orderBy('id')->get()->map->getAttributes()->all(),
            'documents' => PageDocument::query()->orderBy('id')->get()->map->getAttributes()->all(),
            'sections' => PageSection::query()->orderBy('id')->get()->map->getAttributes()->all(),
        ];
    }
}
