<?php
namespace App\Services\Support;

use App\Models\{SupportConversation,SupportMessage,SupportReplyBatch};
use App\Services\SupportFaqService;
use App\Jobs\{ProcessSupportReplyBatch,PublishSupportReplySegment};
use Illuminate\Support\Facades\{Cache,DB,Schema,Log};
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class SupportReplyCadenceService
{
    private const ACTIVE=['collecting','generating','ready'];
    public function enqueue(SupportMessage $source,?string $language=null):bool
    {
        $settings=app(SupportFaqService::class)->read();$policy=app(SupportReplyCadencePolicy::class)->settings($settings);
        // Attachment analysis/confirmation retains its established source lifecycle.
        if(!$policy['enabled']||!Schema::hasTable('support_reply_batches')||$source->attachments()->exists())return false;
        $c=SupportConversation::find($source->conversation_id);
        if(!$c||$source->sender_role!=='customer'||!app(SupportAiPolicy::class)->enabled($settings,$c->channel))return false;
        if(app(SupportEscalationPolicy::class)->requested($source->content,$settings))return false;
        DB::transaction(function()use($source,$language,$policy,$settings,$c){
            $c=SupportConversation::whereKey($c->id)->lockForUpdate()->first();
            if($c->status!=='open'||$this->archived($c)||(int)$c->messages()->where('sender_role','customer')->max('id')!==$source->id)return;
            $control=(int)$c->notes()->max('id');
            $active=SupportReplyBatch::where('conversation_id',$c->id)->whereIn('status',self::ACTIVE)->lockForUpdate()->orderByDesc('latest_source_id')->first();
            if($active&&$active->latest_source_id===$source->id)return;
            $carryFirst=null;
            if($active&&in_array($active->status,['generating','ready'],true)&&$active->next_index===0)$carryFirst=$c->messages()->find($active->first_source_id);
            if(!$active){
                $previous=SupportReplyBatch::where('conversation_id',$c->id)->where('latest_source_id','<',$source->id)->orderByDesc('latest_source_id')->first();
                if($previous&&$previous->status==='cancelled'&&in_array($previous->stop_reason,['input_or_control_changed','generation_superseded'],true)&&$previous->next_index===0&&$previous->settings_version===(int)($settings['version']??0)&&$previous->control_version===$control)$carryFirst=$c->messages()->find($previous->first_source_id);
            }
            if($active&&($active->status!=='collecting'||$active->settings_version!==(int)($settings['version']??0)||$active->control_version!==$control)){
                $active->update(['status'=>'cancelled','stop_reason'=>'input_or_control_changed']);$active=null;
            }
            if($active){
                $due=$source->created_at->copy()->addSeconds($policy['collectionSeconds']);
                $active->update(['latest_source_id'=>$source->id,'language'=>$language??$active->language,'due_at'=>$due->min($active->deadline_at)]);
                $this->collectLater($active);return;
            }
            // A redelivered old ingress job must not create another answer.
            $consumed=(int)SupportReplyBatch::where('conversation_id',$c->id)->max('latest_source_id');
            if($consumed>=$source->id)return;
            $boundary=max($consumed,(int)$c->messages()->whereIn('sender_role',['ai','human_agent'])->max('id'));
            $first=$carryFirst??$c->messages()->where('sender_role','customer')->where('id','>',$boundary)->where('id','<=',$source->id)->where('created_at','>=',$source->created_at->copy()->subSeconds(5))->oldest('id')->first()??$source;
            $deadline=$first->created_at->copy()->addSeconds(5);
            $batch=SupportReplyBatch::create(['id'=>(string)Str::uuid(),'conversation_id'=>$c->id,'first_source_id'=>$first->id,'latest_source_id'=>$source->id,'language'=>$language,
                'status'=>'collecting','settings_version'=>(int)($settings['version']??0),'control_version'=>$control,'deadline_at'=>$deadline,
                'due_at'=>$source->created_at->copy()->addSeconds($policy['collectionSeconds'])->min($deadline)]);
            $this->collectLater($batch);
        });
        return true;
    }
    private function collectLater(SupportReplyBatch $batch):void
    {
        ProcessSupportReplyBatch::dispatch($batch->id)->onConnection('support')->onQueue('support-ai')->delay($batch->due_at)->beforeCommit();
    }
    private function publishLater(SupportReplyBatch $batch):void
    {
        PublishSupportReplySegment::dispatch($batch->id)->onConnection('support')->onQueue('support-ai')->delay($batch->due_at)->beforeCommit();
    }
    private function archived(SupportConversation $c):bool {return $c->notes()->where('content','like','[Archive] %')->latest('id')->value('content')==='[Archive] true';}
    private function eligible(SupportReplyBatch $batch,SupportConversation $c,array $settings):bool
    {
        $ack=($batch->trace['escalation']??null)==='explicit_request';
        return app(SupportReplyCadencePolicy::class)->settings($settings)['enabled']&&app(SupportAiPolicy::class)->enabled($settings,$c->channel)
            &&($c->status==='open'||($ack&&$c->status==='waiting_human'))&&!$this->archived($c)
            &&$batch->settings_version===(int)($settings['version']??0)&&$batch->control_version===(int)$c->notes()->max('id')
            &&$batch->latest_source_id===(int)$c->messages()->where('sender_role','customer')->max('id')
            &&(int)$c->messages()->where('sender_role','human_agent')->max('id')<$batch->latest_source_id;
    }
    public function process(string $batchId):void
    {
        $lock=Cache::lock('support-cadence-generate:'.$batchId,115);
        if(!$lock->get()){
            $batch=SupportReplyBatch::find($batchId);
            if($batch&&in_array($batch->status,['collecting','generating'],true))ProcessSupportReplyBatch::dispatch($batchId)->onConnection('support')->onQueue('support-ai')->delay(now()->addSeconds(5));
            return;
        }
        try{
            $batch=SupportReplyBatch::find($batchId);if(!$batch)return;
            if($batch->status==='ready'){$this->publishLater($batch);return;}
            if($batch->status==='generating'){
                // Never rebill a possibly completed generation after a crashed worker.
                $batch->update(['status'=>'failed','stop_reason'=>'generation_interrupted']);
                SupportConversation::whereKey($batch->conversation_id)->update(['updated_at'=>now()]);
                return;
            }
            if($batch->status!=='collecting')return;
            $ready=DB::transaction(function()use($batch){
                $c=SupportConversation::whereKey($batch->conversation_id)->lockForUpdate()->first();$b=SupportReplyBatch::whereKey($batch->id)->lockForUpdate()->first();
                if(!$c||$b->status!=='collecting')return null;
                if(!$this->eligible($b,$c,app(SupportFaqService::class)->read())){$b->update(['status'=>'cancelled','stop_reason'=>'input_or_control_changed']);return null;}
                if($b->due_at->isFuture()){$this->collectLater($b);return null;}
                $b->update(['status'=>'generating','generation_started_at'=>now()]);return [$b,$c];
            });
            if(!$ready)return;[$batch,$c]=$ready;
            $inputs=$c->messages()->where('sender_role','customer')->whereBetween('id',[$batch->first_source_id,$batch->latest_source_id])->orderBy('id')->get();
            $content=implode("\n\n",$inputs->pluck('content')->all());
            if(mb_strlen($content)>10000){$batch->update(['status'=>'failed','stop_reason'=>'input_budget_exceeded']);return;}
            $source=$inputs->last();if(!$source){$batch->update(['status'=>'failed','stop_reason'=>'source_unavailable']);return;}
            $completed=app(FaqAutoReplyService::class)->reply($c,['client_message_id'=>$source->client_message_id,'content'=>$content,'language'=>$batch->language??'auto_detect','_cadence_batch'=>$batch->id,'_combinedInput'=>true]);
            $batch->refresh();
            if($batch->status==='ready')$this->publishLater($batch);
            elseif($batch->status==='generating'){
                $batch->update(['status'=>$completed?'cancelled':'collecting','stop_reason'=>$completed?'generation_superseded':null,'due_at'=>now()->addSeconds(5)]);
                if(!$completed)$this->collectLater($batch);
            }
        }catch(\Throwable $error){
            SupportReplyBatch::whereKey($batchId)->whereIn('status',['generating','collecting'])->update(['status'=>'failed','stop_reason'=>'generation_failed']);
            Log::warning('support_cadence_generation_failed',['batch_id'=>$batchId,'error_type'=>get_class($error)]);
        }finally{$lock->release();}
    }
    public function prepare(string $batchId,SupportConversation $c,SupportMessage $source,string $answer,array $actions,array $trace,array $settings):void
    {
        $batch=SupportReplyBatch::whereKey($batchId)->where('conversation_id',$c->id)->where('latest_source_id',$source->id)->lockForUpdate()->first();
        if(!$batch||$batch->status!=='generating')return;
        $policy=app(SupportReplyCadencePolicy::class)->settings($settings);
        $single=($trace['escalation']??null)!==null||in_array($trace['decision']??'', ['handoff_requested','escalation_offered','server_order','verification_required','needs_reference','rate_limited','attachment_confirmation','generation_unavailable'],true)
            ||collect($trace['tools']??[])->contains(fn($t)=>($t['tool']??'')==='order.lookup');
        try{$segments=app(SupportReplySegmenter::class)->segments($answer,$single?[]:($trace['candidateSegments']??[]),$single?1:$policy['maxSegments'],in_array($c->channel,['facebook','instagram','whatsapp'],true)?2000:10000);}
        catch(\LengthException $e){$batch->update(['status'=>'failed','stop_reason'=>'answer_exceeds_channel_limit']);return;}
        if(in_array($c->channel,['facebook','instagram','whatsapp'],true)){
            $payload=end($segments);
            foreach(app(SupportActionResolver::class)->resolve($actions,$payload)['actions'] as $action){
                if(isset($action['url'])&&str_starts_with($action['url'],'/')&&!str_starts_with($action['url'],'//'))$payload.="\n".($action['label']??'View').': '.rtrim(config('app.url'),'/').$action['url'];
            }
            if(mb_strlen($payload)>2000){$batch->update(['status'=>'failed','stop_reason'=>'answer_exceeds_channel_limit']);return;}
        }
        unset($trace['candidateSegments']);
        $trace['cadence']=['batchId'=>$batch->id,'totalSegments'=>count($segments),'sourceMessageIds'=>$c->messages()->where('sender_role','customer')->whereBetween('id',[$batch->first_source_id,$batch->latest_source_id])->pluck('id')->all()];
        $batch->update(['status'=>'ready','segments'=>$segments,'actions'=>$actions,'trace'=>$trace,'next_index'=>0,'interval_seconds'=>$single?0:$policy['intervalSeconds'],
            'control_version'=>(int)$c->notes()->max('id'),'due_at'=>now()]);
        $c->touch();
    }
    public function publish(string $batchId):void
    {
        $lock=Cache::lock('support-cadence-publish:'.$batchId,35);
        if(!$lock->get()){
            if(SupportReplyBatch::whereKey($batchId)->where('status','ready')->exists())PublishSupportReplySegment::dispatch($batchId)->onConnection('support')->onQueue('support-ai')->delay(now()->addSeconds(5));
            return;
        }
        try{
            $reserved=DB::transaction(function()use($batchId){
                $batch=SupportReplyBatch::find($batchId);if(!$batch)return null;
                $c=SupportConversation::whereKey($batch->conversation_id)->lockForUpdate()->first();$batch=SupportReplyBatch::whereKey($batchId)->lockForUpdate()->first();
                if(!$c||$batch->status!=='ready')return null;
                if(!$this->eligible($batch,$c,app(SupportFaqService::class)->read())){$batch->update(['status'=>'cancelled','stop_reason'=>'input_or_control_changed']);return null;}
                if($batch->due_at->isFuture())return null;
                $parts=$batch->segments??[];$index=$batch->next_index;
                if(!isset($parts[$index])){$batch->update(['status'=>'failed','stop_reason'=>'segment_unavailable']);return null;}
                $uuid=(string)Uuid::uuid5(Uuid::NAMESPACE_DNS,'support-cadence:'.$batchId.':'.$index);
                $social=in_array($c->channel,['facebook','instagram','whatsapp'],true);
                $trace=$batch->trace??[];$trace['cadence']=[...($trace['cadence']??[]),'segment'=>$index+1,'total'=>count($parts)];
                $message=$c->messages()->firstOrCreate(['client_message_id'=>$uuid],['sender_role'=>'ai','content'=>$parts[$index],
                    'actions'=>$index===count($parts)-1?($batch->actions??[]):[],'ai_trace'=>$trace,
                    'ai_settings_version'=>$batch->settings_version,'ai_control_version'=>$batch->control_version,'delivery_status'=>$social?'pending':'sent']);
                $c->update(['last_message_at'=>now()]);
                return [$batch,$c,$message,SupportMessage::find($batch->latest_source_id)];
            });
            if(!$reserved)return;[$batch,$c,$message,$source]=$reserved;
            if(!$source){$batch->update(['status'=>'failed','stop_reason'=>'source_unavailable']);return;}
            if($c->channel==='whatsapp')app(\App\Services\Social\WhatsAppReplyService::class)->sendGenerated($message,$source);
            elseif(in_array($c->channel,['facebook','instagram'],true))app(\App\Services\Social\MessengerReplyService::class)->sendGenerated($message,$source);
            $status=$message->fresh()->delivery_status;
            DB::transaction(function()use($batch,$status){
                $b=SupportReplyBatch::whereKey($batch->id)->lockForUpdate()->first();if($b->status!=='ready')return;
                if(!in_array($status,['accepted','sent','delivered','read'],true)){$b->update(['status'=>$status==='failed'?'failed':'unknown','stop_reason'=>$status==='failed'?'provider_rejected':'delivery_unconfirmed']);return;}
                $next=$b->next_index+1;$done=$next>=count($b->segments??[]);
                $b->update(['next_index'=>$next,'status'=>$done?'completed':'ready','due_at'=>now()->addSeconds($b->interval_seconds)]);
                if(!$done)$this->publishLater($b);
            });
        }finally{$lock->release();}
    }
}
