<?php
namespace App\Services\Marketplace;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Caller owns fresh package eligibility and durable attempt claiming. */
final class LazadaRtsClient
{
 public function send(#[\SensitiveParameter] string $token,array $packages):array
 {
  if (config('marketplace.fulfillment_operations_enabled')!==true) throw new RuntimeException('Marketplace fulfillment is disabled.');
  if (!$packages||!array_is_list($packages)||count($packages)>20) throw new RuntimeException('Invalid RTS packages.');
  foreach ($packages as $id) if (!is_string($id)||!preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$id)) throw new RuntimeException('Invalid RTS packages.');
  if (count($packages)!==count(array_unique($packages))) throw new RuntimeException('Duplicate RTS packages.');
  $key=config('marketplace.lazada.app_key');$secret=config('marketplace.lazada.app_secret');
  if (config('marketplace.lazada.enabled')!==true||!is_string($key)||!preg_match('/\A[1-9][0-9]*\z/D',$key)
   ||!is_string($secret)||trim($secret)===''||trim($token)==='') throw new RuntimeException('Lazada fulfillment is not configured.');
  $path='/order/package/rts';
  $params=['readyToShipReq'=>json_encode(['packages'=>array_map(fn($id)=>['package_id'=>$id],$packages)],JSON_THROW_ON_ERROR),
   'app_key'=>$key,'access_token'=>$token,'sign_method'=>'sha256','timestamp'=>(string)now()->getTimestampMs()];
  ksort($params,SORT_STRING);$base=$path;
  foreach ($params as $name=>$value) $base.=$name.$value;
  $params['sign']=strtoupper(hash_hmac('sha256',$base,$secret));
  try {
   $response=Http::asForm()->acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()->post('https://api.lazada.com.my/rest'.$path,$params);
   $body=$response->json();
   if (!$response->successful()||!is_array($body)) throw new RuntimeException('Unconfirmed response');
   return LazadaRtsResult::parse($body,$packages);
  } catch (\Throwable) { throw new RuntimeException('Lazada RTS outcome is uncertain. Reconcile before retrying.'); }
 }
}
