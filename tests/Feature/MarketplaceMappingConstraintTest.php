<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceMappingConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_unmapped_selection_cannot_overwrite_a_new_mapping():void
    {
        [$store,$otherStore,$one,$two,$other,$variant]=$this->fixtures();
        $actor=\App\Models\User::factory()->create(['is_admin'=>true]);
        $product=DB::table('product_variants')->where('id',$variant)->value('product_id');
        $second=\App\Models\ProductVariant::create(['product_id'=>$product,'sku'=>'CONSTRAINT-2','title'=>'Two','price'=>10,'stock'=>0,'enabled'=>true]);
        app(\App\Services\Marketplace\MarketplaceFoundationService::class)->map($one,$variant,$product,$actor->id);
        $this->actingAs($actor)->putJson('/admin/api/marketplace/listings/'.$one.'/mapping',[
            'productId'=>$product,'variantId'=>$second->id,'expectedVariantId'=>null,
        ])->assertConflict();
        $this->assertDatabaseHas('marketplace_variant_mappings',['listing_variant_id'=>$one,'product_variant_id'=>$variant]);
        $this->assertDatabaseCount('marketplace_mapping_events',1);
    }

    public function test_reviewed_existing_mapping_can_be_saved_without_duplicate_events():void
    {
        [$store,$otherStore,$one,$two,$other,$variant]=$this->fixtures();
        $actor=\App\Models\User::factory()->create(['is_admin'=>true]);
        $product=DB::table('product_variants')->where('id',$variant)->value('product_id');
        $this->actingAs($actor);
        $url='/admin/api/marketplace/listings/'.$one.'/mapping';
        $this->putJson($url,['productId'=>$product,'variantId'=>$variant,'expectedVariantId'=>null])->assertOk();
        $this->putJson($url,['productId'=>$product,'variantId'=>$variant,'expectedVariantId'=>$variant])->assertOk();
        $this->assertDatabaseCount('marketplace_mapping_events',1);
        $this->assertDatabaseHas('product_variants',['id'=>$variant,'stock'=>7]);
    }

    public function test_database_rejects_a_second_mapping_for_the_same_store_and_jw_variant(): void
    {
        [$store, $otherStore, $one, $two, $other, $variant] = $this->fixtures();
        DB::table('marketplace_variant_mappings')->insert(['listing_variant_id'=>$one,'store_id'=>$store,'product_variant_id'=>$variant]);
        DB::table('marketplace_variant_mappings')->insert(['listing_variant_id'=>$other,'store_id'=>$otherStore,'product_variant_id'=>$variant]);
        $this->assertDatabaseCount('marketplace_variant_mappings', 2);
        $this->expectException(QueryException::class);
        DB::table('marketplace_variant_mappings')->insert(['listing_variant_id'=>$two,'store_id'=>$store,'product_variant_id'=>$variant]);
    }

    public function test_database_rejects_a_forged_store_for_a_listing(): void
    {
        [$store, $otherStore, $one, $two, $other, $variant] = $this->fixtures();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('FOREIGN KEY constraint failed');
        DB::table('marketplace_variant_mappings')->insert(['listing_variant_id'=>$one,'store_id'=>$otherStore,'product_variant_id'=>$variant]);
    }

    public function test_upgrade_refuses_legacy_duplicates_before_changing_schema_or_records(): void
    {
        [$store, $otherStore, $one, $two, $other, $variant] = $this->fixtures();
        $migration = require database_path('migrations/2026_09_06_000002_enforce_marketplace_store_mapping_uniqueness.php');
        $migration->down();
        foreach ([$one, $two] as $listing) DB::table('marketplace_variant_mappings')->insert(['listing_variant_id'=>$listing,'product_variant_id'=>$variant]);
        try {
            $migration->up();
            $this->fail('Legacy duplicates must block migration.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Marketplace mapping conflict:', $error->getMessage());
        }
        $this->assertDatabaseCount('marketplace_variant_mappings', 2);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('marketplace_variant_mappings', 'store_id'));
    }

    public function test_upgrade_preserves_existing_mapping_and_backfills_its_real_store(): void
    {
        [$store, $otherStore, $one, $two, $other, $variant] = $this->fixtures();
        $migration = require database_path('migrations/2026_09_06_000002_enforce_marketplace_store_mapping_uniqueness.php');
        $migration->down();
        $id = DB::table('marketplace_variant_mappings')->insertGetId(['listing_variant_id'=>$one,'product_variant_id'=>$variant]);
        $migration->up();
        $this->assertDatabaseHas('marketplace_variant_mappings', ['id'=>$id,'listing_variant_id'=>$one,'product_variant_id'=>$variant,'store_id'=>$store]);
        $this->assertDatabaseCount('marketplace_variant_mappings', 1);
    }

    private function fixtures(): array
    {
        $product=Product::query()->create(['title'=>'Constraint','handle'=>'constraint','base_price'=>10,'status'=>'active']);
        $variant=$product->variants()->create(['sku'=>'CONSTRAINT-1','title'=>'One','price'=>10,'stock'=>7,'enabled'=>true]);
        $stores=[];
        foreach(['A','B'] as $name) $stores[]=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>$name,'region'=>'MY','environment'=>'sandbox']);
        $listings=[];
        foreach([[$stores[0],'one'],[$stores[0],'two'],[$stores[1],'one']] as [$store,$external]) {
            $listings[]=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store,'external_product_id'=>'p1','external_variant_id'=>$external,'observed_data'=>'{}','fetched_at'=>now()]);
        }
        return [$stores[0],$stores[1],...$listings,$variant->id];
    }
}
