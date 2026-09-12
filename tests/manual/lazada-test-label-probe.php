<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
 if (!app()->environment('local')) exit(1);
 $store=Illuminate\Support\Facades\DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
 $token=app(App\Services\Marketplace\LazadaTokenManager::class)->accessToken($store->id);
 $data=app(App\Services\Marketplace\LazadaClient::class)->read('/order/package/document/get',$token,
    ['getDocumentReq'=>json_encode(['doc_type'=>'PDF','packages'=>[['package_id'=>'FP021111611256684']]])]);
 $pdf=app(App\Services\Marketplace\LazadaLabelPdf::class)->download($data);
 echo json_encode(['verified_pdf_bytes'=>strlen($pdf),'sha256'=>hash('sha256',$pdf),'fields'=>array_keys($data),'doc_type'=>$data['doc_type']??null,
    'pdf_host'=>parse_url($data['pdf_url']??'',PHP_URL_HOST),'pdf_path'=>parse_url($data['pdf_url']??'',PHP_URL_PATH)]).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,'Label probe failed: '.get_class($error).PHP_EOL); exit(1); }
