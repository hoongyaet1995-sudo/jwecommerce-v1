<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class LazadaOrderSnapshotTest extends TestCase
{
 use RefreshDatabase;
 public function test_lazada_snapshot_is_store_scoped_encrypted_and_idempotent():void
 {
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','environment'=>'live','region'=>'MY','external_shop_id'=>'seller-1','shop_name'=>'Lazada MY','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
  $payload=['order_id'=>'LZ-1','updated_at'=>'2026-09-08T10:00:00+08:00','statuses'=>['pending'],'currency'=>'MYR','price'=>'99.50','order_items'=>[]];
  $service=app(\App\Services\Marketplace\MarketplaceOrderSnapshotService::class);
  $id=$service->saveLazada($store,$payload);
  $this->assertSame($id,$service->saveLazada($store,$payload));
  $row=DB::table('marketplace_orders')->find($id);
  $this->assertSame('pending',$row->platform_status);$this->assertSame(99.5,(float)$row->total_amount);
  $this->assertSame($payload, json_decode(Crypt::decryptString($row->encrypted_payload),true,512,JSON_THROW_ON_ERROR));
 }
}
