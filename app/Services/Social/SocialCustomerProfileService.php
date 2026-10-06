<?php
namespace App\Services\Social;
use App\Models\ManagedSocialAccount;
use Illuminate\Support\Facades\Http;
class SocialCustomerProfileService {
 public function fetch(ManagedSocialAccount $account,string $senderId):array {
  if(!in_array($account->platform,['facebook','instagram'],true))return [];
  $token=$account->access_token;
  $response=Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(12)->get('https://graph.facebook.com/'.config('services.facebook.graph_version','v25.0').'/'.$senderId,['fields'=>$account->platform==='instagram'?'name,username,profile_pic':'name,first_name,last_name,profile_pic','appsecret_proof'=>hash_hmac('sha256',$token,(string)config('services.facebook.client_secret'))]);
  if(!$response->successful())throw new \RuntimeException('Social customer profile unavailable (HTTP '.$response->status().').');
  $data=$response->json();$result=[];
  foreach(['name'=>'name','username'=>'username','profile_pic'=>'avatar_url'] as $source=>$target)if(is_string($data[$source]??null)&&$data[$source]!=='')$result[$target]=mb_substr($data[$source],0,$target==='avatar_url'?2048:255);
  return $result;
 }
}
