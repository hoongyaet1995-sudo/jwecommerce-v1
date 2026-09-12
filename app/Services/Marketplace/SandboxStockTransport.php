<?php
namespace App\Services\Marketplace;

use App\Models\User;
use RuntimeException;

class SandboxStockTransport
{
 public function observe(array $snapshot,User $actor):int {
  $current=app(SandboxInventoryWriteGuard::class)->review($snapshot['store_id'],$snapshot['inventory_balance_id'],$actor);
  if($snapshot!==$current)throw new RuntimeException('Sandbox stock review changed.');
  if($current['platform']==='shopee')return app(ShopeeClient::class)->sandboxStockQuantity($snapshot,$actor);
  $client=app(LazadaClient::class);$token=app(LazadaTokenManager::class)->accessToken($snapshot['store_id']);
  $seller=$client->read('/seller/get',$token);
  if((string)($seller['seller_id']??'')!==$snapshot['external_shop_id']||($seller['short_code']??'')!=='MY4NA1T7CK'||($seller['status']??'')!=='ACTIVE')throw new RuntimeException('Sandbox seller identity changed.');
  $item=$client->read('/product/item/get',$token,['item_id'=>$snapshot['external_product_id']]);
  $skus=array_values(array_filter($item['skus']??[],fn($sku)=>(string)($sku['SkuId']??'')===$snapshot['external_variant_id']));
  if(count($skus)!==1)throw new RuntimeException('Sandbox SKU identity missing or duplicated.');
  $locations=array_values(array_filter($skus[0]['multiWarehouseInventories']??[],fn($row)=>($row['warehouseCode']??null)===$snapshot['platform_location_id']));
  if(count($locations)!==1||!is_int($locations[0]['sellableQuantity']??null)||$locations[0]['sellableQuantity']<0)throw new RuntimeException('Sandbox location stock is unknown.');
  return $locations[0]['sellableQuantity'];
 }
 public function write(array $snapshot,User $actor):void {
  if($snapshot['platform']==='shopee'){app(ShopeeClient::class)->updateSandboxQuantity($snapshot,$actor);return;}
  app(LazadaClient::class)->updateSandboxQuantity($snapshot,$actor);
 }
}
