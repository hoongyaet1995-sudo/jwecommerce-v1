<?php
namespace App\Services\Customers;

use App\Models\{Customer,CustomerChannelIdentity,CustomerPlatformPresence,SupportConversation};
use Illuminate\Support\Facades\DB;

class CustomerContactResolver {
 public function associate(SupportConversation $conversation,array $identity,array $profile=[]):Customer {
  foreach(['platform','merchant_key','app_id','account_id','sender_id'] as $key)if(!isset($identity[$key])||!is_string($identity[$key])||$identity[$key]==='')throw new \InvalidArgumentException('Missing identity '.$key);
  if(!in_array($identity['platform'],['storefront','facebook','instagram','whatsapp','tiktok'],true))throw new \InvalidArgumentException('Unknown platform');
  return DB::transaction(function()use($conversation,$identity,$profile){
   // CRM has one tenant per installation. Serialize cross-account matching too.
   DB::table('customer_identity_locks')->insertOrIgnore(['key'=>'contacts']);DB::table('customer_identity_locks')->where('key','contacts')->lockForUpdate()->first();
   $hash=hash('sha256',json_encode(array_map(fn($k)=>$identity[$k],['platform','merchant_key','app_id','account_id','sender_id'])));
   $firstSeen=$conversation->created_at??now();$lastSeen=$conversation->last_message_at??$firstSeen;
   $link=CustomerChannelIdentity::where('identity_hash',$hash)->lockForUpdate()->first();
   $email=ContactValueNormalizer::email($profile['email']??null);$phone=ContactValueNormalizer::phone($profile['phone']??null);
   $trustedEmail=($profile['email_trusted']??false)?$email:null;$trustedPhone=($profile['phone_trusted']??false)?$phone:null;
   $conflict=false;$customer=$link?->customer;
   if($customer&&($trustedEmail||$trustedPhone)){
    $matches=Customer::whereNull('merged_into_id')->where(function($q)use($trustedEmail,$trustedPhone){if($trustedEmail)$q->whereRaw('LOWER(TRIM(email)) = ?',[$trustedEmail]);if($trustedPhone)$q->orWhere('normalized_phone',$trustedPhone);})->lockForUpdate()->get();
    $other=$matches->reject(fn($c)=>$c->id===$customer->id);
    $conflict=$matches->count()>1;
    if($other->count()===1&&!$conflict&&$this->contradicts($other->first(),$trustedEmail,$trustedPhone))$conflict=true;
    if($other->count()===1&&!$conflict){
     $provisional=!$customer->user_id&&!$customer->email&&!$customer->phone&&$customer->channelIdentities()->count()===1;
     foreach(['orders','customer_addresses'] as $table)if(\Illuminate\Support\Facades\Schema::hasTable($table)&&DB::table($table)->where('customer_id',$customer->id)->exists())$provisional=false;
     if($provisional){
      $target=$other->first();$old=$customer;
      foreach($old->platformPresences as $presence)CustomerPlatformPresence::firstOrCreate(['customer_id'=>$target->id,'platform'=>$presence->platform],['first_seen_at'=>$presence->first_seen_at,'last_seen_at'=>$presence->last_seen_at]);
      SupportConversation::where('customer_id',$old->id)->update(['customer_id'=>$target->id]);
      $old->forceFill(['merged_into_id'=>$target->id])->save();$link->update(['customer_id'=>$target->id]);$customer=$target;
     }else $conflict=true;
    }
   }
   if(!$customer){
    if($identity['platform']==='storefront'&&$conversation->customer_user_id)$customer=Customer::where('user_id',$conversation->customer_user_id)->lockForUpdate()->first();
    $candidates=collect();
    if(!$customer&&($trustedEmail||$trustedPhone))$candidates=Customer::whereNull('merged_into_id')->where(function($q)use($trustedEmail,$trustedPhone){if($trustedEmail)$q->whereRaw('LOWER(TRIM(email)) = ?',[$trustedEmail]);if($trustedPhone)$q->orWhere('normalized_phone',$trustedPhone);})->lockForUpdate()->get();
    $conflict=$candidates->count()>1||($candidates->count()===1&&$this->contradicts($candidates->first(),$trustedEmail,$trustedPhone));
    if(!$customer&&$candidates->count()===1&&!$conflict)$customer=$candidates->first();
    if(!$customer)$customer=Customer::create(['first_name'=>mb_substr(trim($profile['name']??ucfirst($identity['platform']).' customer'),0,255),'email'=>$conflict?null:$trustedEmail,'phone'=>$conflict?null:$trustedPhone,'status'=>'active','tags'=>[]]);
    $link=CustomerChannelIdentity::create(['customer_id'=>$customer->id,'identity_hash'=>$hash,'platform'=>$identity['platform'],'merchant_key'=>$identity['merchant_key'],'app_id'=>$identity['app_id'],'account_id'=>$identity['account_id'],'external_sender_id'=>$identity['sender_id'],'first_seen_at'=>$firstSeen,'last_seen_at'=>$lastSeen]);
   }
   if(($trustedEmail&&$customer->email&&ContactValueNormalizer::email($customer->email)!==$trustedEmail)||($trustedPhone&&$customer->phone&&ContactValueNormalizer::phone($customer->phone)!==$trustedPhone))$conflict=true;
   if(!$conflict){$fill=[];if(!$customer->email&&$trustedEmail)$fill['email']=$trustedEmail;if(!$customer->phone&&$trustedPhone)$fill['phone']=$trustedPhone;if($fill)$customer->update($fill);}
   $metadata=$link->metadata??[];
   foreach(['email'=>$email,'phone'=>$phone] as $key=>$value)if($value)$metadata['provided_'.$key]=$value;
   if($conflict)$metadata['matching_conflict']=true;
   $link->update(['display_name'=>isset($profile['name'])?mb_substr($profile['name'],0,255):$link->display_name,'username'=>$profile['username']??$link->username,'avatar_url'=>$profile['avatar_url']??$link->avatar_url,'metadata'=>$metadata,'first_seen_at'=>$link->first_seen_at->min($firstSeen),'last_seen_at'=>$link->last_seen_at->max($lastSeen)]);
   // Provider display names improve provisional names, never replace known buyer names.
   if(!empty($profile['name'])&&(!$customer->first_name||$customer->first_name==='Guest'||str_ends_with($customer->first_name,' customer')))$customer->update(['first_name'=>mb_substr($profile['name'],0,255)]);
   $presence=CustomerPlatformPresence::firstOrCreate(['customer_id'=>$customer->id,'platform'=>$identity['platform']],['first_seen_at'=>$firstSeen,'last_seen_at'=>$lastSeen]);
   $presence->update(['first_seen_at'=>$presence->first_seen_at->min($firstSeen),'last_seen_at'=>$presence->last_seen_at->max($lastSeen)]);
   $conversation->update(['customer_id'=>$customer->id]);return $customer;
  },3);
 }
 private function contradicts(Customer $customer,?string $email,?string $phone):bool {
  return ($email&&$customer->email&&ContactValueNormalizer::email($customer->email)!==$email)||($phone&&$customer->phone&&ContactValueNormalizer::phone($customer->phone)!==$phone);
 }
}
