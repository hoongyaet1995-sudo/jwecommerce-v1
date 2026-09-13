<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplacePublishingSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_snapshot_is_persisted_and_changed_source_is_rejected_before_http(): void
    {
        config(['marketplace.basic_wms_enabled'=>false,'marketplace.read_operations_enabled'=>true,'marketplace.publish_operations_enabled'=>true]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $product=Product::create(['title'=>'Reviewed','handle'=>'reviewed-snapshot','base_price'=>1599]);
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'JW-REVIEW','price'=>1599,'stock'=>77,'enabled'=>true]);
        $store=\Illuminate\Support\Facades\DB::table('marketplace_stores')->insertGetId([
            'platform'=>'shopee','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $source=$this->getJson("/admin/api/marketplace/products/{$product->id}/publishing-source")->assertOk()->json();
        $task=$this->postJson("/admin/api/marketplace/stores/{$store}/publishing-tasks",[
            'product_id'=>$product->id,'request_key'=>'7285db80-1111-4111-8111-123456789012',
            'source_revision'=>$source['revision'],'payload'=>['item_name'=>'Reviewed'],
        ])->assertOk()->json('id');
        $saved=\Illuminate\Support\Facades\DB::table('marketplace_publishing_tasks')->find($task);
        $this->assertSame($source['revision'],$saved->source_revision ?? null);
        $this->assertSame($source,json_decode($saved->source_snapshot,true));
        $variant->update(['price'=>1799]);
        \Illuminate\Support\Facades\Http::fake();
        try {
            app(\App\Services\Marketplace\MarketplacePublishingDispatchService::class)->dispatch($task);
            $this->fail('Changed product was dispatched');
        } catch (\RuntimeException $error) {
            $this->assertSame('The product changed after review. Review it again before publishing.',$error->getMessage());
        }
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$task,'status'=>'rejected']);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_basic_publishing_rejects_unclassified_destination_before_task_creation(): void
    {
        config(['marketplace.basic_wms_enabled'=>true,'marketplace.read_operations_enabled'=>true,'marketplace.publish_operations_enabled'=>true]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $product=Product::create(['title'=>'Saved','handle'=>'destination-publish','base_price'=>1599]);
        $product->variants()->create(['title'=>'Default','sku'=>'JW-DEST','price'=>1599,'stock'=>77,'enabled'=>true]);
        $store=\Illuminate\Support\Facades\DB::table('marketplace_stores')->insertGetId([
            'platform'=>'shopee','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $revision=$this->getJson("/admin/api/marketplace/products/{$product->id}/publishing-source")->assertOk()->json('revision');
        $this->postJson("/admin/api/marketplace/stores/{$store}/publishing-tasks",[
            'product_id'=>$product->id,'request_key'=>'9285db80-1111-4111-8111-123456789012',
            'source_revision'=>$revision,'payload'=>['item_name'=>'Saved'],
        ])->assertForbidden();
        $this->assertDatabaseCount('marketplace_publishing_tasks',0);
    }

    public function test_stale_review_cannot_create_a_publishing_task(): void
    {
        config(['marketplace.basic_wms_enabled'=>false]);
        config(['marketplace.read_operations_enabled'=>true,'marketplace.publish_operations_enabled'=>true]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $product=Product::create(['title'=>'Saved title','handle'=>'stale-publish','base_price'=>1599]);
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'JW-STALE','price'=>1599,'stock'=>77,'enabled'=>true]);
        $store=\Illuminate\Support\Facades\DB::table('marketplace_stores')->insertGetId([
            'platform'=>'shopee','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $revision=$this->getJson("/admin/api/marketplace/products/{$product->id}/publishing-source")->assertOk()->json('revision');
        $variant->update(['price'=>1799]);
        $this->postJson("/admin/api/marketplace/stores/{$store}/publishing-tasks",[
            'product_id'=>$product->id,'request_key'=>'9285db80-1111-4111-8111-123456789012',
            'source_revision'=>$revision,'payload'=>['item_name'=>'Saved title'],
        ])->assertStatus(409);
        $this->assertDatabaseCount('marketplace_publishing_tasks',0);
    }

    public function test_review_uses_saved_catalog_values_and_zero_initial_stock_without_mutating_inventory(): void
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $product=Product::create(['title'=>'Saved title','handle'=>'publish-source','base_price'=>1599]);
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'JW-SOURCE','price'=>1599,'stock'=>77,'enabled'=>true]);
        $product->variants()->create(['title'=>'Disabled','sku'=>'DISABLED','price'=>100,'stock'=>8,'enabled'=>false]);
        $url="/admin/api/marketplace/products/{$product->id}/publishing-source";
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $response=$this->getJson($url)->assertOk()->assertJsonPath('title','Saved title')
            ->assertJsonCount(1,'variants')->assertJsonPath('variants.0.price_minor',1599)
            ->assertJsonPath('variants.0.initial_quantity',0)->assertJsonPath('variants.0.sku','JW-SOURCE');
        $revision=$response->json('revision');
        $this->assertSame(64,strlen($revision));
        $this->assertSame(77,$variant->fresh()->stock);
        $this->assertDatabaseCount('marketplace_publishing_tasks',0);
        $variant->update(['price'=>1799]);
        $changed=$this->getJson($url)->assertOk()->assertJsonPath('variants.0.price_minor',1799);
        $this->assertNotSame($revision,$changed->json('revision'));
        $variant->update(['enabled'=>false]);
        $this->getJson($url)->assertStatus(422);
    }
}
