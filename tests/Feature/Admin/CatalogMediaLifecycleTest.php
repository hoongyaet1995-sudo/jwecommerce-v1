<?php

namespace Tests\Feature\Admin;

use App\Domain\Catalog\CatalogMediaReferenceSynchronizer;
use App\Models\Category;
use App\Models\CmsMediaAsset;
use App\Models\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogMediaLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_catalog_asset_is_only_orphaned_after_its_last_catalog_reference_is_removed(): void
    {
        $asset = $this->asset();
        $category = Category::query()->create([
            'slug' => 'managed-media-category',
            'name' => 'Managed media category',
            'image_url' => $asset->url,
            'is_active' => true,
        ]);
        $collection = Collection::query()->create([
            'slug' => 'managed-media-collection',
            'title' => 'Managed media collection',
            'cover_image' => $asset->url,
            'is_active' => true,
        ]);

        DB::transaction(function () use ($category, $collection): void {
            $synchronizer = app(CatalogMediaReferenceSynchronizer::class);
            $synchronizer->syncCategory($category);
            $synchronizer->syncCollection($collection);
        });

        $this->assertDatabaseCount('catalog_media_references', 2);
        $this->assertNull($asset->fresh()->orphaned_at);
        $asset->forceFill(['orphaned_at' => now()->subDays(30)])->saveOrFail();
        $this->assertNotContains(
            $asset->getKey(),
            CmsMediaAsset::query()->cleanupEligible()->pluck('id')->all(),
        );

        DB::transaction(function () use ($category): void {
            app(CatalogMediaReferenceSynchronizer::class)->removeCategory($category);
        });

        $this->assertDatabaseCount('catalog_media_references', 1);
        $this->assertNull($asset->fresh()->orphaned_at);

        DB::transaction(function () use ($collection): void {
            app(CatalogMediaReferenceSynchronizer::class)->removeCollection($collection);
        });

        $this->assertDatabaseCount('catalog_media_references', 0);
        $this->assertNotNull($asset->fresh()->orphaned_at);
    }

    private function asset(): CmsMediaAsset
    {
        $id = (string) Str::uuid();
        $path = "cms/media/{$id}.jpg";

        return CmsMediaAsset::query()->create([
            'id' => $id,
            'disk' => 'public',
            'path' => $path,
            'url' => '/storage/'.$path,
            'original_name' => 'fixture.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 128,
            'width' => 1,
            'height' => 1,
            'checksum' => hash('sha256', $id),
            'orphaned_at' => now(),
        ]);
    }
}
