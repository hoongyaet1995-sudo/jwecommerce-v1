<?php
namespace App\Services\Support;

use App\Models\{SupportAttachment,SupportAttachmentAnalysis,SupportMessage,SupportConversation};
use App\Jobs\AnalyzeSupportAttachments;
use App\Services\{SupportFaqService};
use App\Services\AI\ProviderFailure;
use Illuminate\Support\Facades\{DB,RateLimiter};

class SupportAttachmentAnalysisService
{
    private function settings(): array { return app(SupportFaqService::class)->read(); }
    private function attachments(SupportMessage $source,array $settings)
    {
        $policy=app(SupportAttachmentAnalysisPolicy::class)->settings($settings);
        return $source->attachments()->get()->filter(fn($a)=>($policy[$a->kind]??false) && ($a->kind!=='audio'||$a->conversation->channel==='whatsapp'));
    }
    private function active(SupportMessage $source,array $settings): bool
    {
        $c=SupportConversation::find($source->conversation_id);
        return $source->sender_role==='customer' && $c && app(SupportAttachmentAnalysisPolicy::class)->conversationEligible($c,$settings)
            && (int)$c->messages()->where('sender_role','customer')->max('id')===$source->id
            && (int)$c->messages()->where('sender_role','human_agent')->max('id')<$source->id;
    }
    private function record(SupportAttachment $a,array $settings): SupportAttachmentAnalysis
    {
        $key=hash('sha256',$a->id.':'.$a->sha256.':'.($settings['version']??0));
        $latest=SupportAttachmentAnalysis::where('attachment_id',$a->id)->where('settings_version',$settings['version']??0)->where('source_hash',$a->sha256??str_repeat('0',64))->latest('id')->first();
        if($latest)return $latest;
        return SupportAttachmentAnalysis::firstOrCreate(['attempt_key'=>$key],[
            'attachment_id'=>$a->id,'source_message_id'=>$a->message_id,
            'merchant_key'=>$a->merchant_key??config('services.facebook.merchant_key','jw-ecommerce'),
            'kind'=>$a->kind,'status'=>'pending','source_hash'=>$a->sha256??str_repeat('0',64),'settings_version'=>$settings['version']??0,
            'expires_at'=>now()->addDays(app(SupportAttachmentAnalysisPolicy::class)->settings($settings)['retentionDays']),
        ]);
    }
    public function schedule(SupportMessage $source): bool
    {
        $settings=$this->settings();
        if(!$this->active($source,$settings)) return false;
        $attachments=$this->attachments($source,$settings); if($attachments->isEmpty()) return false;
        $new=false;
        foreach($attachments as $a) if($a->status==='ready'||$a->status==='failed') $new=$this->record($a,$settings)->wasRecentlyCreated||$new;
        if($new) AnalyzeSupportAttachments::dispatch($source->id)->onConnection('support')->onQueue('support-ai')->afterCommit();
        return true;
    }
    public function process(SupportMessage $source): bool
    {
        $settings=$this->settings(); if(!$this->active($source,$settings)) return false;
        $attachments=$this->attachments($source,$settings);
        if($attachments->isEmpty()||$attachments->contains(fn($a)=>!in_array($a->status,['ready','failed'],true))) return false;
        foreach($attachments as $a) {
            if($a->merchant_key!==$a->conversation->merchant_key || $a->owner_hash!==$a->conversation->owner_hash) return false;
            $analysis=$this->record($a,$settings);
            if($analysis->status==='processing' && $analysis->started_at?->lt(now()->subMinutes(3))) $analysis->update(['status'=>'failed','failure_code'=>'interrupted','completed_at'=>now()]);
            if($analysis->status!=='pending') continue;
            $claimed=SupportAttachmentAnalysis::whereKey($analysis->id)->where('status','pending')->update(['status'=>'processing','started_at'=>now()]);
            if(!$claimed) continue;
            try {
                if($a->status==='failed') throw new ProviderFailure('download_failed');
                $key='support-media-daily:'.$a->owner_hash.':'.now()->toDateString();
                if(RateLimiter::tooManyAttempts($key,20)) throw new ProviderFailure('analysis_quota');
                RateLimiter::hit($key,86400);
                $result=app(SupportAttachmentAnalyzer::class)->analyze($a,$settings);
                $analysis->update(['status'=>'ready','result'=>$result,'provider'=>$result['provider'],'model'=>$result['model'],'completed_at'=>now(),'failure_code'=>null]);
            } catch(\Throwable $error) {
                $code=$error instanceof ProviderFailure?$error->reason:'unavailable';
                $analysis->update(['status'=>$code==='unsupported'?'unsupported':'failed','failure_code'=>$code,'completed_at'=>now()]);
            }
            $a->conversation->touch();
            // One billable call per queue invocation keeps work within support retry_after.
            break;
        }
        return $this->context($source)!==null;
    }
    public function needsProcessing(SupportMessage $source): bool
    {
        $settings=$this->settings();
        return $this->active($source,$settings)
            && !$this->attachments($source,$settings)->contains(fn($a)=>!in_array($a->status,['ready','failed'],true))
            && SupportAttachmentAnalysis::where('source_message_id',$source->id)->where('settings_version',$settings['version']??0)->where('status','pending')->exists();
    }
    public function view(SupportAttachment $a): ?array
    {
        $analysis=SupportAttachmentAnalysis::where('attachment_id',$a->id)->latest('id')->first();
        if(!$analysis)return null;
        if($analysis->merchant_key!==($a->merchant_key??config('services.facebook.merchant_key','jw-ecommerce')))return null;
        $ready=$analysis->status==='ready'&&$analysis->expires_at?->isFuture();
        return ['status'=>$analysis->status==='ready'&&!$ready?'expired':$analysis->status,
            'text'=>$ready?($analysis->result['text']??''):null,'limitations'=>$ready?($analysis->result['limitations']??[]):[],
            'needsConfirmation'=>$ready?($analysis->result['needsConfirmation']??true):true,
            'failureCode'=>$analysis->failure_code,'analyzedAt'=>$analysis->completed_at?->toIso8601String(),
            'retryUrl'=>in_array($analysis->status,['failed','unsupported'],true)?'/admin/api/support/attachments/'.$a->id.'/analysis/retry':null];
    }
    public function retry(SupportAttachment $a,int $staffId): void
    {
        $source=SupportMessage::find($a->message_id);$settings=$this->settings();
        abort_unless($source&&$this->active($source,$settings)&&$a->status==='ready',409,'Reanalysis requires the current AI-owned conversation and a ready file.');
        abort_unless($a->merchant_key===null||$a->merchant_key===config('services.facebook.merchant_key','jw-ecommerce'),404);
        abort_unless(app(SupportAttachmentAnalysisPolicy::class)->settings($settings)[$a->kind]??false,409,'Enable this analysis capability first.');
        DB::transaction(function()use($a,$source,$settings,$staffId){
            SupportAttachment::whereKey($a->id)->lockForUpdate()->firstOrFail();
            $latest=SupportAttachmentAnalysis::where('attachment_id',$a->id)->latest('id')->first();
            abort_if($latest&&in_array($latest->status,['pending','processing'],true),409,'Analysis is already in progress.');
            SupportAttachmentAnalysis::create(['attachment_id'=>$a->id,'source_message_id'=>$source->id,'merchant_key'=>$a->merchant_key??config('services.facebook.merchant_key','jw-ecommerce'),'kind'=>$a->kind,'source_hash'=>$a->sha256,'settings_version'=>$settings['version']??0,'attempt_key'=>hash('sha256',(string)\Illuminate\Support\Str::uuid()),'status'=>'pending','expires_at'=>now()->addDays(app(SupportAttachmentAnalysisPolicy::class)->settings($settings)['retentionDays'])]);
            $a->conversation->notes()->create(['staff_user_id'=>$staffId,'content'=>'[Attachment analysis] Explicit retry requested for '.$a->id]);
            AnalyzeSupportAttachments::dispatch($source->id)->onConnection('support')->onQueue('support-ai')->afterCommit();
        });
    }
    public function context(SupportMessage $source): ?array
    {
        $settings=$this->settings(); if(!$this->active($source,$settings)) return null;
        $attachments=$this->attachments($source,$settings); if($attachments->isEmpty()) return null;
        $items=[];
        foreach($attachments as $a) {
            if($a->merchant_key!==$a->conversation->merchant_key || $a->owner_hash!==$a->conversation->owner_hash) return null;
            $analysis=SupportAttachmentAnalysis::where('attachment_id',$a->id)->where('source_message_id',$source->id)->where('settings_version',$settings['version']??0)->where('source_hash',$a->sha256??str_repeat('0',64))->latest('id')->first();
            if(!$analysis || in_array($analysis->status,['pending','processing'],true)) return null;
            if($analysis->status==='ready'&&$analysis->expires_at?->isFuture()) $items[]=($analysis->result??[])+['id'=>'attachment:'.$a->id];
            else $items[]=['id'=>'attachment:'.$a->id,'text'=>'','needsConfirmation'=>true,'limitations'=>['Attachment analysis unavailable. Ask the customer to describe it or request staff help.']];
        }
        return ['items'=>$items];
    }
}
