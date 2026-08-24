<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTaxonomySlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_and_collection_allocate_unique_slugs_when_the_form_leaves_them_blank(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $firstCategory = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', $this->categoryPayload('Studio Audio'))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'studio-audio')
            ->json('data');
        $secondCategory = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', $this->categoryPayload('Studio Audio'))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'studio-audio-2')
            ->json('data');

        $firstCollection = $this->actingAs($admin)->postJson('/admin/api/catalog/collections', $this->collectionPayload('Summer Sales'))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'summer-sales')
            ->json('data');
        $secondCollection = $this->actingAs($admin)->postJson('/admin/api/catalog/collections', $this->collectionPayload('Summer Sales'))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'summer-sales-2')
            ->json('data');

        $this->assertDatabaseHas('categories', ['id' => $firstCategory['id'], 'slug' => 'studio-audio']);
        $this->assertDatabaseHas('categories', ['id' => $secondCategory['id'], 'slug' => 'studio-audio-2']);
        $this->assertDatabaseHas('collections', ['id' => $firstCollection['id'], 'slug' => 'summer-sales']);
        $this->assertDatabaseHas('collections', ['id' => $secondCollection['id'], 'slug' => 'summer-sales-2']);
    }

    public function test_category_and_collection_reject_a_manually_entered_slug_that_is_already_taken(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        Collection::query()->create(['title' => 'Summer Sales', 'slug' => 'summer-sales', 'type' => 'manual', 'status' => 'active']);

        $this->actingAs($admin)->postJson('/admin/api/catalog/categories', $this->categoryPayload('Second Audio', 'audio'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
        $this->actingAs($admin)->postJson('/admin/api/catalog/collections', $this->collectionPayload('Second Summer', 'summer-sales'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_blank_slug_does_not_change_an_existing_category_or_collection_url(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $collection = Collection::query()->create(['title' => 'Summer Sales', 'slug' => 'summer-sales', 'type' => 'manual', 'status' => 'active']);

        $this->actingAs($admin)->patchJson('/admin/api/catalog/categories/'.$category->id, $this->categoryPayload('Audio Updated'))
            ->assertOk()
            ->assertJsonPath('data.slug', 'audio');
        $this->actingAs($admin)->patchJson('/admin/api/catalog/collections/'.$collection->id, $this->collectionPayload('Summer Sales Updated'))
            ->assertOk()
            ->assertJsonPath('data.slug', 'summer-sales');
    }

    /** @return array<string, mixed> */
    private function categoryPayload(string $name, ?string $slug = null): array
    {
        return ['name' => $name, 'slug' => $slug, 'status' => 'active', 'sortOrder' => 1];
    }

    /** @return array<string, mixed> */
    private function collectionPayload(string $title, ?string $slug = null): array
    {
        return ['title' => $title, 'slug' => $slug, 'type' => 'manual', 'manualProductIds' => [], 'status' => 'active'];
    }
}
