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
    public function test_shopee_resolves_owned_remote_image_without_changing_catalog_source(): void
    {
        Storage::fake('public'); Http::preventStrayRequests();
        $product=Product::create(['title'=>'Test','handle'=>'remote-publishing-image','base_price'=>1000,'status'=>'draft']);
        $remote='https://sg-test-11.slatic.net/p/owned.png';
        $image=ProductImage::create(['product_id'=>$product->id,'path'=>$remote,'position'=>0]);
        $id='9285db80-1111-4111-8111-123456789012';
        $path='cms/media/'.$id.'.png';
        $bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==');
        Storage::disk('public')->put($path,$bytes);
        \App\Models\CmsMediaAsset::create(['id'=>$id,'disk'=>'public','path'=>$path,'url'=>'/storage/'.$path,
            'original_name'=>'owned.png','mime_type'=>'image/png','size'=>strlen($bytes),'checksum'=>hash('sha256',$bytes)]);
        \Illuminate\Support\Facades\DB::table('marketplace_media_sources')->insert(['source_hash'=>hash('sha256',$remote),
            'source_url'=>$remote,'asset_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
        $this->assertSame($bytes,app(MarketplaceLocalImage::class)->readForShopee($product->id,$image->id));
        $this->assertSame($remote,$image->fresh()->path);
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'REMOTE-VARIANT','price'=>1000,'stock'=>0,'image_url'=>$remote]);
        $this->assertSame($bytes,app(MarketplaceLocalImage::class)->readVariantForShopee($product->id,$variant->id,$remote));
        Http::assertNothingSent();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(MarketplaceLocalImage::class)->readForShopee($product->id+1,$image->id);
    }
    public function test_shopee_image_resolution_rejects_untrusted_urls_and_changed_variant_paths(): void
    {
        Http::preventStrayRequests();
        $product=Product::create(['title'=>'Test','handle'=>'untrusted-publishing-image','base_price'=>1000]);
        $image=$product->images()->create(['path'=>'https://example.com/photo.png']);
        try {app(MarketplaceLocalImage::class)->readForShopee($product->id,$image->id);$this->fail('Untrusted URL accepted.');}
        catch (\DomainException $e) {$this->assertStringContainsString('supported local',$e->getMessage());}
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'CHANGED-PATH','price'=>1000,'stock'=>0,'image_url'=>'/images/catalog/new.jpg']);
        try {app(MarketplaceLocalImage::class)->readVariantForShopee($product->id,$variant->id,'/images/catalog/old.jpg');$this->fail('Changed variant accepted.');}
        catch (\DomainException $e) {$this->assertStringContainsString('changed',$e->getMessage());}
        Http::assertNothingSent();
    }
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
