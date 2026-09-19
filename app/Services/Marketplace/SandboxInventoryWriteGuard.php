<?php
namespace App\Services\Marketplace;

use App\Models\User;
use App\Models\MarketplaceStoreToken;
use App\Services\Inventory\BasicWmsBalanceReader;
use Illuminate\Support\Facades\DB;

/** A local review is necessary but not sufficient: transport must also verify provider identity. */
final class SandboxInventoryWriteGuard
{
    public function review(int $storeId,int $balanceId,User $actor):array
    {
        abort_unless(config('marketplace.basic_wms_enabled')===true&&config('marketplace.sandbox_stock_writes_enabled')===true,403,'Sandbox inventory writes are disabled.');
        abort_unless($actor->exists&&User::whereKey($actor->id)->where('is_admin',true)->exists(),403);
        return DB::transaction(function()use($storeId,$balanceId){
            $configuration=DB::table('inventory_configuration')->where('id',1)->lockForUpdate()->first();
            abort_unless($configuration&&!$configuration->accounting_enabled,409,'Basic test writes require inactive inventory takeover.');
            $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
            abort_unless($store&&in_array($store->platform,['shopee','lazada'],true)&&$store->status==='connected'
                &&$store->data_classification==='test'&&$store->classified_by&&$store->classified_at
                &&(string)config('marketplace.'.$store->platform.'.test_store_id')===(string)$storeId,403,'Select the designated classified test store.');
            $token=MarketplaceStoreToken::where('store_id',$storeId)->first();
            if($store->platform==='lazada'){
                abort_unless($store->environment==='live'&&$store->region==='MY'&&$store->external_shop_id==='300163725140'
                    &&$token?->application_id===(string)config('marketplace.lazada.test_app_key')
                    &&$token->credential_source==='official_test'&&$token->test_lease_expires_at?->isFuture(),403,'Verified Lazada borrowed-seller credentials required.');
            }else{
                abort_unless($store->environment==='sandbox'&&config('marketplace.shopee.environment')==='sandbox'
                    &&$token&&$token->application_id===(string)config('marketplace.shopee.partner_id'),403,'Shopee sandbox credentials required.');
            }
            $identity=DB::table('inventory_balances')->find($balanceId);abort_unless($identity,404);
            $variant=DB::table('product_variants')->where('id',$identity->product_variant_id)->lockForUpdate()->first();
            abort_unless($variant&&$variant->data_classification==='test'&&$variant->enabled,409,'A classified test SKU is required.');
            $balance=DB::table('inventory_balances')->where('id',$balanceId)->lockForUpdate()->first();
            $pool=DB::table('inventory_pools')->find($balance->inventory_pool_id);
            $kind=match($configuration->operating_mode){'shared_pool'=>'shared','independent_warehouses'=>'warehouse',default=>null};
            abort_unless($kind&&$pool&&$pool->kind===$kind,409,'Review the configured inventory pool.');
            $route=DB::table('inventory_fulfillment_routes')->where('channel_key','marketplace:'.$storeId)->lockForUpdate()->first();
            $warehouse=$route?DB::table('inventory_warehouses')->find($route->warehouse_id):null;
            abort_unless($route&&$warehouse&&$warehouse->is_active&&$warehouse->data_classification==='test'
                &&($kind==='shared'||(int)$pool->warehouse_id===(int)$warehouse->id)
                &&is_string($route->platform_location_id)&&$route->platform_location_id!=='',409,'Bind the store to the classified test warehouse and provider location.');
            $listings=DB::table('marketplace_variant_mappings as m')->join('marketplace_listing_variants as l','l.id','=','m.listing_variant_id')
                ->where('m.store_id',$storeId)->where('l.store_id',$storeId)->where('m.product_variant_id',$variant->id)
                ->select('l.id','l.external_product_id','l.external_variant_id')->get();
            abort_unless($listings->count()===1,409,'Exactly one mapped test SKU is required.');
            $reserved=(int)DB::table('stock_allocations')->where('inventory_balance_id',$balanceId)->where('status','reserved')->sum('quantity');
            $basic=app(BasicWmsBalanceReader::class)->project($balance,$reserved);
            abort_unless($balance->counted_at&&$basic['available']!==null,409,'Count physical stock and configure safety before test writes.');
            $listing=$listings->first();
            return ['platform'=>$store->platform,'store_id'=>$storeId,'external_shop_id'=>$store->external_shop_id,
                'listing_variant_id'=>(int)$listing->id,'external_product_id'=>$listing->external_product_id,'external_variant_id'=>$listing->external_variant_id,
                'inventory_balance_id'=>$balanceId,'balance_version'=>(int)$balance->version,'route_version'=>(int)$route->version,
                'platform_location_id'=>$route->platform_location_id,'desired_quantity'=>$basic['available'],
                'configuration_version'=>(int)$configuration->version,'order_coverage_complete'=>false];
        });
    }
}
