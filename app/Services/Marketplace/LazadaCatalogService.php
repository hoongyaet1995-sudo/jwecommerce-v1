<?php
namespace App\Services\Marketplace;
use Illuminate\Support\Facades\DB;
use RuntimeException;
/** Lazada read-only catalog capture; it never creates a JW product or writes a channel listing. */
final class LazadaCatalogService
{
 public function __construct(private LazadaClient $client,private LazadaTokenManager $tokens){}
 public function pullItem(int $storeId,string $itemId):array
 {
  if(trim($itemId)===''||strlen($itemId)>128)throw new RuntimeException('Invalid Lazada item identity.');
  $token=$this->tokens->accessToken($storeId);$store=DB::table('marketplace_stores')->find($storeId);
  if(!$store||$store->platform!=='lazada'||$store->status!=='connected')throw new RuntimeException('Lazada store is not connected.');
  $item=$this->client->read('/product/item/get',$token,['item_id'=>$itemId]);
  if((string)($item['item_id']??$item['ItemId']??'')!==$itemId)throw new RuntimeException('Lazada returned another product identity.');
  $rows=$this->capture($store,[$item],$token);
  return ['items'=>1,'variants'=>count($rows)];
 }
 public function pullPage(int $storeId,int $offset):array
 {
  if($offset<0||$offset>5000||$offset%50!==0)throw new RuntimeException('Invalid Lazada catalog offset.');
  $token=$this->tokens->accessToken($storeId);$store=DB::table('marketplace_stores')->find($storeId);
  if(!$store||$store->platform!=='lazada'||$store->status!=='connected')throw new RuntimeException('Lazada store is not connected.');
  $page=$this->client->read('/products/get',$token,['filter'=>'all','limit'=>50,'offset'=>$offset]);
  $products=$page['products']??null;$total=$page['total_products']??$page['total']??$page['countTotal']??null;
  if(!is_array($products)||!array_is_list($products)||count($products)>50||(!is_int($total)&&!ctype_digit((string)$total)))throw new RuntimeException('Lazada catalog page is incomplete.');
  $rows=$this->capture($store,$products,$token);
  $hasMore=$offset+count($products)<(int)$total;return ['items'=>count($products),'variants'=>count($rows),'has_more'=>$hasMore,'next_offset'=>$offset+50];
 }
 private function capture(object $store,array $products,#[\SensitiveParameter] string $token):array
 {
  $storeId=(int)$store->id;
  $rows=[];
  foreach($products as $summary){
   $itemId=$summary['item_id']??$summary['ItemId']??null;
   if((!is_string($itemId)&&!is_int($itemId))||(string)$itemId==='')throw new RuntimeException('Lazada catalog product identity is incomplete.');
   $item=is_array($summary['skus']??null)&&is_array($summary['attributes']??null)?$summary:$this->client->read('/product/item/get',$token,['item_id'=>(string)$itemId]);$skus=$item['skus']??$item['Skus']??null;
   if(!is_array($skus)||!array_is_list($skus))throw new RuntimeException('Lazada catalog SKU structure is incomplete.');
   foreach($skus as $sku){
    $skuId=$sku['SkuId']??$sku['sku_id']??null;if((!is_string($skuId)&&!is_int($skuId))||(string)$skuId==='')throw new RuntimeException('Lazada catalog SKU identity is incomplete.');
    $rows[]=['external_product_id'=>(string)$itemId,'external_variant_id'=>(string)$skuId,'observed_data'=>json_encode([
      'productImages'=>$item['images']??[], 'brand'=>data_get($item,'attributes.brand'),
      'sourceDescriptionHtml'=>data_get($item,'attributes.description'),
      'productTitle'=>data_get($item,'attributes.name')??data_get($item,'Attributes.name'),'title'=>$sku['SellerSku']??$sku['seller_sku']??null,
      'sku'=>$sku['SellerSku']??$sku['seller_sku']??null,'price'=>$sku['price']??null,'currency'=>$sku['currency']??($store->region==='MY'?'MYR':null),
      'stock'=>is_numeric($sku['quantity']??null)?(int)$sku['quantity']:null,'stockInfo'=>array_intersect_key($sku,array_flip(['multiWarehouseInventories','fblWarehouseInventories','channelInventories'])),
      'status'=>$sku['Status']??$sku['status']??null,'externalCategoryId'=>$item['primary_category']??$item['primaryCategory']??$item['PrimaryCategory']??null,
    ],JSON_THROW_ON_ERROR)];
   }
  }
  DB::transaction(function()use($storeId,$store,$rows){$current=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();if(!$current||$current->status!=='connected'||$current->external_shop_id!==$store->external_shop_id||$current->environment!==$store->environment)throw new RuntimeException('Store connection changed during catalog fetch.');foreach($rows as $row){$identity=['store_id'=>$storeId,'external_product_id'=>$row['external_product_id'],'external_variant_id'=>$row['external_variant_id']];$existing=DB::table('marketplace_listing_variants')->where($identity)->first();DB::table('marketplace_listing_variants')->updateOrInsert($identity,['observed_data'=>$row['observed_data'],'fetched_at'=>now(),'updated_at'=>now(),'created_at'=>$existing->created_at??now()]);}});
  app(PublishedCatalogMapping::class)->reconcile($storeId,$rows);
  return $rows;
 }
}
