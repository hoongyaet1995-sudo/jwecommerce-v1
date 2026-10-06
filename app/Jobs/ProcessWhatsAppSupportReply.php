<?php

namespace App\Jobs;

use App\Models\SupportMessage;
use App\Services\Social\WhatsAppReplyService;
use App\Services\Support\FaqAutoReplyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Ramsey\Uuid\Uuid;

class ProcessWhatsAppSupportReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 25;
    public int $timeout = 80;

    public function __construct(public int $sourceId) {}

    public function handle(FaqAutoReplyService $ai, WhatsAppReplyService $transport): void
    {
        $source = SupportMessage::find($this->sourceId);
        if (!$source || $source->sender_role !== 'customer') return;
        $conversation = \App\Models\SupportConversation::find($source->conversation_id);
        if (!$conversation || !in_array($conversation->channel,['whatsapp'],true) || $conversation->merchant_key !== app(\App\Services\Social\FacebookAccountService::class)->merchant()) return;
        $replyId = (string) Uuid::uuid5(Uuid::NAMESPACE_DNS, 'support-ai:'.$source->id);
        $completed = $ai->reply($conversation, ['client_message_id'=>$source->client_message_id, 'content'=>$source->content]);
        $reply = $conversation->messages()->where('client_message_id',$replyId)->first();
        if ($reply) $transport->sendGenerated($reply, $source);
        elseif (!$completed && $conversation->fresh()->status === 'open' && (int)$conversation->messages()->where('sender_role','customer')->max('id') === $source->id) {
            // Another generation may own the shared lock; release this job, never lose the latest turn.
            $this->release(5);
        }
    }
}
