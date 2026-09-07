<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Marketplace\MarketplaceLocalImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class MarketplaceLocalImageTest extends TestCase
{
    use RefreshDatabase;
    public function test_reads_owned_managed_image_without_network_and_rejects_other_product(): void
    {
        Storage::fake('public');Http::preventStrayRequests();
        $product=Product::create(['title'=>'Test','handle'=>'local-media-test','base_price'=>1000,'status'=>'draft']);
        $path='cms/media/9285db80-1111-4111-8111-123456789012.png';
        Storage::disk('public')->put($path,'image-bytes');
        $image=ProductImage::create(['product_id'=>$product->id,'path'=>'/storage/'.$path,'position'=>0]);
        $this->assertSame('image-bytes',app(MarketplaceLocalImage::class)->read($product->id,$image->id));
        Http::assertNothingSent();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(MarketplaceLocalImage::class)->read($product->id+1,$image->id);
    }
    public function test_external_and_traversal_paths_are_not_read_as_local_files(): void
    {
        $product=Product::create(['title'=>'Test','handle'=>'unsafe-media-test','base_price'=>1000,'status'=>'draft']);
        foreach (['https://example.com/photo.png','/storage/cms/media/../../.env','/images/catalog/../../.env'] as $path) {
            $image=ProductImage::create(['product_id'=>$product->id,'path'=>$path,'position'=>0]);
            try {app(MarketplaceLocalImage::class)->read($product->id,$image->id);$this->fail('Unsafe path accepted.');}
            catch (\DomainException $e) {$this->assertNotEmpty($e->getMessage());}
        }
    }
}
