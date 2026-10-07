<?php
namespace App\Services\Support;
use App\Models\{CustomerChannelIdentity,SupportConversation};
class SupportCustomerMemoryPolicy
{
    public function settings(array $settings):array {
        $v=$settings['customerMemory']??[];
        return ['enabled'=>($v['enabled']??false)===true,'retentionDays'=>max(7,min(90,(int)($v['retentionDays']??30)))];
    }
    public function scope(SupportConversation $c):?array {
        $channel=match($c->channel){null,'web_guest','web_customer'=>'storefront',default=>$c->channel};
        if(!$c->customer_id||($channel==='storefront'&&!$c->customer_user_id))return null;
        $merchant=$c->merchant_key?:config('services.facebook.merchant_key','jw-ecommerce');
        $links=CustomerChannelIdentity::where('customer_id',$c->customer_id)->where('platform',$channel)->where('merchant_key',$merchant);
        if($channel==='storefront')$links->where('external_sender_id','user:'.$c->customer_user_id);
        else $links->where('external_sender_id',$c->provider_sender_id)->where('account_id',$c->provider_page_id);
        $matches=$links->limit(2)->get();if($matches->count()!==1)return null;$link=$matches->first();
        if(!$link||($link->metadata['matching_conflict']??false))return null;
        return ['merchant_key'=>$merchant,'identity_hash'=>$link->identity_hash,'customer_id'=>$c->customer_id,'trust_hash'=>$link->metadata['memory_trust_hash']??null];
    }
}
