<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Http;

class MarketingEvolinkClient
{
    public function submit(array $payload): array
    {
        try{$r=Http::withToken((string)config('marketing.native.api_key'))->acceptJson()->connectTimeout(8)->timeout(60)->withoutRedirecting()->post('https://api.evolink.ai/v1/videos/generations',$payload);}
        catch(\Throwable){throw new MarketingProductionFailure('native_submission_outcome_unknown','unknown');}
        if($r->status()>=400&&$r->status()<500)throw new MarketingProductionFailure('native_request_rejected');
        if(!$r->successful())throw new MarketingProductionFailure('native_submission_outcome_unknown','unknown');
        $data=$r->json();
        if(!is_array($data)||!preg_match('/^[a-zA-Z0-9_-]{1,160}$/D',$data['id']??''))throw new MarketingProductionFailure('native_submission_outcome_unknown','unknown');
        return ['id'=>$data['id'],'usage'=>$this->usage($data)];
    }

    public function query(string $id): array
    {
        if(!preg_match('/^[a-zA-Z0-9_-]{1,160}$/D',$id))throw new MarketingProductionFailure('native_task_invalid','unknown');
        try{$r=Http::withToken((string)config('marketing.native.api_key'))->acceptJson()->connectTimeout(8)->timeout(30)->withoutRedirecting()->get('https://api.evolink.ai/v1/tasks/'.$id);}
        catch(\Throwable){throw new MarketingProductionFailure('native_status_unavailable','unknown');}
        $data=$r->json();
        if(!$r->successful()||!is_array($data)||($data['id']??null)!==$id||!in_array($data['status']??null,['pending','queued','processing','running','completed','failed','cancelled'],true))throw new MarketingProductionFailure('native_status_unavailable','unknown');
        return ['status'=>$data['status'],'results'=>$data['results']??[],'usage'=>$this->usage($data)];
    }

    private function usage(array $data): array
    {
        $usage=[];foreach(['credits_reserved','credits_consumed','credits_used','total_tokens','video_duration','billing_rule'] as $key){$v=$data['usage'][$key]??null;if(is_int($v)||is_float($v)||is_string($v)&&strlen($v)<80)$usage[$key]=$v;}
        return $usage;
    }
}
