<?php

namespace Tests\Feature;

use App\Models\AiConnection;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\SearchTask;
use App\Models\StoreSetting;
use App\Services\Search\SearchMetadata;
use Tests\TestCase;

class SearchProducerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    } // Isolated SQLite only; avoid unrelated legacy down-migration defects.

    private function enable(): void
    {
        StoreSetting::create(['key' => 'search_visibility', 'value' => ['mode' => 'auto', 'version' => 1, 'daily_limit' => 100, 'preview' => false]]);
        AiConnection::create(['provider' => 'openai', 'enabled' => true, 'search_enabled' => true, 'verified_at' => now(), 'text_model' => 'fixture', 'api_key' => 'fixture']);
    }

    public function test_catalog_saves_do_not_queue_ai_tasks(): void
    {
        $this->enable();
        $p = Product::create(['handle' => 'auto-product', 'title' => '20ft Container', 'subtitle' => 'Storage options.', 'base_price' => 100, 'status' => 'draft']);
        $category = Category::create(['slug' => 'auto-category', 'name' => 'Containers', 'description' => 'Storage options.', 'is_active' => true]);
        $collection = Collection::create(['slug' => 'auto-collection', 'title' => 'Container range', 'description' => 'Storage options.', 'is_active' => true, 'status' => 'active', 'type' => 'manual']);
        $this->assertSame(0, SearchTask::count());
        $p->refresh()->forceFill(['base_price' => 200])->save();
        $this->assertSame(0, SearchTask::count());
        $p->refresh()->forceFill(['title' => '40ft Container'])->save();
        app(\App\Services\Search\SearchProducer::class)->productContentSaved($p->id);
        $this->assertSame(0, SearchTask::count());
        $this->assertFalse(app(SearchMetadata::class)->public('product', $p->id));
    }

    public function test_category_old_url_redirects_before_route_model_binding_and_home_uses_root(): void
    {
        $category = Category::create(['slug' => 'previous-category', 'name' => 'Containers', 'is_active' => true]);
        $category->slug = 'current-category';
        $category->save();
        $this->get('/categories/previous-category')->assertStatus(301)->assertRedirect('/categories/current-category');
    }

    public function test_catalog_api_changes_generate_new_metadata_and_respect_field_locks(): void
    {
        $this->enable();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $payloads = [
            'category' => ['name' => 'Storage', 'slug' => 'storage', 'description' => 'Storage options.', 'status' => 'active', 'sortOrder' => 0],
            'collection' => ['title' => 'Storage', 'slug' => 'storage', 'description' => 'Storage options.', 'status' => 'active', 'type' => 'manual', 'manualProductIds' => []],
            'product' => ['title' => 'Storage', 'slug' => 'storage', 'summary' => 'Storage options.', 'status' => 'active', 'hasVariants' => false, 'basePrice' => 100, 'featured' => false, 'options' => [], 'tags' => [], 'collectionIds' => [], 'variants' => [['sku' => 'SEO-ONLY', 'title' => 'Default', 'price' => 100, 'enabled' => true, 'options' => []]]],
        ];
        $plural = ['category' => 'categories', 'collection' => 'collections', 'product' => 'products'];
        $ai = app(\App\Services\Search\SearchOptimization::class);
        $this->mock(\App\Services\AI\AiConnectionService::class)->shouldReceive('generateText')->times(6)->andReturn(json_encode(['title' => 'Storage options', 'description' => 'Storage options.', 'suggestedTopics' => []]));
        foreach ($payloads as $kind => $payload) {
            if ($kind === 'product') {
                $payload['variants'][0]['inventoryQuantity'] = 0;
            }
            $url = '/admin/api/catalog/'.$plural[$kind];
            $created = $this->postJson($url, $payload)->assertCreated()->json('data');
            $id = (int) $created['id'];
            $this->assertSame(0, SearchTask::where('kind', $kind)->where('entity_id', $id)->count());
            $ai->request($kind, $id, 'live', true);
            $task = SearchTask::where('kind', $kind)->where('entity_id', $id)->latest('id')->firstOrFail();
            $this->assertSame('applied', $ai->run($task)->status);
            $meta = app(SearchMetadata::class)->for($kind, $id);
            $this->putJson('/admin/api/search/'.$kind.'/'.$id, ['version' => $meta['version'], 'titleLocked' => true, 'descriptionLocked' => false, 'title' => 'My fixed title', 'description' => '', 'excluded' => false])->assertOk();
            $payload[$kind === 'category' ? 'name' : 'title'] = 'Updated Storage';
            if ($kind === 'product') {
                $payload['variants'][0]['id'] = $created['variants'][0]['id'];
                unset($payload['variants'][0]['inventoryQuantity']);
            }
            $this->patchJson($url.'/'.$id, $payload)->assertOk();
            $this->assertSame(1, SearchTask::where('kind', $kind)->where('entity_id', $id)->count());
            $ai->request($kind, $id, 'live', true);
            $next = SearchTask::where('kind', $kind)->where('entity_id', $id)->latest('id')->firstOrFail();
            $this->assertNotSame($task->id, $next->id);
            $this->assertSame('applied', $ai->run($next)->status);
            $resolved = app(SearchMetadata::class)->for($kind, $id);
            $this->assertSame('My fixed title', $resolved['rawTitle']);
            $this->assertSame('Storage options.', $resolved['description']);
            $this->get('/'.$plural[$kind].'/storage')->assertOk()->assertSee('My fixed title')->assertSee('Storage options.');
        }
    }
}
