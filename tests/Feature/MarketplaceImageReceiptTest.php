<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Marketplace\MarketplaceImageReceipts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceImageReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_is_reusable_only_for_same_store_image_and_bytes(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Image source','handle'=>'image-source','base_price'=>100]);
        $image=$product->images()->create(['path'=>'/images/catalog/test.jpg']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $service=app(MarketplaceImageReceipts::class);
        $hash=hash('sha256','original bytes');
        $id=$service->record($store,$product->id,$image->id,$actor->id,$hash,'confirmed-image');
        $this->assertSame($id,$service->record($store,$product->id,$image->id,$actor->id,$hash,'confirmed-image'));
        $this->assertSame('confirmed-image',$service->find($store,$image->id,$hash)?->provider_identity);
        $this->assertNull($service->find($store,$image->id,hash('sha256','changed bytes')));
        $this->assertNull($service->find($store+1,$image->id,$hash));
        $this->assertDatabaseCount('marketplace_image_receipts',1);
        $this->assertDatabaseHas('marketplace_image_receipts',['actor_id'=>$actor->id,'source_hash'=>$hash]);
    }

    public function test_cross_product_image_cannot_be_recorded(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Image source','handle'=>'other-image-source','base_price'=>100]);
        $image=$product->images()->create(['path'=>'/images/catalog/test.jpg']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $this->expectException(\DomainException::class);
        app(MarketplaceImageReceipts::class)->record($store,$product->id+1,$image->id,$actor->id,hash('sha256','bytes'),'confirmed-image');
    }
}
