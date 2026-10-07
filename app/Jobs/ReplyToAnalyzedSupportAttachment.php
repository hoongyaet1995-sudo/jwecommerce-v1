<?php
namespace App\Jobs;
use App\Models\{SupportMessage,SupportConversation};
use App\Services\Support\FaqAutoReplyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class ReplyToAnalyzedSupportAttachment implements ShouldQueue
{
    use Queueable;
    public int $tries=25;
    public int $timeout=80;
    public function __construct(public int $sourceId) {}
    public function handle(FaqAutoReplyService $ai): void
    {
        $source=SupportMessage::find($this->sourceId); if(!$source)return;
        $c=SupportConversation::find($source->conversation_id);
        if($c&&in_array($c->channel,['web_guest','web_customer'],true)) {
            $completed=$ai->reply($c,['client_message_id'=>$source->client_message_id,'content'=>$source->content]);
            if(!$completed && $c->fresh()->status==='open' && (int)$c->messages()->where('sender_role','customer')->max('id')===$source->id) $this->release(5);
        }
    }
}
