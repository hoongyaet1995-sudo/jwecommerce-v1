<?php

namespace App\Services\Marketing;

use App\Models\AiConnection;
use App\Models\MarketingProject;
use Illuminate\Support\Str;

/** Local, owner-scoped, employee-initiated testing. Activation never sends requests. */
class MarketingStoryboardManualTesting
{
    public function activate(string $project): bool
    {
        $path=config('marketing.storyboard.manual_testing_file',storage_path('app/private/marketing/storyboard-manual-testing.json'));
        $d=app()->environment('local')&&is_file($path)?json_decode(file_get_contents($path),true):null;
        if(!is_array($d)||($d['enabled']??false)!==true||!Str::isUuid($d['project_id']??'')||($d['project_id']??'')!==$project){
            if((config('marketing.storyboard.live_grant.mode'))==='manual_local')config(['marketing.storyboard.live_enabled'=>false,'marketing.storyboard.live_grant'=>[]]);
            return false;
        }
        $p=MarketingProject::whereKey($project)->where('owner_id',$d['owner_id']??0)->first();
        $c=AiConnection::where('provider','openai')->where('enabled',true)->whereNotNull('verified_at')->first();
        if(!$p||!$c||(int)$c->version!==($d['connection_version']??0)||$c->image_model!=='gpt-image-2'||$c->text_model!=='gpt-5.6-luna'){
            if(config('marketing.storyboard.live_grant.mode')==='manual_local')config(['marketing.storyboard.live_enabled'=>false,'marketing.storyboard.live_grant'=>[]]);
            return false;
        }
        $price=['model'=>'gpt-5.6-luna','version'=>'manual-local-cache-v1','cap_microusd'=>50000,'input_usd_per_million'=>0.2,'output_usd_per_million'=>1.2,'cached_usd_per_million'=>0.02,'cache_write_usd_per_million'=>0.25,'cache_write_12h_usd_per_million'=>0.5,'pricing_verified_at'=>'2026-10-22'];
        config(['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.max_repairs'=>0,
            'marketing.storyboard.live_grant'=>['mode'=>'manual_local','reference'=>'manual-local:'.$project,'project_id'=>$project,'owner_id'=>$p->owner_id,'roles'=>['semantic','image','qa'],'max_total_microusd'=>100000000,'max_calls'=>1],
            'marketing.storyboard.roles.semantic'=>$price,'marketing.storyboard.roles.qa'=>$price,
            'marketing.storyboard.roles.image'=>['model'=>'gpt-image-2','version'=>MarketingImagePricing::VERSION,'cap_microusd'=>500000,'billing_mode'=>'standard','text_usd_per_million'=>5,'image_usd_per_million'=>8,'output_usd_per_million'=>30,'enforce_request_bound'=>false,'pricing_verified_at'=>'2026-10-22','budget_mode'=>'employee_initiated_manual_local_testing'],
        ]);
        return true;
    }
}
