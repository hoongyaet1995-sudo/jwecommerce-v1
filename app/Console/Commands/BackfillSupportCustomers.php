<?php
namespace App\Console\Commands;
use App\Models\{Customer,CustomerChannelIdentity,CustomerPlatformPresence,ManagedSocialAccount,SupportConversation};
use App\Services\Customers\CustomerContactResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillSupportCustomers extends Command {
 protected $signature='social-crm:backfill {--apply : Persist associations; default rolls the simulation back}';
 protected $description='Idempotently associate historical support conversations with CRM contacts';
 public function handle(CustomerContactResolver $resolver):int {
  $stats=['created'=>0,'linked'=>0,'conflicted'=>0,'skipped'=>0];
  // Simulate inside one rollback transaction; no provider calls or queued messages.
  DB::beginTransaction();
  try {
   SupportConversation::orderBy('id')->chunk(100,function($rows)use($resolver,&$stats){foreach($rows as $c){
    $platform=in_array($c->channel,['facebook','instagram','whatsapp','tiktok'],true)?$c->channel:'storefront';$profile=[];
    if($platform==='storefront'){
     $identity=['platform'=>$platform,'merchant_key'=>config('services.facebook.merchant_key','jw-ecommerce'),'app_id'=>'storefront','account_id'=>'storefront','sender_id'=>$c->customer_user_id?'user:'.$c->customer_user_id:$c->owner_hash];
     $note=$c->notes()->where('content','like','[Contact] %')->latest('id')->value('content');$profile=$note?json_decode(substr($note,10),true)??[]:['name'=>'Guest'];
    }else{
     $a=ManagedSocialAccount::where('platform',$platform)->where('merchant_key',$c->merchant_key)->where('provider_account_id',$c->provider_page_id)->where('app_id',(string)config($platform==='whatsapp'?'services.whatsapp.app_id':'services.facebook.client_id'))->first();
     if(!$a||!$c->provider_sender_id){$stats['skipped']++;continue;}
     $identity=['platform'=>$platform,'merchant_key'=>$a->merchant_key,'app_id'=>$a->app_id,'account_id'=>$a->provider_account_id,'sender_id'=>$c->provider_sender_id];
     if($platform==='whatsapp')$profile=['phone'=>'+'.$c->provider_sender_id,'phone_trusted'=>true];
    }
    $before=Customer::count();$customer=$resolver->associate($c,$identity,$profile);$stats[Customer::count()>$before?'created':'linked']++;
    if(CustomerChannelIdentity::where('customer_id',$customer->id)->get()->contains(fn($i)=>($i->metadata['matching_conflict']??false)))$stats['conflicted']++;
   }});
   Customer::where(function($q){$q->whereNotNull('user_id')->orWhereHas('orders');})->chunkById(100,function($rows){foreach($rows as $c)CustomerPlatformPresence::firstOrCreate(['customer_id'=>$c->id,'platform'=>'storefront'],['first_seen_at'=>$c->created_at,'last_seen_at'=>$c->updated_at]);});
   if($this->option('apply'))DB::commit();else DB::rollBack();
  }catch(\Throwable $e){DB::rollBack();throw $e;}
  $this->info(($this->option('apply')?'Applied':'Dry run (rolled back)').': '.json_encode($stats));return self::SUCCESS;
 }
}
