<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$lastResponse=[];
Illuminate\Support\Facades\Event::listen(Illuminate\Http\Client\Events\ResponseReceived::class,
    function ($event) use (&$lastResponse) {
        $code=$event->response->json('code');
        $lastResponse=['http_status'=>$event->response->status(),
            'provider_code'=>is_scalar($code)&&preg_match('/\A[A-Za-z0-9_.-]{1,40}\z/',(string)$code)?(string)$code:null];
    });
try {
    if (!app()->environment('local')) throw new RuntimeException('Local only');
    $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
    $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
    $matches=[]; $scanned=0; $complete=false;
    $fetch=function (int $offset,int $limit) use (&$fetch,&$lastResponse,$token):array {
      $lastResponse=[];
      try {
        return app(App\Services\Marketplace\LazadaClient::class)->read('/products/get',$token,
          ['filter'=>'all','limit'=>$limit,'offset'=>$offset]);
      } catch (RuntimeException $error) {
        if (($lastResponse['provider_code']??null)!=='ServiceTimeout'||$limit===1) throw $error;
        $leftSize=intdiv($limit,2);
        $left=$fetch($offset,$leftSize);
        $right=$fetch($offset+$leftSize,$limit-$leftSize);
        if (!is_array($left['products']??null)||!is_array($right['products']??null)
            ||($left['total_products']??null)!==($right['total_products']??null)) throw new RuntimeException('Split response changed');
        $left['products']=array_merge($left['products'],$right['products']);
        return $left;
      }
    };
    $start=isset($argv[1])&&ctype_digit($argv[1])?(int)$argv[1]:0;
    $limit=isset($argv[2])&&ctype_digit($argv[2])?(int)$argv[2]:50;
    $maxPages=isset($argv[3])&&ctype_digit($argv[3])?(int)$argv[3]:101;
    if ($limit<1||$limit>50||$maxPages<1||$maxPages>250) throw new RuntimeException('Invalid page bounds');
    if ($start>5000) throw new RuntimeException('Invalid start offset');
    for ($offset=$start,$page=0;$offset<=5000&&$page<$maxPages;$offset+=$limit,$page++) {
      $data=$fetch($offset,$limit);
      $products=$data['products']??null;
      $total=$data['total_products']??$data['total']??$data['countTotal']??null;
      if (!is_array($products)||!array_is_list($products)||!is_numeric($total)) throw new RuntimeException('Incomplete catalog response');
      $scanned+=count($products);
      if (($page+1)%10===0) echo json_encode(['progress_offset'=>$offset,'scanned'=>$scanned]).PHP_EOL;
      foreach ($products as $product) foreach ($product['skus']??[] as $sku) {
        if (($sku['SellerSku']??'')==='JW-SBX-LZ-WMS-20260908') $matches[]=['item_id'=>$product['item_id'],
            'sku_id'=>$sku['SkuId'],'status'=>$sku['Status']??null,'quantity'=>$sku['quantity']??null];
      }
      if ($offset+count($products)>=(int)$total) { $complete=true; break; }
      if (!$products) throw new RuntimeException('Catalog pagination did not advance');
    }
    echo json_encode(['scan_complete'=>$complete&&$start===0,'reached_end'=>$complete,'start_offset'=>$start,'scanned'=>$scanned,'total'=>$total,'matches'=>$matches]).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,json_encode(['scan_complete'=>false,'failed_offset'=>$offset??null,'scanned'=>$scanned??0,
        'matches'=>$matches??[],'error_class'=>get_class($error),'last_response'=>$lastResponse]).PHP_EOL);
    exit(1);
}
