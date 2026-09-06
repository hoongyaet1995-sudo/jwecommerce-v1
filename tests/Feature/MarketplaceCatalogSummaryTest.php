<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceCatalogSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapping_context_is_protected_bounded_and_does_not_invent_unknown_variants(): void
    {
        config(['marketplace.operations_enabled'=>false,'marketplace.read_operations_enabled'=>true]);
        $this->getJson('/admin/api/marketplace/listings/1/mapping-context?variantIds[]=1')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->getJson('/admin/api/marketplace/listings/1/mapping-context?variantIds[]=1')->assertNotFound();
        $this->getJson('/admin/api/marketplace/listings/1/mapping-context')->assertUnprocessable();
        $this->getJson('/admin/api/marketplace/listings/1/mapping-context?'.http_build_query(['variantIds'=>range(1,101)]))->assertUnprocessable();
        $this->getJson('/admin/api/marketplace/listings/1/mapping-context?variantIds[]=1&variantIds[]=1')->assertUnprocessable();
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'One','region'=>'MY','environment'=>'sandbox']);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store,'external_product_id'=>'p','external_variant_id'=>'v','observed_data'=>'{}','fetched_at'=>now()]);
        $this->getJson('/admin/api/marketplace/listings/'.$listing.'/mapping-context?variantIds[]=999999')->assertOk()->assertExactJson(['data'=>[]]);
        config(['marketplace.read_operations_enabled'=>false]);
        $this->getJson('/admin/api/marketplace/listings/'.$listing.'/mapping-context?variantIds[]=1')->assertStatus(503);
    }

    public function test_mapping_changes_recompute_counts_without_hiding_other_statuses(): void
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'One','region'=>'MY','environment'=>'sandbox']);
        foreach (['one','two'] as $external) DB::table('marketplace_listing_variants')->insert([
            'store_id'=>$store,'external_product_id'=>'p','external_variant_id'=>$external,'observed_data'=>'{}','fetched_at'=>now()]);
        $product = \App\Models\Product::create(['title'=>'Headphones','handle'=>'summary-headphones','base_price'=>10,'status'=>'active']);
        $variant = $product->variants()->create(['sku'=>'SUMMARY-BLUE','title'=>'Blue','price'=>10,'stock'=>7,'enabled'=>true]);
        $listing = DB::table('marketplace_listing_variants')->value('id');
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->putJson('/admin/api/marketplace/listings/'.$listing.'/mapping',['productId'=>$product->id,'variantId'=>$variant->id])->assertOk();
        $otherListing = DB::table('marketplace_listing_variants')->where('id','!=',$listing)->value('id');
        $this->getJson('/admin/api/marketplace/listings/'.$otherListing.'/mapping-context?variantIds[]='.$variant->id)->assertOk()
            ->assertJsonPath('data.0.jw_variant_id',$variant->id)
            ->assertJsonPath('data.0.same_store_conflict.listing_id',$listing)
            ->assertJsonPath('data.0.same_store_conflict.external_variant_id','one');
        $this->getJson('/admin/api/marketplace/listings/'.$listing.'/mapping-context?variantIds[]='.$variant->id)->assertOk()
            ->assertJsonPath('data.0.same_store_conflict',null);
        $secondStore = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Second','region'=>'MY','environment'=>'sandbox']);
        $crossListing = DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$secondStore,'external_product_id'=>'p','external_variant_id'=>'one','observed_data'=>'{}','fetched_at'=>now()]);
        $this->getJson('/admin/api/marketplace/listings/'.$crossListing.'/mapping-context?variantIds[]='.$variant->id)->assertOk()
            ->assertJsonPath('data.0.same_store_conflict',null);
        DB::table('marketplace_listing_variants')->where('id',$crossListing)->delete();
        foreach (['/catalog?status=mapped', '/catalog/products?status=mapped'] as $endpoint) {
            $prefix = str_contains($endpoint, '/products') ? 'data.0.variants.0.' : 'data.0.';
            $this->getJson('/admin/api/marketplace'.$endpoint)->assertOk()
                ->assertJsonPath($prefix.'jw_product_id',$product->id)
                ->assertJsonPath($prefix.'jw_product_title','Headphones')
                ->assertJsonPath($prefix.'jw_variant_title','Blue');
        }
        $this->getJson('/admin/api/marketplace/catalog/summary?status=unmapped')->assertOk()
            ->assertJsonPath('products',1)->assertJsonPath('variants',2)->assertJsonPath('mapped',1)->assertJsonPath('unmapped',1);
        $this->getJson('/admin/api/marketplace/catalog/summary?q=SUMMARY-BLUE')->assertOk()
            ->assertJsonPath('variants',1)->assertJsonPath('mapped',1)->assertJsonPath('unmapped',0);
        $this->deleteJson('/admin/api/marketplace/listings/'.$listing.'/mapping')->assertOk();
        $this->getJson('/admin/api/marketplace/catalog/summary')->assertOk()->assertJsonPath('mapped',0)->assertJsonPath('unmapped',2);
        $this->assertSame(7, $variant->fresh()->stock);
    }

    public function test_counts_cover_the_filtered_store_catalog_not_only_a_page_or_selected_status(): void
    {
        config(['marketplace.operations_enabled'=>false, 'marketplace.read_operations_enabled'=>true]);
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'One','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $other = DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Two','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        foreach (range(1, 320) as $i) {
            DB::table('marketplace_listing_variants')->insert(['store_id'=>$store,'external_product_id'=>(string)ceil($i / 2),
                'external_variant_id'=>(string)$i,'fetched_at'=>now(),'observed_data'=>json_encode(['sku'=>'SKU-'.$i,'productTitle'=>'Headphones'])]);
        }
        DB::table('marketplace_listing_variants')->insert(['store_id'=>$other,'external_product_id'=>'1','external_variant_id'=>'1','fetched_at'=>now(),'observed_data'=>'{}']);
        $this->getJson('/admin/api/marketplace/catalog/summary')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->getJson('/admin/api/marketplace/catalog/summary?storeId='.$store.'&per_page=15&page=2&status=mapped')->assertOk()
            ->assertJsonPath('products',160)->assertJsonPath('variants',320)->assertJsonPath('mapped',0)->assertJsonPath('unmapped',320);
        $this->getJson('/admin/api/marketplace/catalog/summary?storeId='.$store.'&q=SKU-320')->assertOk()
            ->assertJsonPath('products',1)->assertJsonPath('variants',1);
        $this->getJson('/admin/api/marketplace/catalog/summary?platform=lazada')->assertOk()
            ->assertJsonPath('products',1)->assertJsonPath('variants',1);
        $this->getJson('/admin/api/marketplace/catalog/summary')->assertOk()->assertJsonPath('products',161)->assertJsonPath('variants',321);
        $this->getJson('/admin/api/marketplace/catalog/summary?storeId='.$store.'&platform=lazada')->assertOk()
            ->assertJsonPath('products',0)->assertJsonPath('variants',0)->assertJsonPath('mapped',0)->assertJsonPath('unmapped',0);
        config(['marketplace.read_operations_enabled'=>false]);
        $this->getJson('/admin/api/marketplace/catalog/summary')->assertStatus(503);
    }
}
