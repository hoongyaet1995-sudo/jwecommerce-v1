<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_non_admins_cannot_access_marketplace(): void
    {
        $this->getJson('/admin/api/marketplace')->assertUnauthorized();
        // Existing ClearLegacyCustomerWebSession middleware removes customer web sessions.
        $this->actingAs(User::factory()->create(['is_admin' => false]))->getJson('/admin/api/marketplace')->assertUnauthorized();
    }

    public function test_empty_workspace_and_store_configuration_survive_refetch(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->getJson('/admin/api/marketplace')->assertOk()->assertJsonPath('stores', []);
        $this->postJson('/admin/api/marketplace/stores', ['platform'=>'shopee','shopName'=>'Test shop','region'=>'MY','environment'=>'sandbox'])
            ->assertCreated()->assertJsonPath('data.status','pending_auth');
        $this->getJson('/admin/api/marketplace')->assertOk()->assertJsonPath('stores.0.shopName','Test shop')->assertJsonPath('providerAvailable',false);
        $this->assertDatabaseCount('marketplace_stores',1);
    }

    public function test_client_cannot_forge_authorization_or_submit_provider_secrets(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $base=['platform'=>'shopee','shopName'=>'Shop','region'=>'MY','environment'=>'sandbox'];
        $this->postJson('/admin/api/marketplace/stores',$base+['status'=>'connected'])->assertUnprocessable();
        $this->postJson('/admin/api/marketplace/stores',$base+['appSecret'=>'do-not-store'])->assertUnprocessable();
        $this->postJson('/admin/api/marketplace/stores',array_replace($base,['platform'=>'unknown']))->assertUnprocessable();
        $this->assertDatabaseCount('marketplace_stores',0);
    }

    public function test_remote_actions_fail_closed(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->postJson('/admin/api/marketplace/remote/fetch')->assertStatus(409)->assertJsonPath('code','provider_not_available');
        $this->assertDatabaseCount('marketplace_listing_variants',0);
    }

    public function test_mapping_enforces_per_store_uniqueness_and_allows_cross_store_reuse_without_touching_stock(): void
    {
        $this->actingAs($admin=User::factory()->create(['is_admin'=>true]));
        $product=\App\Models\Product::query()->create(['title'=>'Mapping test','handle'=>'mapping-test','base_price'=>10,'status'=>'active']);
        $variant=$product->variants()->create(['sku'=>'MAP-1','title'=>'One','price'=>10,'stock'=>7,'enabled'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Fixture','region'=>'MY','environment'=>'sandbox','status'=>'pending_auth','created_at'=>now(),'updated_at'=>now()]);
        $ids=[];
        foreach(['one','two'] as $external) $ids[]=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store,'external_product_id'=>'p1','external_variant_id'=>$external,'observed_data'=>'{}','fetched_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $this->putJson('/admin/api/marketplace/listings/'.$ids[0].'/mapping',['productId'=>$product->id+100,'variantId'=>$variant->id])->assertUnprocessable();
        $this->putJson('/admin/api/marketplace/listings/'.$ids[0].'/mapping',['productId'=>$product->id,'variantId'=>$variant->id])->assertOk();
        $this->putJson('/admin/api/marketplace/listings/'.$ids[1].'/mapping',['productId'=>$product->id,'variantId'=>$variant->id])->assertStatus(409);
        $this->assertDatabaseCount('marketplace_variant_mappings',1);
        $this->assertDatabaseCount('marketplace_mapping_events',1);
        $otherStore=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Other store','region'=>'MY','environment'=>'sandbox','status'=>'pending_auth','created_at'=>now(),'updated_at'=>now()]);
        $otherListing=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$otherStore,'external_product_id'=>'p1','external_variant_id'=>'one','observed_data'=>'{}','fetched_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $this->putJson('/admin/api/marketplace/listings/'.$otherListing.'/mapping',['productId'=>$product->id,'variantId'=>$variant->id])->assertOk();
        $this->putJson('/admin/api/marketplace/listings/'.$ids[0].'/mapping',['productId'=>$product->id,'variantId'=>$variant->id])->assertOk();
        $this->assertDatabaseCount('marketplace_variant_mappings',2);
        $this->assertDatabaseCount('marketplace_mapping_events',2);
        $this->deleteJson('/admin/api/marketplace/listings/'.$ids[0].'/mapping')->assertOk();
        $this->assertDatabaseCount('marketplace_variant_mappings',1);
        $this->assertDatabaseCount('marketplace_mapping_events',3);
        $this->putJson('/admin/api/marketplace/listings/'.$ids[1].'/mapping',['productId'=>$product->id,'variantId'=>$variant->id])->assertOk();
        $this->assertDatabaseCount('marketplace_variant_mappings',2);
        $this->assertDatabaseCount('marketplace_mapping_events',4);
        $this->assertEquals(7,$variant->fresh()->stock);
        $this->deleteJson('/admin/api/marketplace/stores/'.$store)->assertStatus(409);
    }
}
