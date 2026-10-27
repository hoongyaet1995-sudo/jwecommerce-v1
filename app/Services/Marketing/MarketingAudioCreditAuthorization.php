<?php

namespace App\Services\Marketing;

use App\Models\{MarketingProject, MarketingRevision, MarketingProduction, MarketingProductionPlan, MarketingVoiceAsset};
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Local, explicit, one-request existing-credit acceptance. Not a merchant billing system. */
class MarketingAudioCreditAuthorization
{
    public function profile(MarketingProject $p, MarketingRevision $r, string $text): ?array
    {
        $a=config('marketing.production.credit_voice',[]);
        if(empty($a['enabled'])||(!app()->environment('local')&&!app()->runningUnitTests())||($a['project']??null)!==$p->id||($a['revision']??null)!==$r->id)return null;
        $spec=['text'=>$text,'model'=>'eleven_flash_v2_5','voice'=>'21m00Tcm4TlvDq8ikWAM','output_format'=>'mp3_44100_128'];
        $signature=hash('sha256',json_encode($spec,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        try{$expiry=Carbon::parse($a['expires_at']??'');}catch(\Throwable){return null;}
        if(!Str::isUuid($a['id']??'')||!is_int($a['max_credits']??null)||$a['max_credits']<1||$a['max_credits']>100||$expiry->lte(now())||$expiry->gt(now()->addDay())||mb_strlen($text)>307||$text===''||!hash_equals((string)($a['signature']??''),$signature))return null;
        return ['id'=>$a['id'],'project_id'=>$p->id,'revision_id'=>$r->id,'signature'=>$signature,'max_credits'=>$a['max_credits'],'max_requests'=>1,'expires_at'=>$expiry->toIso8601String(),'existing_credits_only'=>true,'topup_allowed'=>false,'retry_allowed'=>false];
    }

    /** Called under the existing project/task transaction, before any provider request. */
    public function claim(MarketingProduction $task, MarketingProductionPlan $plan, array $spec): array
    {
        $p=MarketingProject::findOrFail($task->project_id);$r=MarketingRevision::where('project_id',$p->id)->findOrFail($plan->revision_id);
        $a=$this->profile($p,$r,$spec['text']);
        if(!$a||$plan->project_id!==$task->project_id||$task->plan_id!==$plan->id||$plan->status!=='budget_confirmed'||!$plan->confirmed_at||$plan->confirmed_by!==$task->owner_id||$a!==($plan->manifest['audio']['credit_authorization']??null)||MarketingVoiceAsset::where('usage->credit_authorization->id',$a['id'])->exists())throw new MarketingProductionFailure('credit_authorization_unavailable','blocked');
        $expected=['text'=>$spec['text'],'model'=>'eleven_flash_v2_5','voice'=>'21m00Tcm4TlvDq8ikWAM','output_format'=>'mp3_44100_128'];
        if($spec!==$expected)throw new MarketingProductionFailure('credit_authorization_changed','blocked');
        return $a;
    }
}
