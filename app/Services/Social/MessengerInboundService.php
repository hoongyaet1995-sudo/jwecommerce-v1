<?php

namespace App\Services\Social;

use App\Models\ManagedSocialAccount;
use App\Models\SupportConversation;
use App\Models\User;
use App\Notifications\SupportHumanRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class MessengerInboundService
{
    public function receive(array $payload): int
    {
        if(($payload['object']??null)!=='page')return 0;
        $processed=0;
        foreach((array)($payload['entry']??[]) as $entry){
            if(!is_array($entry))continue;
            $page=$entry['id']??null;
            if(!$this->identity($page))continue;
            foreach((array)($entry['messaging']??[]) as $event){
                if(!is_array($event))continue;
                $sender=data_get($event,'sender.id');$recipient=data_get($event,'recipient.id');
                $mid=data_get($event,'message.mid');$text=data_get($event,'message.text');
                if(!$this->identity($sender)||$sender===$page||$recipient!==$page||data_get($event,'message.is_echo')
                    ||!is_string($mid)||$mid===''||strlen($mid)>512||!is_string($text)||trim($text)===''||mb_strlen($text)>16000)continue;
                $processed += $this->append($page,$sender,$mid,$text,$event)?1:0;
            }
        }
        return $processed;
    }

    private function identity(mixed $value): bool
    {
        return is_string($value)&&preg_match('/^[0-9]{1,100}$/D',$value)===1;
    }

    private function append(string $page,string $sender,string $mid,string $text,array $event): bool
    {
        return DB::transaction(function()use($page,$sender,$mid,$text,$event){
            // Serializes inbound events and disconnects for this Page. No remote calls in the transaction.
            $account=ManagedSocialAccount::where('merchant_key',config('services.facebook.merchant_key','jw-ecommerce'))
                ->where('platform','facebook')->where('app_id',(string)config('services.facebook.client_id'))
                ->where('provider_account_id',$page)->lockForUpdate()->first();
            if(!$account||$account->state==='disconnected'||!$account->inbound_enabled)return false;
            $key=hash('sha256',json_encode([$account->merchant_key,$account->app_id,$page,$mid]));
            if(DB::table('messenger_inbound_receipts')->where('event_key',$key)->exists())return false;
            $owner=hash('sha256',json_encode(['messenger',$account->merchant_key,$account->app_id,$page,$sender]));
            $conversation=SupportConversation::firstOrCreate(['owner_hash'=>$owner],[
                'id'=>(string)Str::uuid(),'channel'=>'facebook','merchant_key'=>$account->merchant_key,
                'provider_page_id'=>$page,'provider_sender_id'=>$sender,'provider_page_name'=>$account->name,'status'=>'waiting_human',
            ]);
            $conversation=SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $timestamp=$event['timestamp']??null;
            $sentAt=is_numeric($timestamp)&&$timestamp>0&&$timestamp<=now()->addMinutes(5)->timestamp*1000
                ? Carbon::createFromTimestampMs((int)$timestamp):now();
            $conversation->messages()->create(['client_message_id'=>(string)Str::uuid(),'sender_role'=>'customer','content'=>$text,'created_at'=>$sentAt,'updated_at'=>now()]);
            if($conversation->notes()->where('content','like','[Archive] %')->latest('id')->value('content')==='[Archive] true')
                $conversation->notes()->create(['content'=>'[Archive] false']);
            $conversation->update(['status'=>$conversation->status==='human_mode'?'human_mode':'waiting_human',
                'provider_page_name'=>$account->name,'last_message_at'=>$conversation->last_message_at?->greaterThan($sentAt)?$conversation->last_message_at:$sentAt]);
            DB::table('messenger_inbound_receipts')->insert(['event_key'=>$key,'conversation_id'=>$conversation->id,'page_id'=>$page,'message_id'=>$mid,'created_at'=>now(),'updated_at'=>now()]);
            $account->update(['last_inbound_at'=>now()]);
            User::where('is_admin',true)->chunkById(100,fn($staff)=>Notification::send($staff,new SupportHumanRequested($conversation->id,'New Messenger message','A customer messaged '.$account->name.'.')));
            return true;
        },3);
    }
}
