<?php
namespace App\Jobs;

use App\Models\SupportMessage;
use App\Services\Support\{SupportAttachmentAnalysisService,FaqAutoReplyService};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class AnalyzeSupportAttachments implements ShouldQueue
{
    use Queueable;
    public int $tries=1;
    public int $timeout=100;
    public function __construct(public int $sourceId) {}
    public function failed(?\Throwable $error): void
    {
        // A killed worker must not leave a billable attempt permanently processing.
        \App\Models\SupportAttachmentAnalysis::where('source_message_id',$this->sourceId)->whereIn('status',['pending','processing'])->update(['status'=>'failed','failure_code'=>'interrupted','completed_at'=>now()]);
        $source=SupportMessage::find($this->sourceId);
        if($source && app(SupportAttachmentAnalysisService::class)->context($source)!==null) $this->dispatchReply($source);
    }
    private function dispatchReply(SupportMessage $source): void
    {
        $c=\App\Models\SupportConversation::find($source->conversation_id);
        if(!$c)return;
        if($c->channel==='whatsapp') ProcessWhatsAppSupportReply::dispatch($source->id)->onConnection('support')->onQueue('support-ai');
        elseif(in_array($c->channel,['facebook','instagram'],true)) ProcessMessengerSupportReply::dispatch($source->id)->onConnection('support')->onQueue('support-ai');
        else ReplyToAnalyzedSupportAttachment::dispatch($source->id)->onConnection('support')->onQueue('support-ai');
    }
    public function handle(SupportAttachmentAnalysisService $service): void
    {
        $source=SupportMessage::find($this->sourceId); if(!$source)return;
        $lock=Cache::lock('support-media:'.$source->id,110);
        if(!$lock->get())return;
        try {
            if(!$service->process($source)) {
                if($service->needsProcessing($source)) self::dispatch($source->id)->onConnection('support')->onQueue('support-ai')->delay(1);
                return;
            }
            // Generation is a separate job; media processing and text generation have independent time budgets.
            $this->dispatchReply($source);
        } finally {$lock->release();}
    }
}
