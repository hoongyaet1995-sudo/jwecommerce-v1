<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
 $id=app(App\Services\Marketplace\LazadaTestConnectionService::class)->connect(trim(fgets(STDIN)));
 $row=Illuminate\Support\Facades\DB::table('marketplace_store_tokens')->where('store_id',$id)->first();
 echo json_encode(['store_id'=>$id,'credential_source'=>$row->credential_source,'provider_expiry'=>$row->access_expires_at,'test_lease_expires_at'=>$row->test_lease_expires_at]).PHP_EOL;
} catch(Throwable $e){fwrite(STDERR,'Connection failed: '.get_class($e).PHP_EOL);exit(1);}
