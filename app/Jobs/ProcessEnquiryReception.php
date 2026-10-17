<?php

namespace App\Jobs;

use App\Models\SupportConversation;
use App\Services\Support\EnquiryReceptionReply;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessEnquiryReception implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 100;

    public function __construct(public string $conversationId, public string $clientMessageId) {}

    public function handle(): void
    {
        $c = SupportConversation::find($this->conversationId);
        if (! $c) {
            return;
        }$m = $c->messages()->where('client_message_id', $this->clientMessageId)->first();
        if ($m) {
            app(EnquiryReceptionReply::class)->reply($c, $m);
        }
    }
}
