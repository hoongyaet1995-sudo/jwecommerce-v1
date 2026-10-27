<?php
namespace App\Services\Marketing;
use App\Models\AiConnection;

/** Trusted platform rate card. No user/AI model prices, and no permission to send requests. */
class MarketingGenerationPricing
{
    public function enabled():bool {return (bool)config('marketing.billing.enabled',false);}
    public function configuration():array {
        $date=config('marketing.billing.pricing_verified_at','2026-10-23');
        abort_unless($this->enabled()&&now()->lt(config('marketing.billing.pricing_valid_until','2026-10-30')),423,'当前制作费率待更新，未开始生成。');
        $version='customer-rates-v1:'.$date;
        $text=['model'=>'gpt-5.6-luna','version'=>$version,'cap_microusd'=>50000,'input_usd_per_million'=>0.2,'output_usd_per_million'=>1.2,'cached_usd_per_million'=>0.02,'cache_write_usd_per_million'=>0.25,'cache_write_12h_usd_per_million'=>0.5,'pricing_verified_at'=>$date];
        $video=['model'=>MarketingEconomyVideoPolicy::PRO,'version'=>$version,'cap_microusd'=>660000,'credit_usd'=>10/650,'pricing_verified_at'=>$date,'access_verified_at'=>$date,'pricing_valid_until'=>config('marketing.billing.pricing_valid_until','2026-10-30'),'customer_billing'=>true,'adapt_prompt'=>true,'tiers'=>['ordinary']];
        foreach(range(4,12) as $seconds)$video['duration_caps'][$seconds]=(int)ceil($seconds*3.5568*(10/650)*1000000);
        $fast=array_replace($video,['model'=>MarketingEconomyVideoPolicy::FAST,'cap_microusd'=>167000,'source_has_audio'=>false,'source_audio_verified_at'=>$date]);$fast['duration_caps']=[];
        foreach(range(2,12) as $seconds)$fast['duration_caps'][$seconds]=(int)ceil($seconds*.9*(10/650)*1000000);
        $rates=['semantic'=>$text,'qa'=>$text,'image'=>['model'=>'gpt-image-2','version'=>MarketingImagePricing::VERSION,'cap_microusd'=>500000,'billing_mode'=>'standard','text_usd_per_million'=>5,'image_usd_per_million'=>8,'output_usd_per_million'=>30,'pricing_verified_at'=>$date,'enforce_request_bound'=>false],
            'video'=>$video,'upload'=>['model'=>'evolink-measured-upload','version'=>$version,'cap_microusd'=>25000,'fee_microusd'=>0,'credit_usd'=>10/650,'pricing_verified_at'=>$date,'measure_balance'=>true],
            'video_models'=>[MarketingEconomyVideoPolicy::PRO=>$video,MarketingEconomyVideoPolicy::FAST=>$fast]];
        $voice=config('marketing.billing.voice_pricing');
        if(is_array($voice)){app(MarketingVoiceCharacterPricing::class)->bound('a',$voice);$rates['voice']=$voice;}
        return $rates;
    }
    public function fingerprint():string {
        $c=AiConnection::where('provider','openai')->first();
        return hash('sha256',json_encode([$this->configuration(),$c?->version,$c?->enabled,$c?->text_model,$c?->image_model,$c?->verified_at?->toIso8601String(),hash_hmac('sha256',(string)$c?->api_key,(string)config('app.key')),app(MarketingEvolinkAdapter::class)->connectionFingerprint()],JSON_THROW_ON_ERROR));
    }
    public function install(array $rates):void {
        foreach(['semantic','qa','image','video','upload'] as $role)config(['marketing.storyboard.roles.'.$role=>$rates[$role]]);
        if(isset($rates['voice']))config(['marketing.storyboard.roles.voice'=>$rates['voice']]);
        config(['marketing.storyboard.video_models'=>$rates['video_models'],'marketing.storyboard.max_repairs'=>0,
            'marketing.storyboard.audio.native'=>['model'=>$rates['video']['model'],'version'=>$rates['video']['version'],'verified_at'=>$rates['video']['pricing_verified_at'],'languages'=>['zh-MY','English','ms-MY'],'script_request_supported'=>true,'customer_billing'=>true]]);
    }
    public function preview(string $project):bool {
        if(!$this->enabled())return false;
        $this->install($this->configuration());return true;
    }
}
