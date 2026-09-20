<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceAttributeImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_attaches_a_verified_image_to_the_selected_product_media(): void
    {
        Storage::fake('public');
        $product = $this->product('attribute-image');

        $response = $this->actingAs($this->admin())->post(
            "/admin/api/marketplace/products/{$product->id}/attribute-images",
            ['file' => $this->sourceJpeg('size-chart.jpg')],
            ['Accept' => 'application/json'],
        )->assertCreated()->assertJsonStructure(['imageId', 'url', 'alt', 'sourceRevision']);

        $this->assertDatabaseHas('product_images', [
            'id' => $response->json('imageId'),
            'product_id' => $product->id,
            'path' => $response->json('url'),
            'alt' => 'size-chart.jpg',
            'position' => 0,
        ]);
        $this->assertDatabaseHas('catalog_media_references', [
            'owner_type' => 'product',
            'owner_id' => $product->id,
            'field_path' => 'images.0',
        ]);
        $this->assertDatabaseHas('cms_media_assets', ['url' => $response->json('url')]);
        $this->assertSame(
            app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read($product->id)['revision'],
            $response->json('sourceRevision'),
        );
    }

    public function test_endpoint_rejects_unauthorized_non_image_oversize_corrupt_and_extra_identity_fields(): void
    {
        Storage::fake('public');
        $product = $this->product('attribute-image-policy');
        $url = "/admin/api/marketplace/products/{$product->id}/attribute-images";

        $this->post($url, ['file' => $this->sourceJpeg('chart.jpg')], ['Accept' => 'application/json'])
            ->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post($url, ['file' => $this->sourceJpeg('chart.jpg')], ['Accept' => 'application/json'])
            ->assertUnauthorized();
        $admin = $this->admin();
        $cases = [
            'plain text' => UploadedFile::fake()->createWithContent('chart.png', 'not an image'),
            'over ten MiB' => UploadedFile::fake()->create('large.png', 10 * 1024 + 1, 'image/png'),
            'corrupt png' => UploadedFile::fake()->createWithContent('broken.png', "\x89PNG\r\n\x1a\nBROKEN"),
        ];
        foreach ($cases as $label => $file) {
            $this->actingAs($admin)->post($url, ['file' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable();
            $this->assertDatabaseCount('product_images', 0);
        }
        $this->actingAs($admin)->post($url, [
            'file' => $this->sourceJpeg('chart.jpg'),
            'product_id' => $this->product('foreign-attribute-image')->id,
            'path' => 'https://example.test/forged.jpg',
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_missing_product_returns_not_found_without_creating_media(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())->post(
            '/admin/api/marketplace/products/999999/attribute-images',
            ['file' => $this->sourceJpeg('chart.jpg')],
            ['Accept' => 'application/json'],
        )->assertNotFound();

        $this->assertDatabaseCount('product_images', 0);
        $this->assertDatabaseCount('cms_media_assets', 0);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function product(string $handle): Product
    {
        $product = Product::query()->create(['title' => 'Attribute Image', 'handle' => $handle, 'base_price' => 100]);
        $product->variants()->create([
            'title' => 'Default',
            'sku' => strtoupper($handle),
            'price' => 100,
            'stock' => 0,
            'enabled' => true,
        ]);

        return $product;
    }

    private function sourceJpeg(string $name): UploadedFile
    {
        return new UploadedFile(
            base_path('public/images/source/jwstudio_logo_1786766390670.jpg'),
            $name,
            'image/jpeg',
            null,
            true,
        );
    }
}
